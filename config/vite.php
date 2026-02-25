<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Vite Build Paths
    |--------------------------------------------------------------------------
    |
    | Vite will scan for assets within these paths when preparing them for
    | production deployment. The default settings should be sufficient for
    | most applications. However, if you have additional directories or
    | have customized your asset structure, you can update them here.
    |
    */

    'build_path' => 'build',

    /*
    |--------------------------------------------------------------------------
    | Vite Development Server
    |--------------------------------------------------------------------------
    |
    | These configuration values are used when Vite's development server is
    | running. The development server will serve your assets with hot module
    | replacement, making development faster and more convenient for you.
    |
    */

    'dev_server_key' => 'dev_server_key',

    'dev_server_cert' => 'dev_server_cert',

    /*
    |--------------------------------------------------------------------------
    | Vite Hot File
    |--------------------------------------------------------------------------
    |
    | This is the file that Vite generates to indicate that the development
    | server is running. When this file is present, Vite will serve assets
    | directly from the development server for faster loading and hot reload.
    |
    */

    'hot_file' => public_path('hot'),

];
