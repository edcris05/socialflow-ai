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

    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'model' => env('OPENAI_MODEL', 'gpt-5-mini'),
        'max_output_tokens' => (int) env('OPENAI_MAX_OUTPUT_TOKENS', 750),
        'strategy_max_output_tokens' => (int) env('OPENAI_STRATEGY_MAX_OUTPUT_TOKENS', 1500),
        'store' => filter_var(env('OPENAI_STORE', false), FILTER_VALIDATE_BOOL),
        'timeout' => (int) env('OPENAI_TIMEOUT', 30),
        'pricing' => [
            'input' => env('OPENAI_INPUT_PRICE_PER_MILLION'),
            'cached_input' => env('OPENAI_CACHED_INPUT_PRICE_PER_MILLION'),
            'output' => env('OPENAI_OUTPUT_PRICE_PER_MILLION'),
        ],
    ],

    'meta' => [
        'base_url' => 'https://graph.instagram.com',
        'version' => 'v26.0',
        'connect_timeout' => 10,
        'timeout' => 20,
        'publishing_enabled' => filter_var(env('META_PUBLISHING_ENABLED', false), FILTER_VALIDATE_BOOL),
    ],

    'scheduled_publishing' => [
        'enabled' => filter_var(env('SCHEDULED_PUBLISHING_ENABLED', false), FILTER_VALIDATE_BOOL),
        'default_limit' => 25,
        'max_limit' => 100,
    ],

    'publication_media' => [
        'allowed_hosts' => array_values(array_filter(array_map(
            static fn (string $host): string => strtolower(trim($host)),
            explode(',', (string) env('PUBLIC_MEDIA_ALLOWED_HOSTS', '')),
        ))),
        'connect_timeout' => (int) env('PUBLIC_MEDIA_CONNECT_TIMEOUT', 5),
        'timeout' => (int) env('PUBLIC_MEDIA_TIMEOUT', 10),
        'max_bytes' => (int) env('PUBLIC_MEDIA_MAX_BYTES', 8 * 1024 * 1024),
        'preflight_fresh_minutes' => (int) env('PUBLIC_MEDIA_PREFLIGHT_FRESH_MINUTES', 15),
    ],

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

];
