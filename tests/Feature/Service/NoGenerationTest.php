<?php

use App\Models\AiCall;
use App\Models\User;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;

it('has removed every thesis-writing class', function (string $class) {
    expect(class_exists($class))->toBeFalse($class);
})->with([
    'App\Services\Pipeline', 'App\Jobs\WriteSection', 'App\Jobs\GenerateStage', 'App\Jobs\SummarizePaper', 'App\Jobs\BuildLiteratureMatrix',
    'App\Jobs\GenerateInstrument', 'App\Jobs\RunAnalysis', 'App\Jobs\ExportThesis', 'App\Services\Thesis\SectionWriter',
    'App\Models\Chapter', 'App\Models\Section', 'App\Models\SectionVersion', 'App\Models\Citation', 'App\Models\Instrument',
    'App\Models\Analysis', 'App\Models\Export', 'App\Models\PaperSummary', 'App\Models\LiteratureMatrix', 'App\Models\Framework',
    'App\Notifications\Admin\DraftReadyForEditing', 'App\Notifications\Admin\GenerationFailed',
]);

it('leaves only the title suggestion prompt', function () {
    $prompts = collect(File::files(resource_path('prompts')))->map->getFilename()->sort()->values()->all();

    expect($prompts)->toBe(['README.md', 'title_suggestions.md']);
});

it('keeps no job that calls Claude', function () {
    foreach (File::allFiles(app_path('Jobs')) as $file) {
        expect($file->getContents())->not->toContain('ClaudeService', $file->getFilename());
    }
});

it('calls Claude only from the title suggester', function () {
    $callers = collect(File::allFiles(app_path()))
        ->filter(fn ($f) => str_contains($f->getContents(), 'ClaudeService') && ! str_starts_with($f->getRelativePathname(), 'Services/Claude/'))
        ->map->getRelativePathname()->values()->all();

    expect($callers)->toEqualCanonicalizing(['Services/Titles/TitleSuggester.php', 'Providers/AppServiceProvider.php']);
});

it('exposes no route that generates thesis content', function () {
    $uris = collect(Route::getRoutes())->map(fn ($r) => $r->uri().' '.$r->getName())
        ->reject(fn ($r) => in_array($r, ['admin/payments/export admin.payments.export', 'admin/usage/export admin.usage.export'], true)) // CSV reports
        ->implode("\n");

    expect($uris)->not->toMatch('/generat|draft|write|section|chapter|analysis|export|instrument|regenerat|retry-stage/i');
});

it('sends nothing to Claude during a full project, end to end', function () {
    $fake = fakeClaude();

    projectAt('completed');

    $fake->assertNothingSent();
    expect(AiCall::count())->toBe(0);
});

it('uses AI for title suggestions only', function () {
    $fake = fakeClaude();
    $user = User::factory()->create();
    paidFor($user);
    $this->actingAs($user)->post(route('projects.new.store'), ['degree_level' => 'mphil']);
    $this->post(route('title-assistant.suggest'), ['discipline' => 'Education', 'topic' => 'Mobile learning', 'approach' => 'not_sure']);
    projectAt('completed', $user);

    expect(AiCall::pluck('stage')->unique()->values()->all())->toBe(['title_suggestions'])
        ->and($fake->count('title_suggestions'))->toBe(1)
        ->and(count($fake->sent))->toBe(1);
});
