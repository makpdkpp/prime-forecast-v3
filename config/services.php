<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'prime_mcp' => [
        'enabled' => env('PRIME_MCP_ENABLED', false),
        'demo_token_issuer_enabled' => env('PRIME_MCP_DEMO_TOKEN_ISSUER_ENABLED', false),
        'service_key' => env('PRIME_MCP_SERVICE_KEY'),
        'audit_enabled' => env('PRIME_MCP_AUDIT_ENABLED', true),
        'max_page_size' => (int) env('PRIME_MCP_MAX_PAGE_SIZE', 100),
    ],

];
