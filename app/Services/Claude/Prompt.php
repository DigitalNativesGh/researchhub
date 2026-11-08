<?php

namespace App\Services\Claude;

final class Prompt
{
    public function __construct(
        public readonly string $name,
        public readonly int $version,
        public readonly string $model,
        public readonly int $maxTokens,
        public readonly string $system,
        public readonly string $user,
    ) {}

    public function id(): string
    {
        return $this->name.'@v'.$this->version;
    }
}
