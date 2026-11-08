<?php

use App\Models\AiCall;
use App\Models\Project;
use App\Models\User;
use App\Services\Claude\ClaudeException;
use App\Services\Claude\FakeGateway;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
    paidFor($this->user, 'mba');
    $this->post(route('projects.new.store'), ['degree_level' => 'mba']);
    $this->claude = fakeClaude();
});

function suggestInput(array $overrides = []): array
{
    return $overrides + [
        'discipline' => 'Business & Economics', 'topic' => 'mobile money use by small businesses', 'keywords' => 'trust, transaction costs',
        'context' => 'market traders in Kumasi, Ghana', 'approach' => 'not_sure', 'background' => 'Traders increasingly accept mobile money.',
    ];
}

it('offers "I have a title" or "Help me find a title" on the details step', function () {
    $this->get(route('projects.create'))->assertOk()->assertSee('I have a title')->assertSee('Help me find a title')->assertSee(route('title-assistant'));
});

it('suggests about five titles with explanations, using the degree level and every input', function () {
    $this->get(route('title-assistant'))->assertOk()->assertSee('MBA Research')->assertSee('Not sure yet')
        ->assertSee('Confirm your title with your supervisor')->assertSee('cannot guarantee that a suggested title has not been used before');

    $this->post(route('title-assistant.suggest'), suggestInput())->assertRedirect();

    $this->claude->assertSent('title_suggestions', function ($params, $context) {
        $text = json_encode($params, JSON_UNESCAPED_UNICODE);

        return str_contains($text, 'MBA Research') && str_contains($text, 'mobile money use by small businesses')
            && str_contains($text, 'trust, transaction costs') && str_contains($text, 'Kumasi, Ghana')
            && str_contains($text, 'Not sure yet') && str_contains($text, 'Traders increasingly accept mobile money')
            && $params['output_config']['format']['type'] === 'json_schema';
    });

    $suggestions = session('title_assistant.suggestions');
    expect($suggestions)->toHaveCount(5)
        ->and(collect($suggestions)->every(fn ($s) => $s['title'] !== '' && $s['explanation'] !== ''))->toBeTrue();
    $this->get(route('title-assistant'))->assertSee($suggestions[0]['title'])->assertSee($suggestions[0]['explanation'])->assertSee('Use this title');
});

it('logs every suggestion request with its cost', function () {
    $this->post(route('title-assistant.suggest'), suggestInput());

    $call = AiCall::sole();
    expect($call->stage)->toBe('title_suggestions')->and($call->user_id)->toBe($this->user->id)
        ->and($call->status)->toBe('success')->and($call->prompt)->toStartWith('title_suggestions@v');
});

it('suggests more without repeating titles already shown', function () {
    $this->post(route('title-assistant.suggest'), suggestInput());
    $first = array_column(session('title_assistant.suggestions'), 'title');

    $this->post(route('title-assistant.more'))->assertRedirect();

    $this->claude->assertSent('title_suggestions', fn ($p) => str_contains(json_encode($p, JSON_UNESCAPED_UNICODE), 'Do not repeat') && str_contains(json_encode($p, JSON_UNESCAPED_UNICODE), $first[0]));
    $all = array_column(session('title_assistant.suggestions'), 'title');
    expect($all)->toHaveCount(10)->and(array_unique($all))->toHaveCount(10);
});

it('drops repeated titles even if the AI sends them again', function () {
    $this->post(route('title-assistant.suggest'), suggestInput());
    $first = session('title_assistant.suggestions');
    $this->claude->push('title_suggestions', FakeGateway::json(['titles' => [$first[0], ['title' => 'A genuinely new title about mobile money', 'explanation' => 'New angle.']]]));

    $this->post(route('title-assistant.more'));

    expect(array_column(session('title_assistant.suggestions'), 'title'))->toHaveCount(6)->toContain('A genuinely new title about mobile money');
});

