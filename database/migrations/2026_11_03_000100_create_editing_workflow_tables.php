<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The editing workflow: AI drafts go to the admin, human editors edit them outside the platform, and only the
 * edited versions reach the researcher. See App\Services\Workflow\ProjectWorkflow.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            // What the researcher and the admin queue see. The older `status` column keeps the fine-grained
            // pipeline step (admin-only detail).
            $table->string('workflow_status', 32)->default('draft')->index();
            $table->unsignedTinyInteger('failed_stage')->nullable();
            $table->timestamp('workflow_changed_at')->nullable()->index();
            $table->timestamp('data_submitted_at')->nullable();
        });

        Schema::create('project_transitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); // null = the system
            $table->text('note')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['project_id', 'id']);
        });

        // Every AI draft and every edited version, never overwritten.
        Schema::create('project_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('stage'); // 1 or 3
            $table->string('type', 32); // chapters_1_3, instrument, complete_thesis
            $table->string('slot', 64); // type plus instrument kind, e.g. instrument:questionnaire
            $table->string('label');
            $table->string('kind', 16); // ai_draft, edited
            $table->unsignedInteger('version');
            $table->string('format', 8); // docx, pdf
            $table->string('file_path');
            $table->string('original_name');
            $table->unsignedBigInteger('file_size');
            $table->string('sha256', 64);
            $table->longText('extracted_text')->nullable();
            $table->text('extraction_error')->nullable();
            $table->timestamp('extracted_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('delivered_at')->nullable(); // edited versions only; null = not yet released
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'kind', 'slot', 'version']);
        });

        // Internal only: the researcher never sees editor details.
        Schema::create('editing_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('stage');
            $table->string('editor_name')->nullable();
            $table->date('expected_return_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('project_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 48);
            $table->foreignId('document_id')->nullable()->constrained('project_documents')->nullOnDelete();
            $table->string('summary');
            $table->json('details')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['project_id', 'id']);
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        Schema::table('datasets', function (Blueprint $table) {
            $table->string('format', 8)->nullable();
            $table->string('converted_path')->nullable(); // CSV copy of .sav/.dta files
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('superseded_at')->nullable(); // set when an admin reopens the data upload
        });

        Schema::table('analyses', function (Blueprint $table) {
            $table->string('kind', 16)->default('quantitative'); // quantitative, qualitative
            $table->foreignId('dataset_id')->nullable()->change();
        });

        // Stage 3 replaces the framework with what the editors left in Chapters 1 and 3.
        Schema::table('frameworks', function (Blueprint $table) {
            $table->text('methodology')->nullable();
            $table->string('source', 16)->default('ai');
            $table->json('ai_snapshot')->nullable();
        });

        // Mixed methods projects get a questionnaire and an interview guide; Stage 3 adds the edited instrument.
        $this->dropKeys(['project_id']);
        Schema::table('instruments', function (Blueprint $table) {
            $table->string('source', 16)->default('ai');
            $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();
            $table->unique(['project_id', 'type', 'source']);
        });
    }

    public function down(): void
    {
        $this->dropKeys(['project_id', 'type', 'source']);
        Schema::table('instruments', function (Blueprint $table) {
            $table->dropColumn('source');
            $table->unique('project_id');
            $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();
        });
        Schema::table('frameworks', fn (Blueprint $table) => $table->dropColumn(['methodology', 'source', 'ai_snapshot']));
        Schema::table('analyses', fn (Blueprint $table) => $table->dropColumn('kind'));
        Schema::table('datasets', fn (Blueprint $table) => $table->dropColumn(['format', 'converted_path', 'submitted_at', 'superseded_at']));
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('project_audit_logs');
        Schema::dropIfExists('editing_assignments');
        Schema::dropIfExists('project_documents');
        Schema::dropIfExists('project_transitions');
        Schema::table('projects', function (Blueprint $table) {
            $table->dropIndex(['workflow_status']);
            $table->dropIndex(['workflow_changed_at']);
        });
        Schema::table('projects', fn (Blueprint $table) => $table->dropColumn(['workflow_status', 'failed_stage', 'workflow_changed_at', 'data_submitted_at']));
    }

    /**
     * Drop the instruments foreign key and a unique index that covers project_id. MySQL needs the foreign key
     * gone before the index it uses; SQLite rebuilds the table on dropForeign and loses the index first.
     */
    private function dropKeys(array $unique): void
    {
        $steps = [
            fn (Blueprint $table) => $table->dropForeign(['project_id']),
            fn (Blueprint $table) => $table->dropUnique($unique),
        ];

        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            $steps = array_reverse($steps);
        }

        foreach ($steps as $step) {
            Schema::table('instruments', $step);
        }
    }
};
