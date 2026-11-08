<?php

use App\Ai\Schemas;
use App\Services\Claude\ClaudeResponse;
use App\Services\Claude\CostCalculator;
use App\Services\Claude\PromptRepository;

it('estimates cost with cache reads and writes', function () {
    $calc = new CostCalculator(config('services.claude'));
    $response = new ClaudeResponse([['text' => 'x', 'citations' => []]], 'claude-opus-5-5', 'end_turn', 1_000_000, 100_000, 1_000_000, 0, 1_000_000);

    $p = config('services.claude.pricing.claude-opus-5-5');
    $expected = $p['input'] + $p['output'] / 10 + $p['input'] * 0.1 + $p['input'] * 2.0;

    expect($calc->cost($response, 'claude-opus-5-5'))->toEqualWithDelta($expected, 0.0001)
        ->and($calc->cost($response, 'claude-opus-5-5-20270101'))->toEqualWithDelta($expected, 0.0001);
});

it('loads every versioned prompt with its variables filled in', function () {
    $repo = new PromptRepository(resource_path('prompts'));

    foreach (glob(resource_path('prompts/*.md')) as $file) {
        $name = basename($file, '.md');
        if ($name === 'README') {
            continue;
        }
        preg_match_all('/\{\{\s*([a-z0-9_]+)\s*\}\}/i', file_get_contents($file), $m);
        $prompt = $repo->get($name, array_fill_keys($m[1], 'x'));
        expect($prompt->system)->not->toBeEmpty("{$name} has no system prompt")
            ->and($prompt->version)->toBeGreaterThan(0);
    }

    $filled = $repo->get('title_suggestions', ['degree' => 'MBA Research', 'discipline' => 'Education', 'topic' => 'mobile money', 'keywords' => 'trust', 'context' => 'Ghana', 'approach' => 'Quantitative', 'background' => '-', 'avoid' => '']);
    expect($filled->user)->toContain('Field of study: Education')->toContain('Broad topic or area of interest: mobile money')
        ->and($filled->id())->toStartWith('title_suggestions@v3');
});

it('declares strict JSON schemas for structured outputs', function () {
    $check = function (array $schema, string $path) use (&$check) {
        if (($schema['type'] ?? null) === 'object') {
            expect($schema['additionalProperties'] ?? null)->toBeFalse("{$path} allows extra properties");
            expect(array_keys($schema['properties']))->toEqualCanonicalizing($schema['required'], "{$path} has optional properties");
            foreach ($schema['properties'] as $key => $child) {
                $check($child, "{$path}.{$key}");
            }
        }
        if (($schema['type'] ?? null) === 'array') {
            $check($schema['items'], "{$path}[]");
        }
    };

    $check(Schemas::titleSuggestions(), 'titleSuggestions');
});
