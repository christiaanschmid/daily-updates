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

    // The cloud plan's prices in Paddle. Both have a free trial, set on the price in Paddle.
    'paddle' => [
        'prices' => [
            'monthly' => env('PADDLE_PRICE_MONTHLY'),
            'yearly' => env('PADDLE_PRICE_YEARLY'),
        ],

        // Shown on the Plan page. It must match the trial set on both prices in
        // Paddle, which is what actually decides when people are charged.
        'trial_days' => 30,
    ],

    // The hosted Daily Updates the Mac app syncs with.
    'cloud' => [
        'url' => env('DAILY_UPDATES_CLOUD_URL', 'https://dailyupdates.app'),
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
