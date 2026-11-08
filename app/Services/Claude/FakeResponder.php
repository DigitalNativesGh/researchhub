<?php

namespace App\Services\Claude;

/**
 * Deterministic stand-in title suggestions, built from the researcher's inputs, so the title assistant works
 * without an API key (CLAUDE_FAKE=true) and in tests. They are clearly sample suggestions.
 */
class FakeResponder
{
    public function respond(array $params, array $context): ClaudeResponse
    {
        $vars = $context['vars'] ?? [];

        return match ($context['prompt'] ?? '') {
            'title_suggestions' => FakeGateway::json($this->titles($vars)),
            default => FakeGateway::text('Sample response.'),
        };
    }

    private function titles(array $vars): array
    {
        $topic = trim((string) ($vars['topic'] ?? 'the research topic'));
        $context = trim((string) ($vars['context'] ?? ''));
        $where = $context !== '' && $context !== '(not specified)' ? ' in '.$context : '';
        $keywords = array_values(array_filter(array_map('trim', explode(',', (string) ($vars['keywords'] ?? '')))));
        $focus = $keywords[0] ?? 'key factors';
        $round = (int) ($vars['round'] ?? 1);

        $patterns = [
            ['%s and %s%s: A Cross-Sectional Study', 'Measures how %2$s relates to %1$s%3$s using survey data.'],
            ['Lived Experiences of %s%s: Perspectives on %s', 'Explores how participants experience %1$s, focusing on %3$s.'],
            ['Determinants of %s%s: The Role of %s', 'Identifies which factors, especially %3$s, explain %1$s.'],
            ['%s%s: Challenges, Practices and the Influence of %s', 'Describes current practice and how %3$s shapes %1$s.'],
            ['Evaluating %s%s: Evidence on %s', 'Assesses outcomes of %1$s with attention to %3$s.'],
            ['Factors Influencing %s%s: A Mixed-Methods Study of %s', 'Combines a survey and interviews to examine %1$s and %3$s.'],
        ];

        $titles = [];
        for ($i = 0; $i < 5; $i++) {
            [$title, $why] = $patterns[($i + ($round - 1) * 2) % count($patterns)];
            $title = $i === 0 ? sprintf($title, ucfirst($topic), $focus, $where) : sprintf($title, ucfirst($topic), $where, $focus);
            $titles[] = ['title' => $title.($round > 1 ? " [sample set {$round}]" : ' [sample]'), 'explanation' => sprintf($why, lcfirst($topic), $where, $focus)];
        }

        return ['titles' => $titles];
    }
}
