<?php

namespace App\Ai;

/**
 * JSON schemas for Claude's structured outputs. Title suggestions are the only AI feature.
 */
final class Schemas
{
    public static function titleSuggestions(): array
    {
        return self::obj([
            'titles' => self::list(self::obj([
                'title' => self::str('A specific, researchable, academically phrased thesis title'),
                'explanation' => self::str('One sentence: what the study would investigate'),
            ])),
        ]);
    }

    private static function obj(array $properties): array
    {
        return [
            'type' => 'object',
            'properties' => $properties,
            'required' => array_keys($properties),
            'additionalProperties' => false,
        ];
    }

    private static function str(string $description = ''): array
    {
        return array_filter(['type' => 'string', 'description' => $description]);
    }

    private static function list(array $item): array
    {
        return ['type' => 'array', 'items' => $item];
    }
}
