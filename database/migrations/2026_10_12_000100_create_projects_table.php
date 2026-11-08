<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title', 500);
            $table->text('description')->nullable();
            $table->string('discipline');
            $table->string('degree_level', 32);
            $table->string('citation_style', 16)->default('apa7');
            $table->string('research_type', 16);
            $table->unsignedInteger('word_count_target')->nullable();
            $table->date('deadline')->nullable();
            $table->json('keywords')->nullable();
            $table->unsignedTinyInteger('phase')->default(1);
            $table->string('status', 40)->default('draft');
            $table->string('status_message')->nullable();
            $table->text('error')->nullable();
            $table->text('abstract')->nullable();
            $table->text('ai_disclosure')->nullable();
            $table->boolean('disclosure_edited')->default(false);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'updated_at']);
        });

        Schema::create('project_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('type', 40);
            $table->string('title');
            $table->text('message')->nullable();
            $table->string('icon', 40)->default('info');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['project_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_events');
        Schema::dropIfExists('projects');
    }
};
