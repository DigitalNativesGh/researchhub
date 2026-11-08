<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;

it('moves projects from the old AI workflow to the matching service status, and logs each move', function () {
    $user = User::factory()->create();
    $cases = [
        // [old workflow_status, old status, failed_stage, expected]
        ['draft', 'draft', null, 'draft'],
        ['draft', 'complete', null, 'submitted'],          // started under the original flow
        ['stage1_generating', 'drafting', null, 'submitted'],
        ['stage1_awaiting_editing', 'drafting', null, 'submitted'],
        ['stage1_in_editing', 'drafting', null, 'submitted'],
        ['generation_failed', 'drafting', 1, 'submitted'],
        ['stage1_delivered', 'drafting', null, 'stage1_delivered'],
        ['data_uploaded', 'drafting', null, 'data_submitted'],
        ['stage3_generating', 'drafting', null, 'data_submitted'],
        ['stage3_in_editing', 'drafting', null, 'data_submitted'],
        ['generation_failed', 'drafting', 3, 'data_submitted'],
        ['completed', 'complete', null, 'completed'],
    ];

    $ids = [];
    foreach ($cases as $i => [$workflow, $status, $failed]) {
        $ids[$i] = DB::table('projects')->insertGetId([
            'user_id' => $user->id, 'title' => "Project {$i}", 'discipline' => 'Education', 'degree_level' => 'mphil', 'citation_style' => 'apa7',
            'research_type' => 'quantitative', 'status' => $status, 'phase' => 1, 'workflow_status' => $workflow, 'failed_stage' => $failed,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }
    DB::table('project_documents')->insert([
        'project_id' => $ids[6], 'stage' => 1, 'type' => 'chapters_1_3', 'slot' => 'chapters_1_3', 'label' => 'Chapters 1–3', 'kind' => 'edited',
        'version' => 1, 'format' => 'docx', 'file_path' => 'x.docx', 'original_name' => 'x.docx', 'file_size' => 1, 'sha256' => str_repeat('a', 64), 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('project_documents')->insert([
        'project_id' => $ids[6], 'stage' => 1, 'type' => 'chapters_1_3', 'slot' => 'chapters_1_3', 'label' => 'Draft', 'kind' => 'ai_draft',
        'version' => 1, 'format' => 'docx', 'file_path' => 'y.docx', 'original_name' => 'y.docx', 'file_size' => 1, 'sha256' => str_repeat('a', 64), 'created_at' => now(), 'updated_at' => now(),
    ]);

    (require database_path('migrations/2026_11_04_000100_switch_to_service_workflow.php'))->moveProjects();

    foreach ($cases as $i => [$from, , , $expected]) {
        $row = DB::table('projects')->find($ids[$i]);
        expect($row->workflow_status)->toBe($expected, "{$from} → {$expected}");

        $logged = DB::table('project_transitions')->where('project_id', $ids[$i])->get();
        if ($expected === $from) {
            expect($logged)->toHaveCount(0)->and($row->submitted_at === null)->toBe($from === 'draft');
        } else {
            expect($logged)->toHaveCount(1)->and($logged[0]->from_status)->toBe($from)->and($logged[0]->note)->toContain('service workflow')
                ->and($row->submitted_at)->not->toBeNull();
        }
    }

    expect(DB::table('project_documents')->pluck('kind')->sort()->values()->all())->toBe(['ai_draft', 'deliverable']); // nothing deleted
});
