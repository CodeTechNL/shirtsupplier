<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Supported Languages
    |--------------------------------------------------------------------------
    |
    | The language codes used for translatable product fields such as title,
    | url, fulltitle, and content. The first language is the default.
    |
    */

    'languages' => ['nl'],

    /*
    |--------------------------------------------------------------------------
    | Webhooks
    |--------------------------------------------------------------------------
    |
    | Configuration for incoming Lightspeed webhooks.
    |
    */

    'webhooks' => [
        'middleware' => [],
        'queue' => env('WEBSHOP_WEBHOOK_QUEUE', 'default'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Lightspeed eCom API
    |--------------------------------------------------------------------------
    |
    | Credentials for the Lightspeed eCom (SEOshop) API integration.
    |
    */

    'lightspeed' => [
        'key' => env('LIGHTSPEED_API_KEY'),
        'secret' => env('LIGHTSPEED_API_SECRET'),
        'cluster' => env('LIGHTSPEED_API_CLUSTER', 'eu1'),
        'language' => env('LIGHTSPEED_API_DEFAULT_LANGUAGE', 'nl'),
    ],

];
