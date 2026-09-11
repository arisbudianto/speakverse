<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | Credentials untuk layanan pihak ketiga.
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
    |--------------------------------------------------------------------------
    | Dinoiki AI
    |--------------------------------------------------------------------------
    */

    'dinoiki' => [
        'key' => env('DINOIKI_API_KEY'),

        'base_url' => env(
            'DINOIKI_API_URL',
            'https://ai.dinoiki.com/v1'
        ),

        'chat_model' => env(
            'DINOIKI_CHAT_MODEL',
            'gpt-4o'
        ),
    ],

    /*
    |--------------------------------------------------------------------------
    | Google OAuth
    |--------------------------------------------------------------------------
    */

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),

        'client_secret' => env('GOOGLE_CLIENT_SECRET'),

        'redirect' => env(
            'GOOGLE_REDIRECT_URI',
            'https://speakverse.id/auth/google/callback'
        ),
    ],

];