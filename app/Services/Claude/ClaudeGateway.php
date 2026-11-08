<?php

namespace App\Services\Claude;

/**
 * Transport between the app and the Anthropic API. The real implementation uses the official SDK;
 * tests (and CLAUDE_FAKE=true) use FakeGateway so no real API calls are made.
 */
interface ClaudeGateway
{
    /**
     * Send a Messages API request. $params use the API's wire format (snake_case keys).
     * When $onText is given the response is streamed and $onText receives each text delta.
     * $context describes the call (stage, prompt name, template variables); the real gateway ignores it,
     * the fake uses it to build coherent responses.
     */
    public function send(array $params, ?callable $onText = null, array $context = []): ClaudeResponse;

    /** Delete a file the retired AI workflow uploaded with the Files API (used when a project is deleted). */
    public function deleteFile(string $fileId): void;
}
