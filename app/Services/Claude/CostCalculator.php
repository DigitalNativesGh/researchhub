<?php

namespace App\Services\Claude;

class CostCalculator
{
    public function __construct(private readonly array $config) {}

    /** Estimated USD cost of one response. */
    public function cost(ClaudeResponse $response, string $model): float
    {
        $prices = $this->pricesFor($model);
        $perToken = fn (float $perMillion) => $perMillion / 1_000_000;

        $input = $prices['input'];

        return round(
            $response->inputTokens * $perToken($input)
            + $response->cacheReadTokens * $perToken($input * ($this->config['cache_read_multiplier'] ?? 0.1))
            + $response->cacheWrite5mTokens * $perToken($input * ($this->config['cache_write_multiplier']['5m'] ?? 1.25))
            + $response->cacheWrite1hTokens * $perToken($input * ($this->config['cache_write_multiplier']['1h'] ?? 2.0))
            + $response->outputTokens * $perToken($prices['output']),
            6,
        );
    }

    private function pricesFor(string $model): array
    {
        $pricing = $this->config['pricing'] ?? [];

        if (isset($pricing[$model])) {
            return $pricing[$model];
        }

        // Dated or aliased model ids, e.g. claude-opus-5-5-20260101.
        foreach ($pricing as $prefix => $prices) {
            if (str_starts_with($model, $prefix)) {
                return $prices;
            }
        }

        foreach (['opus', 'sonnet', 'haiku'] as $family) {
            if (str_contains($model, $family)) {
                foreach ($pricing as $id => $prices) {
                    if (str_contains($id, $family)) {
                        return $prices;
                    }
                }
            }
        }

        return ['input' => 0.0, 'output' => 0.0];
    }
}
