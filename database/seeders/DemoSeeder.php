<?php

namespace Database\Seeders;

use App\Enums\CitationStyle;
use App\Enums\DatasetStatus;
use App\Enums\DegreeLevel;
use App\Enums\DocumentType;
use App\Enums\ResearchType;
use App\Models\Dataset;
use App\Models\Paper;
use App\Models\Project;
use App\Models\User;
use App\Services\Workflow\DocumentStore;
use App\Services\Workflow\ProjectWorkflow;
use App\Support\SamplePdf;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Settings;

/**
 * Demo accounts and a sample project at every status, for trying the service end to end. No AI is used: the
 * "delivered documents" are placeholder Word files standing in for what the team writes, and the papers, data and
 * transcripts are invented and labelled as such.
 *
 *   php artisan db:seed --class=DemoSeeder
 *   → researcher: demo@researchhub.com / password
 *   → admin:      admin@researchhub.com / password (work queue at /admin/queue)
 */
class DemoSeeder extends Seeder
{
    private const PAPERS = [
        ['Owusu_2021', 'Determinants of Mobile Learning Adoption among University Students', 'Perceived usefulness and perceived ease of use were the strongest predictors of the intention to use mobile learning among 412 undergraduates. Facilitating conditions such as campus Wi-Fi had a smaller direct effect.'],
        ['Nkrumah_2022', 'Smartphone Use, Self-Regulated Learning and Grade Point Average', 'Students who used their phones for structured study reported higher self-regulated learning scores, which partly explained higher grade point averages. Recreational use showed no relationship with grades.'],
        ['Bello_2023', 'Connectivity Costs and the Digital Divide in Public Universities', 'The cost of mobile data was the main barrier to regular mobile learning. Students from lower-income households reported fewer weekly hours on learning platforms.'],
        ['Adjei_2020', 'Perceived Usefulness and Ease of Use in Higher-Education M-Learning', 'A structural model confirmed that ease of use influences usefulness, and both shape attitudes towards mobile learning. Lecturer support moderated the effect of usefulness on use.'],
        ['Quaye_2019', 'Lecturer Support and Student Engagement with Learning Platforms', 'Interviews with 28 lecturers showed that timely feedback through mobile platforms increased student participation, but workload limited how often lecturers could respond.'],
    ];

    private User $admin;

    private ProjectWorkflow $workflow;

    private DocumentStore $store;

    public function run(): void
    {
        config(['queue.default' => 'sync']);

        $user = User::updateOrCreate(['email' => 'demo@researchhub.com'], [
            'name' => 'Ustarz Baba',
            'password' => Hash::make('password'),
            'role' => 'student',
            'timezone' => 'Africa/Accra',
            'institution' => 'Demo University',
            'field_of_study' => 'Education',
            'bio' => 'Demo account. Every project here uses invented sample files.',
            'email_alerts' => false,
        ]);
        $user->forceFill(['email_verified_at' => now()])->save();

        foreach ($user->projects as $old) {
            $old->purgeFiles();
            $old->delete();
        }

        $this->admin = User::updateOrCreate(['email' => 'admin@researchhub.com'], [
            'name' => 'Platform Admin',
            'password' => Hash::make('password'),
            'role' => 'academic',
            'email_alerts' => false,
        ]);
        $this->admin->forceFill(['email_verified_at' => now(), 'is_admin' => true])->save();
        $this->workflow = app(ProjectWorkflow::class);
        $this->store = app(DocumentStore::class);

        $this->stages($user);

        $this->command?->info('Researcher: demo@researchhub.com / password');
        $this->command?->info('Admin:      admin@researchhub.com / password (work queue at /admin/queue)');
        $this->command?->table(['Project', 'Status'], $user->projects()->orderBy('id')->get()->map(fn ($p) => [$p->reference().' '.Str::limit($p->title, 50), $p->workflow_status->label()])->all());
    }

