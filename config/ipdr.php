<?php

/*
 * The session records database. An empty value takes the app's own MySQL connection setting; the database name
 * defaults to ispgrid_ipdr. Read here, not in the service provider, so the values survive `config:cache`.
 */
return [
    'database' => [
        'host' => env('IPDR_DB_HOST'),
        'port' => env('IPDR_DB_PORT'),
        'database' => env('IPDR_DB_DATABASE'),
        'username' => env('IPDR_DB_USERNAME'),
        'password' => env('IPDR_DB_PASSWORD'),
    ],
];