it('carries a chosen title into the details step and records where it came from', function (string $edit, string $source) {
    $this->post(route('title-assistant.suggest'), suggestInput());
    $chosen = session('title_assistant.suggestions')[2]['title'];

    $this->post(route('title-assistant.choose'), ['choice' => 2])->assertRedirect(route('projects.create'));
    $this->get(route('projects.create'))->assertSee($chosen)->assertSee('From the title suggestions');

    $title = match ($edit) {
        'as is' => $chosen,
        'edited' => str_replace(' [sample]', ' in Ghana [sample]', $chosen),
        'replaced' => 'A completely different title written by the researcher',
    };
    $this->post(route('projects.store'), ['title' => $title, 'discipline' => 'Business & Economics', 'degree_level' => 'mba', 'research_type' => 'mixed', 'citation_style' => 'apa7'])->assertRedirect();

    $project = Project::sole();
    expect($project->title)->toBe($title)->and($project->title_source)->toBe($source);
})->with([
    'used as suggested' => ['as is', 'suggested'],
    'edited' => ['edited', 'suggested_edited'],
    'replaced with their own' => ['replaced', 'own'],
]);

it('saves a title the researcher typed as their own', function () {
    $this->post(route('projects.store'), ['title' => 'My own carefully chosen thesis title', 'discipline' => 'Education', 'degree_level' => 'mba', 'research_type' => 'quantitative', 'citation_style' => 'apa7']);

    expect(Project::sole()->title_source)->toBe('own');
    $this->claude->assertNothingSent();
});

it('handles AI errors gracefully: the researcher can still type their own title', function (bool $retryable, string $message) {
    $this->claude->push('title_suggestions', fn () => throw new ClaudeException('upstream problem', retryable: $retryable));

    $this->post(route('title-assistant.suggest'), suggestInput())->assertRedirect(route('title-assistant'))->assertSessionHas('error', fn ($m) => str_contains($m, $message));

    $this->get(route('title-assistant'))->assertOk()->assertSee('mobile money use by small businesses') // inputs kept
        ->assertSee("I'll write my own title", false);
    expect(AiCall::sole()->status)->toBe('error');
})->with([
    'overloaded' => [true, 'busy right now'],
    'other failure' => [false, 'type your own title'],
]);

it('treats an empty or malformed reply as a friendly failure', function () {
    $this->claude->push('title_suggestions', FakeGateway::json(['titles' => []]));

    $this->post(route('title-assistant.suggest'), suggestInput())->assertSessionHas('error', fn ($m) => str_contains($m, 'type your own title'));
});

it('rate-limits suggestion requests per researcher', function () {
    config(['researchhub.rate_limits.title_suggestions_per_hour' => 3]);

    foreach (range(1, 3) as $i) {
        $this->post(route('title-assistant.suggest'), suggestInput())->assertSessionMissing('error');
    }
    $this->post(route('title-assistant.suggest'), suggestInput())->assertRedirect(route('title-assistant'))->assertSessionHas('error', fn ($m) => str_contains($m, 'a lot of suggestions'));

    expect($this->claude->count('title_suggestions'))->toBe(3);

    // Another researcher is not affected.
    $other = User::factory()->create();
    paidFor($other, 'mba');
    $this->actingAs($other)->withSession(['wizard' => ['degree_level' => 'mba']])->post(route('title-assistant.suggest'), suggestInput())->assertSessionMissing('error');
});

it('validates the inputs and needs the research type paid for first', function () {
    $this->post(route('title-assistant.suggest'), suggestInput(['topic' => '', 'approach' => 'astrology']))->assertSessionHasErrors(['topic', 'approach']);

    $unpaid = User::factory()->create();
    $this->actingAs($unpaid)->withSession(['wizard' => ['degree_level' => 'phd']])->get(route('title-assistant'))->assertRedirect(route('projects.payment'));
    $this->actingAs($unpaid)->withSession(['wizard' => []])->post(route('title-assistant.suggest'), suggestInput())->assertRedirect(route('projects.new'));
});