    /** One project at each status, oldest first, so the work queue shows real waiting times. */
    private function stages(User $user): void
    {
        $draft = $this->project($user, 'Lecturer Perspectives on Feedback through Mobile Learning Platforms', DegreeLevel::Undergraduate, ResearchType::Qualitative);
        $this->addPapers($draft, 2);

        $submitted = $this->submitted($this->project($user, 'Connectivity Costs and Weekly Mobile Learning Hours among Undergraduates in Ghana', DegreeLevel::Mphil, ResearchType::Quantitative, deadline: 10, titleSource: 'suggested'));
        $submitted->forceFill(['workflow_changed_at' => now()->subDays(3)])->save();

        $inProgress = $this->submitted($this->project($user, 'Mobile Money Adoption among Small Traders in Kumasi: A Mixed-Methods Study', DegreeLevel::Mba, ResearchType::Mixed, titleSource: 'suggested_edited'));
        $this->workflow->markInProgress($inProgress, $this->admin, 'Esi Owusu', now()->addDays(6)->toDateString(), 'Check the research questions against the guidelines.');

        $delivered = $this->delivered($this->submitted($this->project($user, 'Mobile Learning Adoption and Academic Performance among Undergraduate Students in Public Universities', DegreeLevel::Mphil, ResearchType::Quantitative)));
        $this->writeSampleData($delivered);

        $dataIn = $this->withData($this->delivered($this->submitted($this->project($user, 'Smartphone Use, Self-Regulated Learning and Grade Point Average', DegreeLevel::Phd, ResearchType::Quantitative, deadline: 45))));

        $stage3 = $this->withData($this->delivered($this->submitted($this->project($user, 'Student Experiences of Data Costs in Mobile Learning: An Interview Study', DegreeLevel::Mphil, ResearchType::Qualitative))));
        $this->workflow->markInProgress($stage3, $this->admin, 'Kofi Asante', now()->addDays(10)->toDateString(), null);

        $completed = $this->withData($this->delivered($this->submitted($this->project($user, 'Digital Literacy and Online Assessment Performance among Nursing Students', DegreeLevel::Undergraduate, ResearchType::Quantitative))));
        $this->deliver($completed, 3, [[DocumentType::Thesis, 'Complete thesis', null]]);

        $needsInfo = $this->submitted($this->project($user, 'Teacher Workload and Retention in Rural Junior High Schools', DegreeLevel::Mphil, ResearchType::Mixed));
        $this->workflow->requestInfo($needsInfo, $this->admin, 'Could you upload your department’s thesis template, and confirm whether your supervisor wants a separate chapter for the conceptual framework?');

        $onHold = $this->submitted($this->project($user, 'Microfinance and Women-Owned Enterprises in Northern Ghana', DegreeLevel::Phd, ResearchType::Qualitative));
        $this->workflow->hold($onHold, $this->admin, 'Waiting for the researcher to confirm the scope by phone.');
    }

    private function project(User $user, string $title, DegreeLevel $level, ResearchType $type, ?int $deadline = 120, string $titleSource = 'own'): Project
    {
        $project = $user->projects()->create([
            'title' => $title,
            'title_source' => $titleSource,
            'description' => 'Demo project: '.$title.'.',
            'discipline' => 'Education',
            'institution' => 'Demo University',
            'degree_level' => $level,
            'research_type' => $type,
            'citation_style' => CitationStyle::Apa7,
            'guidelines' => 'Use the department template; chapters numbered 1–5; maximum '.number_format($level->typicalWords()).' words.',
            'writer_notes' => 'Sample notes for the team.',
            'deadline' => $deadline ? now()->addDays($deadline)->toDateString() : null,
            'status' => 'draft',
        ]);
        $project->record('created', 'Project created', null, 'note_add');

        return $project;
    }

    private function submitted(Project $project): Project
    {
        $this->addPapers($project);
        $this->workflow->submit($project, $project->user);

        return $project->fresh();
    }

    private function delivered(Project $project): Project
    {
        $documents = [[DocumentType::Chapters, 'Chapters 1–3', null]];
        foreach (match ($project->research_type) {
            ResearchType::Quantitative => ['Questionnaire'],
            ResearchType::Qualitative => ['Interview guide'],
            ResearchType::Mixed => ['Questionnaire', 'Interview guide'],
        } as $label) {
            $documents[] = [DocumentType::Instrument, $label, Str::slug($label, '_')];
        }
        $this->deliver($project, 1, $documents);

        return $project->fresh();
    }

    /** Stand-in for the team: upload placeholder Word documents and deliver them. */
    private function deliver(Project $project, int $stage, array $documents): void
    {
        foreach ($documents as [$type, $label, $variant]) {
            $this->store->storeDeliverable($project, $type, $label, $this->sampleDocx($project, $label), $this->admin, $variant);
        }
        $this->workflow->deliver($project->fresh(), $stage, $this->admin);
    }

