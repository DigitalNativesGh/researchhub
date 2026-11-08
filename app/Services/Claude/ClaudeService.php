<?php

namespace App\Services\Claude;

use App\Models\AiCall;
use App\Models\User;
use Throwable;

/**
 * The single entry point to Claude. Since the AI no longer writes any part of a thesis, it serves exactly one
 * feature: title suggestions (App\Services\Titles\TitleSuggester).
 *
 * - Prompts come from versioned files in resources/prompts (PromptRepository).
 * - Output is structured (JSON schema).
 * - Every call is logged to ai_calls with tokens, cost and duration (Admin → AI Usage & Cost).
 */
class ClaudeService
{
    private ?int $userId = null;

    private ?string $label = null;

    public function __construct(
        private readonly ClaudeGateway $gateway,
        private readonly PromptRepository $prompts,
        private readonly CostCalculator $costs,
        private readonly array $config,
    ) {}

    /** Attribute the next call(s) to a user in ai_calls. */
    public function forUser(?User $user): static
    {
        $clone = clone $this;
        $clone->userId = $user?->id;

        return $clone;
    }

    /** A human-readable description of the next call(s), shown on the AI Usage page. */
    public function label(?string $label): static
    {
        $clone = clone $this;
        $clone->label = $label !== null ? mb_substr($label, 0, 190) : null;

        return $clone;
    }

    /** Run a prompt that must return JSON matching $schema (structured outputs). */
    public function structured(string $stage, string $prompt, array $vars, array $schema): array
    {
        $tpl = $this->prompts->get($prompt, $vars);
        $model = $this->config['model'];

        $params = [
            'model' => $model,
            'max_tokens' => min($tpl->maxTokens, (int) ($this->config['max_tokens'] ?? 4000)),
            'system' => [['type' => 'text', 'text' => $tpl->system]],
            'messages' => [['role' => 'user', 'content' => [['type' => 'text', 'text' => $tpl->user]]]],
            'output_config' => ['format' => ['type' => 'json_schema', 'schema' => $schema]],
        ];

        $started = hrtime(true);

        try {
            $response = $this->gateway->send($params, null, ['stage' => $stage, 'prompt' => $tpl->name, 'vars' => $vars, 'schema' => $schema]);
        } catch (Throwable $e) {
            $this->log($stage, $tpl, $model, null, $started, $e);

            throw $e instanceof ClaudeException ? $e : new ClaudeException($e->getMessage(), previous: $e);
        }

        $this->log($stage, $tpl, $response->model ?: $model, $response, $started);

        if ($response->wasTruncated()) {
            throw new ClaudeException("The {$stage} response was cut off before it finished.");
        }

        return $response->json();
    }

    private function log(string $stage, Prompt $tpl, string $model, ?ClaudeResponse $response, int $started, ?Throwable $error = null): void
    {
        AiCall::create([
            'project_id' => null,
            'user_id' => $this->userId,
            'stage' => $stage,
            'label' => $this->label,
            'prompt' => $tpl->id(),
            'model' => $model,
            'input_tokens' => $response?->inputTokens ?? 0,
            'output_tokens' => $response?->outputTokens ?? 0,
            'cached_tokens' => $response?->cacheReadTokens ?? 0,
            'cache_write_tokens' => $response?->cacheWriteTokens() ?? 0,
            'cost' => $response ? $this->costs->cost($response, $model) : 0,
            'duration_ms' => (int) ((hrtime(true) - $started) / 1_000_000),
            'status' => $error ? 'error' : 'success',
            'error' => $error ? mb_substr($error->getMessage(), 0, 2000) : null,
        ]);
    }
}
