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
