<?php

return [
    /*
    |--------------------------------------------------------------------------
    | PMTA SMTP Configuration
    |--------------------------------------------------------------------------
    | The dedicated PMTA server for outreach emails.
    | Set these in .env.production on the server.
    */
    'pmta_host' => env('OUTREACH_PMTA_HOST', '127.0.0.1'),
    'pmta_port' => env('OUTREACH_PMTA_PORT', 2525),
    'pmta_user' => env('OUTREACH_PMTA_USER', ''),
    'pmta_pass' => env('OUTREACH_PMTA_PASS', ''),

    /*
    |--------------------------------------------------------------------------
    | Sending Domains (rotation)
    |--------------------------------------------------------------------------
    | Comma-separated list of from_email addresses.
    | Round-robin rotation per contact ID.
    */
    'sending_emails' => env('OUTREACH_SENDING_EMAILS', 'williams@provider-expat.com,williams@hub-travelers.com,williams@spaceship.com'),
];
