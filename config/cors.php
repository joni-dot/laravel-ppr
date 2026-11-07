<?php

/*
 * No web page on another site may call this app from a browser.
 * Laravel's default would allow any website to call api/* routes.
 */
return [

    'paths' => [],

    'allowed_methods' => [],

    'allowed_origins' => [],

    'allowed_origins_patterns' => [],

    'allowed_headers' => [],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