    private function withData(Project $project): Project
    {
        if ($project->needsQuantitativeData()) {
            $this->dataset($project, 'survey_responses.csv', $this->responsesCsv());
        }
        if ($project->needsQualitativeData()) {
            foreach ([1, 2, 3] as $n) {
                $this->dataset($project, "interview_P0{$n}.txt", $this->transcript($n));
            }
        }
        $this->workflow->submitData($project->fresh(), $project->user);

        return $project->fresh();
    }

    private function dataset(Project $project, string $name, string $contents): void
    {
        $path = "datasets/{$project->id}/".Str::uuid().'.'.pathinfo($name, PATHINFO_EXTENSION);
        Storage::disk(config('researchhub.disk'))->put($path, $contents);
        $project->datasets()->create([
            'kind' => str_ends_with($name, '.csv') ? Dataset::KIND_QUANTITATIVE : Dataset::KIND_TRANSCRIPT,
            'format' => pathinfo($name, PATHINFO_EXTENSION),
            'original_name' => $name,
            'file_path' => $path,
            'file_size' => strlen($contents),
            'status' => DatasetStatus::Valid,
        ]);
    }

    private function addPapers(Project $project, int $count = 5): void
    {
        $disk = Storage::disk(config('researchhub.disk'));

        foreach (array_slice(self::PAPERS, 0, $count) as [$prefix, $title, $abstract]) {
            $pdf = SamplePdf::make($title, [
                "SAMPLE DOCUMENT - invented for the ResearchHub demo; not a real publication.\n\nAbstract\n{$abstract}",
                "Method\nA cross-sectional survey design was used.\n\nFindings\n{$abstract}",
            ]);
            $path = "papers/{$project->id}/".Str::uuid().'.pdf';
            $disk->put($path, $pdf);

            $project->papers()->create([
                'kind' => Paper::KIND_SOURCE,
                'original_name' => $prefix.'_'.Str::slug($title).'.pdf',
                'file_path' => $path,
                'file_size' => strlen($pdf),
                'sha256' => hash('sha256', $pdf),
                'parse_status' => 'ready',
            ]);
        }
    }

    /** A clearly marked placeholder for a document the team writes. */
    private function sampleDocx(Project $project, string $label): UploadedFile
    {
        Settings::setOutputEscapingEnabled(true);
        $word = new PhpWord;
        $word->addTitleStyle(1, ['bold' => true, 'size' => 16]);
        $section = $word->addSection();
        $section->addTitle($label, 1);
        $section->addText($project->title);
        $section->addText('SAMPLE DOCUMENT for the ResearchHub demo. In a real project this is the document written by the team.');

        $path = tempnam(sys_get_temp_dir(), 'rh-demo').'.docx';
        IOFactory::createWriter($word, 'Word2007')->save($path);

        return new UploadedFile($path, Str::slug($label).'.docx', null, null, true);
    }

    /** A ready-made file for trying the data upload on the "ready for your data" project. */
    private function writeSampleData(Project $project): void
    {
        $path = storage_path("app/demo/{$project->reference()}_survey_responses.csv");
        @mkdir(dirname($path), 0775, true);
        file_put_contents($path, $this->responsesCsv());
        $this->command?->info("Sample data for {$project->reference()}: {$path}");
    }

    private function responsesCsv(): string
    {
        mt_srand(42);
        $lines = ['ID,Q1,Q2,Q3,Q4,Q5,Q6,Q7,Q8,AGE,GENDER'];
        for ($r = 1; $r <= 120; $r++) {
            $trait = mt_rand(-100, 100) / 100;
            $items = array_map(fn () => max(1, min(5, (int) round(3.4 + 1.1 * $trait + mt_rand(-80, 80) / 100))), range(1, 8));
            $lines[] = implode(',', array_merge([$r], $items, [mt_rand(18, 30), mt_rand(0, 1) ? 'Female' : 'Male']));
        }

        return implode("\n", $lines)."\n";
    }

    private function transcript(int $n): string
    {
        $lines = [
            1 => 'The cost of mobile data is the biggest problem for me, so I download lecture videos only when I am on campus Wi-Fi.',
            2 => 'When my lecturer answers questions on the class group the same day, I feel more confident to keep studying on my phone.',
            3 => 'I share a phone with my sister, which means I can only study at night when she is not using it.',
        ];

        return "SAMPLE TRANSCRIPT - invented for the ResearchHub demo.\n\nInterviewer: How do you use your phone for your studies?\nParticipant P0{$n}: {$lines[$n]} "
            ."Most of my classmates face the same situation, especially in the first year of the programme.\n";
    }
}
