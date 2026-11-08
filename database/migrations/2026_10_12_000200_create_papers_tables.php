<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('papers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('original_name');
            $table->string('file_path');
            $table->unsignedBigInteger('file_size');
            $table->char('sha256', 64);
            $table->string('anthropic_file_id')->nullable();
            $table->string('title', 1000)->nullable();
            $table->json('authors')->nullable();
            $table->unsignedSmallInteger('year')->nullable();
            $table->string('journal', 500)->nullable();
            $table->string('volume', 32)->nullable();
            $table->string('issue', 32)->nullable();
            $table->string('pages', 64)->nullable();
            $table->string('doi')->nullable();
            $table->string('citation_key', 80)->nullable();
            $table->string('parse_status', 24)->default('pending');
            $table->text('parse_error')->nullable();
            $table->timestamp('metadata_checked_at')->nullable();
            $table->timestamps();

            $table->unique(['project_id', 'citation_key']);
            $table->unique(['project_id', 'sha256']);
            $table->index(['project_id', 'parse_status']);
        });

        Schema::create('paper_summaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('paper_id')->unique()->constrained()->cascadeOnDelete();
            $table->text('summary');
            $table->json('key_findings');
            $table->text('methods')->nullable();
            $table->json('variables');
            $table->text('limitations')->nullable();
            $table->json('themes')->nullable();
            $table->timestamp('edited_at')->nullable();
            $table->timestamps();
        });

        Schema::create('literature_matrices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->unique()->constrained()->cascadeOnDelete();
            $table->json('themes')->nullable();
            $table->json('rows')->nullable();
            $table->json('gaps')->nullable();
            $table->json('constructs')->nullable();
            $table->string('status', 24)->default('pending');
            $table->text('error')->nullable();
            $table->timestamp('built_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('literature_matrices');
        Schema::dropIfExists('paper_summaries');
        Schema::dropIfExists('papers');
    }
};
