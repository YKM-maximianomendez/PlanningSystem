<?php

return [
    /*
    |--------------------------------------------------------------------------
    | AS400 Environment
    |--------------------------------------------------------------------------
    |
    | This value determines the current AS/400 environment, which can be
    | used to load the correct library list and inform the frontend.
    | Supported values: 'live', 'proto', 'development'.
    |
    */
    'env' => env('INFORLX_ENVIRONMENT', 'PROTO'),

    /*
    |--------------------------------------------------------------------------
    | AS400 Library List
    |--------------------------------------------------------------------------
    |
    | This value sets the library list for the AS/400 connection.
    | It's read from an environment variable to allow different lists
    | for different environments (e.g., live, proto, development).
    |
    */
    'libs' => match (env('INFORLX_ENVIRONMENT', 'PROTO')) {
        'LIVE' => 'LX834F01,LX834FU01,LX834OU01',
        'PROTO' => 'LX834F02,LX834FU02,LX834OU02',
        'DEVELOPMENT' => 'LX834F03,LX834FU03,LX834OU03',
    },
];
