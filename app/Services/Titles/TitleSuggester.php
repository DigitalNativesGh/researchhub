<?php

namespace App\Services\Titles;

use App\Ai\Schemas;
use App\Enums\DegreeLevel;
use App\Models\User;
use App\Services\Claude\ClaudeException;
use App\Services\Claude\ClaudeService;
use Illuminate\Support\Str;

/**
 * Suggests possible thesis titles: the only thing the platform uses AI for. Runs while the researcher waits (a
 * single short call on a fast model), so it does not depend on a queue worker. Calls are logged in ai_calls.
 */
class TitleSuggester
{
    public const APPROACHES = [
        'quantitative' => 'Quantitative',
        'qualitative' => 'Qualitative',
        'mixed' => 'Mixed methods',
        'not_sure' => 'Not sure yet',
    ];

    public function __construct(private readonly ClaudeService $claude) {}

    /**
     * @param  array{degree_level: string, discipline: string, topic: string, keywords?: string, context?: string, approach: string, background?: string}  $input
     * @param  list<string>  $avoid  titles already shown (for "suggest more")
     * @return list<array{title: string, explanation: string}>
     *
     * @throws TitleSuggestionFailed
     */
    public function suggest(User $user, array $input, array $avoid = []): array
    {
        $level = DegreeLevel::tryFrom($input['degree_level']) ?? DegreeLevel::Undergraduate;
        $blank = fn (?string $v) => trim((string) $v) === '' ? '(not specified)' : trim((string) $v);

        try {
            $data = $this->claude->forUser($user)->label('Title suggestions: '.Str::limit($input['topic'], 80))->structured('title_suggestions', 'title_suggestions', [
                'degree' => $level->label().' ('.$level->description().' Typical length '.$level->typicalRange().'.)',
                'discipline' => $input['discipline'],
                'topic' => trim($input['topic']),
                'keywords' => $blank($input['keywords'] ?? null),
                'context' => $blank($input['context'] ?? null),
                'approach' => self::APPROACHES[$input['approach']] ?? 'Not sure yet',
                'background' => $blank(Str::limit((string) ($input['background'] ?? ''), 3000)),
                'avoid' => $avoid ? "Do not repeat or closely rephrase these titles, which were already suggested:\n- ".implode("\n- ", $avoid) : '',
                'round' => intdiv(count($avoid), 5) + 1,
            ], Schemas::titleSuggestions());
        } catch (ClaudeException $e) {
            report($e);

            throw new TitleSuggestionFailed($e->retryable
                ? 'Title suggestions are busy right now. Please try again in a minute, or type your own title.'
                : 'We could not generate title suggestions right now. You can type your own title instead.', previous: $e);
        }

        $titles = collect($data['titles'] ?? [])
            ->map(fn ($t) => ['title' => Str::limit(trim((string) ($t['title'] ?? '')), 300, ''), 'explanation' => Str::limit(trim((string) ($t['explanation'] ?? '')), 400)])
            ->filter(fn ($t) => $t['title'] !== '')
            ->unique(fn ($t) => mb_strtolower($t['title']))
            ->reject(fn ($t) => in_array(mb_strtolower($t['title']), array_map('mb_strtolower', $avoid), true))
            ->take(5)->values()->all();

        if ($titles === []) {
            throw new TitleSuggestionFailed('No suggestions came back this time. Please try again, or type your own title.');
        }

        return $titles;
    }
}
