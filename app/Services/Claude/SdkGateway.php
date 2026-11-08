<?php

namespace App\Services\Claude;

use Anthropic\Client;
use Anthropic\Core\Exceptions\APIConnectionException;
use Anthropic\Core\Exceptions\APIStatusException;
use Anthropic\Core\Exceptions\APITimeoutException;
use Anthropic\Core\Exceptions\RateLimitException;
use Anthropic\Lib\Streaming\MessageAccumulator;
use Anthropic\Messages\CitationPageLocation;
use Anthropic\Messages\Message;
use Anthropic\Messages\RawContentBlockDeltaEvent;
use Anthropic\Messages\TextBlock;
use Anthropic\Messages\TextDelta;
use GuzzleHttp\Client as GuzzleClient;
use Throwable;

/**
 * ClaudeGateway backed by the official anthropic-ai/sdk package.
 */
class SdkGateway implements ClaudeGateway
{
    private ?Client $client = null;

    public function __construct(private readonly array $config) {}

    public function send(array $params, ?callable $onText = null, array $context = []): ClaudeResponse
    {
        $args = $this->namedArguments($params);

        try {
            if ($onText === null) {
                return $this->normalise($this->client()->messages->create(...$args));
            }

            $stream = $this->client()->messages->createStream(...$args);
            $accumulator = MessageAccumulator::forMessages();

            foreach ($stream as $event) {
                $accumulator->accumulate($event);

                if ($event instanceof RawContentBlockDeltaEvent && $event->delta instanceof TextDelta) {
                    $onText($event->delta->text);
                }
            }

            return $this->normalise($accumulator->message());
        } catch (Throwable $e) {
            throw $this->translate($e);
        }
    }

    public function deleteFile(string $fileId): void
    {
        try {
            $this->client()->files->delete($fileId);
        } catch (Throwable $e) {
            throw $this->translate($e);
        }
    }

    private function client(): Client
    {
        if ($this->client) {
            return $this->client;
        }

        if (blank($this->config['api_key'] ?? null)) {
            throw new ClaudeException('ANTHROPIC_API_KEY is not set. Add it to your .env file.');
        }

        $headers = [];
        if ($beta = trim((string) ($this->config['beta_headers'] ?? ''))) {
            $headers['anthropic-beta'] = $beta;
        }

        $timeout = (float) ($this->config['timeout'] ?? 600);

        $options = [];
        if (! empty($this->config['sdk_middleware'])) {
            // Tests pass PSR middleware here to exercise the real SDK without the network.
            $options['middleware'] = $this->config['sdk_middleware'];
        }

        return $this->client = new Client(
            apiKey: $this->config['api_key'],
            baseUrl: $this->config['base_url'] ?: null,
            requestOptions: $options + [
                'timeout' => $timeout,
                'maxRetries' => (int) ($this->config['max_retries'] ?? 2),
                'extraHeaders' => $headers ?: null,
                // Guzzle reads whole responses by default; this client streams server-sent events.
                'streamingTransporter' => new GuzzleClient(['stream' => true, 'timeout' => $timeout, 'connect_timeout' => 30]),
                'transporter' => new GuzzleClient(['timeout' => $timeout, 'connect_timeout' => 30]),
            ],
        );
    }

    /** Map wire-format params to the SDK's named arguments. */
    private function namedArguments(array $params): array
    {
        $map = [
            'model' => 'model', 'max_tokens' => 'maxTokens', 'messages' => 'messages', 'system' => 'system',
            'output_config' => 'outputConfig', 'temperature' => 'temperature', 'stop_sequences' => 'stopSequences',
            'thinking' => 'thinking', 'metadata' => 'metadata', 'cache_control' => 'cacheControl',
        ];

        $args = [];
        foreach ($params as $key => $value) {
            if (! isset($map[$key])) {
                throw new ClaudeException("Unsupported request parameter [$key].");
            }
            $args[$map[$key]] = $value;
        }

        return $args;
    }

    private function normalise(Message $message): ClaudeResponse
    {
        $blocks = [];

        foreach ($message->content as $block) {
            if (! $block instanceof TextBlock) {
                continue;
            }

            $citations = [];
            foreach ($block->citations ?? [] as $citation) {
                $citations[] = [
                    'type' => $citation->type ?? 'unknown',
                    'document_index' => $citation->documentIndex ?? null,
                    'file_id' => $citation instanceof CitationPageLocation ? $citation->fileID : null,
                    'document_title' => $citation->documentTitle ?? null,
                    'cited_text' => (string) ($citation->citedText ?? ''),
                    'start_page' => $citation instanceof CitationPageLocation ? $citation->startPageNumber : null,
                    'end_page' => $citation instanceof CitationPageLocation ? $citation->endPageNumber : null,
                ];
            }

            $blocks[] = ['text' => $block->text, 'citations' => $citations];
        }

        $usage = $message->usage;

        return new ClaudeResponse(
            blocks: $blocks,
            model: (string) $message->model,
            stopReason: $message->stopReason ? (is_string($message->stopReason) ? $message->stopReason : $message->stopReason->value) : null,
            inputTokens: (int) $usage->inputTokens,
            outputTokens: (int) $usage->outputTokens,
            cacheReadTokens: (int) ($usage->cacheReadInputTokens ?? 0),
            cacheWrite5mTokens: (int) ($usage->cacheCreation?->ephemeral5mInputTokens ?? $usage->cacheCreationInputTokens ?? 0),
            cacheWrite1hTokens: (int) ($usage->cacheCreation?->ephemeral1hInputTokens ?? 0),
        );
    }

    private function translate(Throwable $e): ClaudeException
    {
        if ($e instanceof ClaudeException) {
            return $e;
        }

        $retryable = $e instanceof RateLimitException
            || $e instanceof APIConnectionException
            || $e instanceof APITimeoutException
            || ($e instanceof APIStatusException && in_array($e->status ?? 0, [408, 409, 429, 500, 502, 503, 504, 529], true));

        $retryAfter = null;
        if ($e instanceof APIStatusException && isset($e->response)) {
            $header = $e->response->getHeaderLine('retry-after');
            $retryAfter = is_numeric($header) ? (int) $header : null;
        }

        return new ClaudeException($e->getMessage(), retryable: $retryable, retryAfter: $retryAfter, previous: $e);
    }
}
