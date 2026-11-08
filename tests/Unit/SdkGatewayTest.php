<?php

use App\Services\Claude\ClaudeException;
use App\Services\Claude\SdkGateway;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;

/** A gateway whose HTTP calls are answered by $respond; requests are captured in $captured. */
function sdkGateway(Closure $respond, array &$captured): SdkGateway
{
    $middleware = function (RequestInterface $request, $next) use ($respond, &$captured) {
        $captured[] = $request;

        return $respond($request);
    };

    return new SdkGateway(['api_key' => 'test-key', 'base_url' => null, 'timeout' => 5, 'max_retries' => 0, 'sdk_middleware' => [$middleware]] + config('services.claude'));
}

function sse(array $events): string
{
    return implode('', array_map(fn ($e) => 'event: '.$e['type']."\ndata: ".json_encode($e)."\n\n", $events));
}

it('sends cached documents and structured-output settings, and reads citations and usage', function () {
    $captured = [];
    $gateway = sdkGateway(fn () => new Response(200, ['content-type' => 'application/json'], json_encode([
        'id' => 'msg_1', 'type' => 'message', 'role' => 'assistant', 'model' => 'claude-opus-5-5', 'stop_reason' => 'end_turn', 'stop_sequence' => null,
        'content' => [['type' => 'text', 'text' => 'Adoption is high', 'citations' => [[
            'type' => 'page_location', 'cited_text' => 'Adoption was high.', 'document_index' => 1, 'document_title' => 'Owusu', 'file_id' => 'file_abc',
            'start_page_number' => 4, 'end_page_number' => 5,
        ]]]],
        'usage' => ['input_tokens' => 100, 'output_tokens' => 20, 'cache_read_input_tokens' => 4000, 'cache_creation_input_tokens' => 0,
            'cache_creation' => ['ephemeral_5m_input_tokens' => 0, 'ephemeral_1h_input_tokens' => 300]],
    ])), $captured);

    $response = $gateway->send([
        'model' => 'claude-opus-5-5', 'max_tokens' => 1000,
        'system' => [['type' => 'text', 'text' => 'Be careful.', 'cache_control' => ['type' => 'ephemeral', 'ttl' => '1h']]],
        'messages' => [['role' => 'user', 'content' => [
            ['type' => 'document', 'source' => ['type' => 'file', 'file_id' => 'file_abc'], 'citations' => ['enabled' => true], 'cache_control' => ['type' => 'ephemeral']],
            ['type' => 'text', 'text' => 'Write.'],
        ]]],
        'output_config' => ['effort' => 'high'],
    ]);

    $body = json_decode((string) $captured[0]->getBody(), true);
    expect((string) $captured[0]->getUri())->toEndWith('/v1/messages')
        ->and($captured[0]->getHeaderLine('x-api-key'))->toBe('test-key')
        ->and($body['messages'][0]['content'][0]['source']['file_id'])->toBe('file_abc')
        ->and($body['system'][0]['cache_control']['ttl'])->toBe('1h')
        ->and($body['output_config']['effort'])->toBe('high');

    expect($response->text())->toBe('Adoption is high')
        ->and($response->blocks[0]['citations'][0])->toMatchArray(['document_index' => 1, 'start_page' => 4, 'cited_text' => 'Adoption was high.'])
        ->and($response->cacheReadTokens)->toBe(4000)
        ->and($response->cacheWrite1hTokens)->toBe(300);
});

it('streams text deltas and accumulates citations', function () {
    $captured = [];
    $gateway = sdkGateway(fn () => new Response(200, ['content-type' => 'text/event-stream'], sse([
        ['type' => 'message_start', 'message' => ['id' => 'm', 'type' => 'message', 'role' => 'assistant', 'model' => 'claude-opus-5-5', 'content' => [], 'stop_reason' => null, 'stop_sequence' => null, 'usage' => ['input_tokens' => 12, 'output_tokens' => 1]]],
        ['type' => 'content_block_start', 'index' => 0, 'content_block' => ['type' => 'text', 'text' => '', 'citations' => []]],
        ['type' => 'content_block_delta', 'index' => 0, 'delta' => ['type' => 'citations_delta', 'citation' => ['type' => 'page_location', 'cited_text' => 'Quote', 'document_index' => 0, 'document_title' => 'A', 'start_page_number' => 2, 'end_page_number' => 3]]],
        ['type' => 'content_block_delta', 'index' => 0, 'delta' => ['type' => 'text_delta', 'text' => 'Hello ']],
        ['type' => 'content_block_delta', 'index' => 0, 'delta' => ['type' => 'text_delta', 'text' => 'world']],
        ['type' => 'content_block_stop', 'index' => 0],
        ['type' => 'message_delta', 'delta' => ['stop_reason' => 'end_turn', 'stop_sequence' => null], 'usage' => ['output_tokens' => 30]],
        ['type' => 'message_stop'],
    ])), $captured);

    $deltas = [];
    $response = $gateway->send(['model' => 'claude-opus-5-5', 'max_tokens' => 10, 'messages' => [['role' => 'user', 'content' => 'hi']]], function ($t) use (&$deltas) {
        $deltas[] = $t;
    });

    expect($deltas)->toBe(['Hello ', 'world'])
        ->and($response->text())->toBe('Hello world')
        ->and($response->blocks[0]['citations'][0]['cited_text'])->toBe('Quote')
        ->and($response->outputTokens)->toBe(30)
        ->and($response->stopReason)->toBe('end_turn');
});

it('deletes files the retired AI workflow uploaded', function () {
    $captured = [];
    $gateway = sdkGateway(fn () => new Response(200, ['content-type' => 'application/json'], json_encode(['id' => 'file_123', 'type' => 'file_deleted'])), $captured);

    $gateway->deleteFile('file_123');

    expect($captured[0]->getMethod())->toBe('DELETE')->and((string) $captured[0]->getUri())->toContain('/v1/files/file_123');
});

it('marks rate limits and overload as retryable', function (int $status, bool $retryable) {
    $captured = [];
    $gateway = sdkGateway(fn () => new Response($status, ['content-type' => 'application/json', 'retry-after' => '12'], json_encode(['type' => 'error', 'error' => ['type' => 'x', 'message' => 'nope']])), $captured);

    try {
        $gateway->send(['model' => 'claude-opus-5-5', 'max_tokens' => 10, 'messages' => [['role' => 'user', 'content' => 'hi']]]);
        $this->fail('expected an exception');
    } catch (ClaudeException $e) {
        expect($e->retryable)->toBe($retryable);
    }
})->with([[429, true], [529, true], [400, false]]);

it('explains a missing API key', function () {
    expect(fn () => (new SdkGateway(['api_key' => ''] + config('services.claude')))->send(['model' => 'm', 'max_tokens' => 1, 'messages' => []]))
        ->toThrow(ClaudeException::class, 'ANTHROPIC_API_KEY');
});
