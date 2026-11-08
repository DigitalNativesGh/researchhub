<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('frameworks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->unique()->constrained()->cascadeOnDelete();
            $table->text('problem_statement')->nullable();
            $table->json('objectives')->nullable();
            $table->json('research_questions')->nullable();
            $table->json('hypotheses')->nullable();
            $table->json('variables')->nullable();
            $table->unsignedInteger('version')->default(0);
            $table->string('status', 24)->default('pending');
            $table->text('revision_note')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('chapters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('number');
            $table->string('title');
            $table->string('status', 24)->default('pending');
            $table->unsignedInteger('version')->default(1);
            $table->text('error')->nullable();
            $table->timestamps();

            $table->unique(['project_id', 'number']);
        });

        Schema::create('sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chapter_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('order');
            $table->string('number', 16);
            $table->string('heading');
            $table->text('brief')->nullable();
            $table->json('source_paper_ids')->nullable();
            $table->json('analysis_ids')->nullable();
            $table->unsignedInteger('word_target')->nullable();
            $table->longText('content')->nullable();
            $table->string('status', 24)->default('queued');
            $table->text('error')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->unsignedInteger('current_version')->default(0);
            $table->timestamps();

            $table->unique(['chapter_id', 'order']);
            $table->index(['chapter_id', 'status']);
        });

        Schema::create('section_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('section_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->longText('content');
            $table->string('source', 16);
            $table->json('citations')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['section_id', 'version']);
        });

        Schema::create('citations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('section_id')->constrained()->cascadeOnDelete();
            $table->foreignId('paper_id')->constrained()->cascadeOnDelete();
            $table->text('cited_text')->nullable();
            $table->string('page_or_location', 64)->nullable();
            $table->unsignedInteger('start_page')->nullable();
            $table->unsignedInteger('end_page')->nullable();
            $table->boolean('verified')->default(false);
            $table->timestamps();

            $table->index(['section_id', 'paper_id']);
            $table->index('paper_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('citations');
        Schema::dropIfExists('section_versions');
        Schema::dropIfExists('sections');
        Schema::dropIfExists('chapters');
        Schema::dropIfExists('frameworks');
    }
};
