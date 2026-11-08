<?php

namespace App\Services\Claude;

/**
 * Normalised Messages API response, independent of the SDK's classes.
 */
final class ClaudeResponse
{
    /**
     * @param  list<array{text:string, citations:list<array{document_index:?int, file_id:?string, document_title:?string, cited_text:string, start_page:?int, end_page:?int, type:string}>}>  $blocks
     */
    public function __construct(
        public readonly array $blocks,
        public readonly string $model,
        public readonly ?string $stopReason,
        public readonly int $inputTokens,
        public readonly int $outputTokens,
        public readonly int $cacheReadTokens = 0,
        public readonly int $cacheWrite5mTokens = 0,
        public readonly int $cacheWrite1hTokens = 0,
    ) {}

    public function text(): string
    {
        return implode('', array_column($this->blocks, 'text'));
    }

    /** Decode a structured-output response. */
    public function json(): array
    {
        $text = trim($this->text());

        // Structured outputs return bare JSON; tolerate a fenced block just in case.
        if (str_starts_with($text, '```')) {
            $text = preg_replace('/^```(?:json)?\s*|\s*```$/', '', $text);
        }

        $data = json_decode($text, true);

        if (! is_array($data)) {
            throw new ClaudeException('Claude returned output that is not valid JSON: '.mb_substr($text, 0, 200), retryable: true);
        }

        return $data;
    }

    public function wasTruncated(): bool
    {
        return $this->stopReason === 'max_tokens';
    }

    public function cacheWriteTokens(): int
    {
        return $this->cacheWrite5mTokens + $this->cacheWrite1hTokens;
    }

    /** Text blocks paired with their citations (citations may be empty). */
    public function hasCitations(): bool
    {
        foreach ($this->blocks as $block) {
            if ($block['citations'] !== []) {
                return true;
            }
        }

        return false;
    }
}
