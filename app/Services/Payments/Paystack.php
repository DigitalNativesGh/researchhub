<?php

namespace App\Services\Payments;

use App\Models\Payment;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Thin client for the Paystack Transactions API. The student pays on Paystack's hosted checkout (card or
 * Mobile Money); we never see card or wallet details.
 */
class Paystack
{
    public function __construct(private readonly array $config) {}

    public function configured(): bool
    {
        return filled($this->config['secret_key'] ?? null);
    }

    /** Start a checkout and return the URL to send the student to. */
    public function initialize(Payment $payment, string $email, string $callbackUrl, array $metadata = []): string
    {
        $data = $this->call('post', '/transaction/initialize', [
            'email' => $email,
            'amount' => $payment->amount,
            'currency' => $payment->currency,
            'reference' => $payment->reference,
            'callback_url' => $callbackUrl,
            'channels' => array_values($this->config['channels'] ?? ['card', 'mobile_money']),
            'metadata' => $metadata + ['payment_id' => $payment->id],
        ]);

        return $data['authorization_url'] ?? throw new PaystackException('Paystack did not return a checkout link.');
    }

    /** @return array the transaction as Paystack reports it */
    public function verify(string $reference): array
    {
        return $this->call('get', '/transaction/verify/'.rawurlencode($reference));
    }

    /** Webhooks are signed with HMAC-SHA512 of the raw body using the secret key. */
    public function validSignature(string $payload, ?string $signature): bool
    {
        return $this->configured() && $signature !== null
            && hash_equals(hash_hmac('sha512', $payload, (string) $this->config['secret_key']), $signature);
    }

    private function call(string $method, string $path, array $body = []): array
    {
        if (! $this->configured()) {
            throw new PaystackException('Online payments are not set up yet. Add PAYSTACK_SECRET_KEY to the server configuration.');
        }

        try {
            $response = $this->client()->{$method}($path, $body);
        } catch (ConnectionException) {
            throw new PaystackException('Could not reach Paystack. Check your connection and try again.');
        }

        if (! $response->successful() || ! $response->json('status')) {
            throw new PaystackException($response->json('message') ?: 'Paystack could not process the request.');
        }

        return (array) $response->json('data');
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl(rtrim($this->config['base_url'] ?? 'https://api.paystack.co', '/'))
            ->withToken((string) $this->config['secret_key'])
            ->acceptJson()->asJson()
            ->timeout(20)->retry(2, 300, fn ($e) => $e instanceof ConnectionException, throw: false);
    }
}
