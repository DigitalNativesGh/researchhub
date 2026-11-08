<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('instruments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('type', 24);
            $table->string('title')->nullable();
            $table->text('instructions')->nullable();
            $table->string('status', 24)->default('pending');
            $table->text('error')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('instrument_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('instrument_id')->constrained()->cascadeOnDelete();
            $table->string('construct');
            $table->string('code', 32);
            $table->text('item_text');
            $table->string('scale_type', 24);
            $table->json('options')->nullable();
            $table->foreignId('source_paper_id')->nullable()->constrained('papers')->nullOnDelete();
            $table->unsignedSmallInteger('order');
            $table->timestamps();

            $table->unique(['instrument_id', 'code']);
            $table->index(['instrument_id', 'order']);
        });

        Schema::create('datasets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('instrument_id')->nullable()->constrained()->nullOnDelete();
            $table->string('kind', 24);
            $table->string('original_name');
            $table->string('file_path');
            $table->unsignedBigInteger('file_size');
            $table->unsignedInteger('row_count')->nullable();
            $table->json('validation_report')->nullable();
            $table->string('invalid_value_policy', 16)->nullable();
            $table->string('status', 24)->default('uploaded');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'kind', 'created_at']);
        });

        Schema::create('analyses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('dataset_id')->constrained()->cascadeOnDelete();
            $table->json('plan')->nullable();
            $table->json('results')->nullable();
            $table->string('status', 24)->default('proposing');
            $table->text('error')->nullable();
            $table->timestamp('plan_approved_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'created_at']);
        });

        Schema::create('ai_calls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('stage', 64);
            $table->string('label', 191)->nullable();
            $table->string('prompt', 96)->nullable();
            $table->string('model', 64);
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->unsignedInteger('cached_tokens')->default(0);
            $table->unsignedInteger('cache_write_tokens')->default(0);
            $table->decimal('cost', 12, 6)->default(0);
            $table->unsignedInteger('duration_ms')->default(0);
            $table->string('status', 16);
            $table->text('error')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['project_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('exports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 24);
            $table->string('format', 8);
            $table->string('file_path')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->json('options')->nullable();
            $table->string('status', 16)->default('pending');
            $table->text('error')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'kind', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exports');
        Schema::dropIfExists('ai_calls');
        Schema::dropIfExists('analyses');
        Schema::dropIfExists('datasets');
        Schema::dropIfExists('instrument_items');
        Schema::dropIfExists('instruments');
    }
};
