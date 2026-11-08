<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    | Paystack checkout for project payments. Use sk_test_/pk_test_ keys in development. Mobile Money needs a
    | Ghanaian (GHS) or Kenyan (KES) Paystack account; channels limits the checkout to card and Mobile Money.
    | Set the webhook URL in the Paystack dashboard to https://your-domain/webhooks/paystack.
    */
    'paystack' => [
        'secret_key' => env('PAYSTACK_SECRET_KEY'),
        'public_key' => env('PAYSTACK_PUBLIC_KEY'),
        'base_url' => env('PAYSTACK_BASE_URL', 'https://api.paystack.co'),
        'channels' => array_filter(explode(',', (string) env('PAYSTACK_CHANNELS', 'card,mobile_money'))),
    ],

    /*
    | Claude (Anthropic API), used only for title suggestions. The key is only ever read on the server.
    | Prices are USD per million tokens and are used to estimate the cost logged in ai_calls.
    */
    'claude' => [
        'api_key' => env('ANTHROPIC_API_KEY'),
        // Use the built-in fake instead of the API (sample suggestions; for demos and local development).
        'fake' => (bool) env('CLAUDE_FAKE', false),
        'base_url' => env('ANTHROPIC_BASE_URL'),
        // A fast model: suggestions are generated while the researcher waits.
        'model' => env('CLAUDE_MODEL', 'claude-haiku-5-5'),
        'max_tokens' => (int) env('CLAUDE_MAX_TOKENS', 4000),
        'timeout' => (float) env('CLAUDE_TIMEOUT', 45),
        'max_retries' => (int) env('CLAUDE_MAX_RETRIES', 1),
        // Comma-separated anthropic-beta header values, if a feature you use still needs one.
        'beta_headers' => env('CLAUDE_BETA_HEADERS'),
        'pricing' => [
            'claude-opus-5-5' => ['input' => (float) env('PRICE_OPUS_INPUT', 5.00), 'output' => (float) env('PRICE_OPUS_OUTPUT', 25.00)],
            'claude-sonnet-5-5' => ['input' => (float) env('PRICE_SONNET_INPUT', 3.00), 'output' => (float) env('PRICE_SONNET_OUTPUT', 15.00)],
            'claude-haiku-5-5' => ['input' => (float) env('PRICE_HAIKU_INPUT', 1.00), 'output' => (float) env('PRICE_HAIKU_OUTPUT', 5.00)],
        ],
        // Multipliers on the input price.
        'cache_write_multiplier' => ['5m' => 1.25, '1h' => 2.0],
        'cache_read_multiplier' => 0.1,
    ],

];
