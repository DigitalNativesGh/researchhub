<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The service model: the AI no longer writes any part of a thesis. Researchers submit a title, details and
 * sources; the team writes the documents outside the platform and the admin delivers them.
 *
 * Existing projects are moved to the new statuses (the mapping approved on 2026-11-03):
 *   draft → draft · any Stage 1 state, Stage 1 failure, or an old-flow project that had started → submitted ·
 *   stage1_delivered → stage1_delivered · data_uploaded, any Stage 3 state, Stage 3 failure → data_submitted ·
 *   completed → completed.
 * Every move is recorded in project_transitions. AI drafts (project_documents.kind = ai_draft) and the AI tables
 * are kept untouched but no longer shown or written; edited versions become "deliverable".
 */
return new class extends Migration
{
    private const MAP = [
        'stage1_generating' => 'submitted',
        'stage1_awaiting_editing' => 'submitted',
        'stage1_in_editing' => 'submitted',
        'stage1_delivered' => 'stage1_delivered',
        'data_uploaded' => 'data_submitted',
        'stage3_generating' => 'data_submitted',
        'stage3_awaiting_editing' => 'data_submitted',
        'stage3_in_editing' => 'data_submitted',
    ];

    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->string('title_source', 24)->nullable(); // own, suggested, suggested_edited
            $table->string('institution')->nullable();
            $table->text('guidelines')->nullable(); // required length, formatting or institutional rules
            $table->text('writer_notes')->nullable(); // the researcher's notes for the writers
            $table->string('resume_status', 32)->nullable(); // where "needs info" / "on hold" returns to
            $table->text('info_request')->nullable(); // what the team asked the researcher
            $table->timestamp('submitted_at')->nullable();
        });

        Schema::table('papers', function (Blueprint $table) {
            $table->string('kind', 16)->default('source'); // source, guidelines
        });

        $this->moveProjects();
    }

    /** Map existing projects onto the new statuses. Public so the mapping can be tested. */
    public function moveProjects(): void
    {
        $now = now();
        foreach (DB::table('projects')->get(['id', 'workflow_status', 'status', 'failed_stage', 'started_at']) as $project) {
            $to = match (true) {
                isset(self::MAP[$project->workflow_status]) => self::MAP[$project->workflow_status],
                $project->workflow_status === 'generation_failed' => (int) $project->failed_stage === 3 ? 'data_submitted' : 'submitted',
                $project->workflow_status === 'draft' && $project->status !== 'draft' => 'submitted', // started under the original flow
                default => null,
            };

            if ($to === null || $to === $project->workflow_status) {
                continue;
            }

            DB::table('projects')->where('id', $project->id)->update([
                'workflow_status' => $to,
                'workflow_changed_at' => $now,
                'submitted_at' => $project->started_at ?? $now,
                'failed_stage' => null,
            ]);
            DB::table('project_transitions')->insert([
                'project_id' => $project->id, 'from_status' => $project->workflow_status, 'to_status' => $to,
                'user_id' => null, 'note' => 'Moved to the service workflow (AI no longer writes theses)', 'created_at' => $now,
            ]);
        }

        DB::table('projects')->whereNull('submitted_at')->whereNotIn('workflow_status', ['draft'])->update(['submitted_at' => $now]);
        DB::table('project_documents')->where('kind', 'edited')->update(['kind' => 'deliverable']);
    }

    public function down(): void
    {
        DB::table('project_documents')->where('kind', 'deliverable')->update(['kind' => 'edited']);
        DB::table('projects')->whereIn('workflow_status', ['submitted', 'stage1_in_progress', 'needs_info', 'on_hold'])->update(['workflow_status' => 'stage1_awaiting_editing']);
        DB::table('projects')->whereIn('workflow_status', ['data_submitted', 'stage3_in_progress'])->update(['workflow_status' => 'stage3_awaiting_editing']);

        Schema::table('papers', fn (Blueprint $table) => $table->dropColumn('kind'));
        Schema::table('projects', fn (Blueprint $table) => $table->dropColumn(['title_source', 'institution', 'guidelines', 'writer_notes', 'resume_status', 'info_request', 'submitted_at']));
    }
};
