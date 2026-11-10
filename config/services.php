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

    'microsoft' => [
        'client_id'     => env('AZURE_CLIENT_ID'),
        'client_secret' => env('AZURE_CLIENT_SECRET'),
        'redirect'      => env('AZURE_REDIRECT_URI'),
        'tenant'        => env('AZURE_TENANT_ID'),
    ],

    // Envío de correo con Microsoft Graph (app "SGP - Buzón", distinta a la del SSO)
    'microsoft_graph' => [
        'tenant_id'     => env('MS_GRAPH_TENANT_ID'),
        'client_id'     => env('MS_GRAPH_CLIENT_ID'),
        'client_secret' => env('MS_GRAPH_CLIENT_SECRET'),
        'remitente'     => env('MS_GRAPH_REMITENTE', 'sgp@dasavena.com'),
    ],

    'netsuite' => [
    'account_id'      => env('NETSUITE_ACCOUNT_ID'),
    'consumer_key'    => env('NETSUITE_CONSUMER_KEY'),
    'consumer_secret' => env('NETSUITE_CONSUMER_SECRET'),
    'token_id'        => env('NETSUITE_TOKEN_ID'),
    'token_secret'    => env('NETSUITE_TOKEN_SECRET'),
    'file_restlet_script_id' => env('NETSUITE_FILE_RESTLET_SCRIPT_ID'),
    'file_restlet_deploy_id' => env('NETSUITE_FILE_RESTLET_DEPLOY_ID'),
    ],

    'company' => [
        'rfc' => env('COMPANY_RFC'),
        ],
    'payment_complements' => [
        'required_since' => env('PAYMENT_COMPLEMENT_REQUIRED_SINCE', '2026-06-01'),
        ],
    'health' => [
    'token' => env('HEALTH_CHECK_TOKEN'),
    ],
        'cron' => [
        'token'      => env('CRON_TRIGGER_TOKEN'),
        'php_binary' => env('CRON_PHP_BINARY', 'php'),
    ],
    // No Conformidades: consecutivo inicial del folio por año (continúa la numeración manual de Calidad)
    'non_conformities' => [
        'folio_start' => [
            2026 => (int) env('NC_FOLIO_START_2026', 1),
        ],
    ],
];