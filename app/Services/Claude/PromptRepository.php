<?php

namespace App\Services\Claude;

use InvalidArgumentException;

/**
 * Loads versioned prompt files from resources/prompts/*.md.
 *
 * File format:
 *   ---
 *   version: 3
 *   model: default | light
 *   max_tokens: 8000
 *   ---
 *   # System
 *   ...
 *   # User
 *   ... {{ variable }} ...
 */
class PromptRepository
{
    private array $cache = [];

    public function __construct(private readonly string $path) {}

    public function get(string $name, array $vars = []): Prompt
    {
        $raw = $this->cache[$name] ??= $this->load($name);

        return new Prompt(
            name: $name,
            version: (int) ($raw['meta']['version'] ?? 1),
            model: $raw['meta']['model'] ?? 'default',
            maxTokens: (int) ($raw['meta']['max_tokens'] ?? 4096),
            system: $this->render($raw['system'], $vars, $name),
            user: $this->render($raw['user'], $vars, $name),
        );
    }

    private function load(string $name): array
    {
        if (! preg_match('/^[a-z0-9_]+$/', $name)) {
            throw new InvalidArgumentException("Invalid prompt name [$name].");
        }

        $file = $this->path.'/'.$name.'.md';

        if (! is_file($file)) {
            throw new InvalidArgumentException("Prompt file [$file] does not exist.");
        }

        $text = str_replace("\r\n", "\n", file_get_contents($file));
        $meta = [];

        if (preg_match('/^---\n(.*?)\n---\n/s', $text, $m)) {
            foreach (explode("\n", $m[1]) as $line) {
                if (str_contains($line, ':')) {
                    [$k, $v] = array_map('trim', explode(':', $line, 2));
                    $meta[$k] = $v;
                }
            }
            $text = substr($text, strlen($m[0]));
        }

        if (! preg_match('/^# System\s*\n(.*?)^# User\s*\n(.*)$/sm', $text, $parts)) {
            throw new InvalidArgumentException("Prompt [$name] must contain '# System' and '# User' sections.");
        }

        return ['meta' => $meta, 'system' => trim($parts[1]), 'user' => trim($parts[2])];
    }

    private function render(string $template, array $vars, string $name): string
    {
        return preg_replace_callback('/\{\{\s*([a-z0-9_]+)\s*\}\}/i', function ($m) use ($vars, $name) {
            if (! array_key_exists($m[1], $vars)) {
                throw new InvalidArgumentException("Prompt [$name] needs variable [{$m[1]}].");
            }

            $value = $vars[$m[1]];

            return is_array($value) ? json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : (string) $value;
        }, $template);
    }
}
