<?php

namespace App\Services\Claude;

use Closure;
use PHPUnit\Framework\Assert;

/**
 * In-memory ClaudeGateway for tests and offline demos (CLAUDE_FAKE=true). Responses can be queued per
 * prompt name; otherwise FakeResponder builds a coherent default response from the request context.
 */
class FakeGateway implements ClaudeGateway
{
    /** @var list<array{params: array, context: array}> */
    public array $sent = [];

    /** @var array<string, list<ClaudeResponse|array|string|Closure>> */
    private array $queue = [];

    /** @var list<string> Files API ids deleted (copies left by the retired AI workflow). */
    public array $deleted = [];

    public function __construct(private readonly FakeResponder $responder = new FakeResponder) {}

    /** Queue a response for the next call of $prompt: a ClaudeResponse, JSON array, text, or closure(params, context). */
    public function push(string $prompt, ClaudeResponse|array|string|Closure $response): static
    {
        $this->queue[$prompt][] = $response;

        return $this;
    }

    public function send(array $params, ?callable $onText = null, array $context = []): ClaudeResponse
    {
        $this->sent[] = ['params' => $params, 'context' => $context];
        $prompt = $context['prompt'] ?? '';

        $response = isset($this->queue[$prompt]) && $this->queue[$prompt] !== []
            ? array_shift($this->queue[$prompt])
            : fn ($p, $c) => $this->responder->respond($p, $c);

        if ($response instanceof Closure) {
            $response = $response($params, $context);
        }

        $response = match (true) {
            $response instanceof ClaudeResponse => $response,
            is_array($response) => self::json($response),
            default => self::text((string) $response),
        };

        if ($onText) {
            foreach ($response->blocks as $block) {
                $onText($block['text']);
            }
        }

        return $response;
    }

    public function deleteFile(string $fileId): void
    {
        $this->deleted[] = $fileId;
    }

    public static function text(string $text, int $in = 1200, int $out = 400): ClaudeResponse
    {
        return new ClaudeResponse([['text' => $text, 'citations' => []]], 'claude-opus-5-5', 'end_turn', $in, $out, 900, 300, 0);
    }

    public static function json(array $data): ClaudeResponse
    {
        return self::text(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    public function assertSent(string $prompt, ?callable $callback = null, ?int $times = null): void
    {
        $matching = array_filter($this->sent, fn ($s) => ($s['context']['prompt'] ?? null) === $prompt && (! $callback || $callback($s['params'], $s['context'])));

        Assert::assertNotEmpty($matching, "No request was sent for prompt [$prompt].");

        if ($times !== null) {
            Assert::assertCount($times, $matching, "Prompt [$prompt] was sent ".count($matching)." times, expected {$times}.");
        }
    }

    public function assertNothingSent(): void
    {
        Assert::assertSame([], $this->sent, 'Requests were sent to Claude.');
    }

    public function count(string $prompt): int
    {
        return count(array_filter($this->sent, fn ($s) => ($s['context']['prompt'] ?? null) === $prompt));
    }
}
