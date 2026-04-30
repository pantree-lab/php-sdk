<?php

return [

    /*
    |--------------------------------------------------------------------------
    | DSN  (recommended — single string containing all credentials)
    |--------------------------------------------------------------------------
    | Format: https://<apiKey>:<ingestSecret>@your-pantree.com/api/ingest
    | Set PANTREE_DSN in your .env file.
    | When DSN is set, the legacy endpoint / project_key / ingest_secret
    | values below are ignored.
    */
    'dsn' => env('PANTREE_DSN', ''),

    /*
    |--------------------------------------------------------------------------
    | Legacy separate credentials  (used only when DSN is empty)
    |--------------------------------------------------------------------------
    */
    'endpoint'      => env('PANTREE_ENDPOINT',      'https://your-pantree.com/api/ingest'),
    'project_key'   => env('PANTREE_PROJECT_KEY',   ''),
    'ingest_secret' => env('PANTREE_INGEST_SECRET', ''),

    /*
    |--------------------------------------------------------------------------
    | Environment
    |--------------------------------------------------------------------------
    | Defaults to the current Laravel environment (app()->environment()).
    */
    'environment' => env('PANTREE_ENVIRONMENT', null),

    /*
    |--------------------------------------------------------------------------
    | Health Reporting
    |--------------------------------------------------------------------------
    | Set to true to automatically send an encrypted system health report
    | every 30 minutes via Laravel's task scheduler.
    |
    | The report includes: OS, RAM, disk, IP, MAC, machine ID, container
    | detection, and Git trace — encrypted with AES-256-GCM before sending.
    | Only super-admins on the Pantree server can decrypt it.
    |
    | Requires: Laravel scheduler (php artisan schedule:run) running via cron.
    */
    'health_reporting' => env('PANTREE_HEALTH_REPORTING', false),

    /*
    |--------------------------------------------------------------------------
    | Release
    |--------------------------------------------------------------------------
    | Tag the captured events with a release/version string (e.g. "v1.4.2").
    | Appears in context.trace.release on every event.
    */
    'release' => env('PANTREE_RELEASE', null),

    /*
    |--------------------------------------------------------------------------
    | Git override  (optional — auto-detected from the repo by default)
    |--------------------------------------------------------------------------
    | Provide static git info when running in CI or environments without a
    | .git directory.  Keys: branch, commitHash, tag, repoUrl, username, email.
    | Example:
    |   'git' => [
    |       'branch'     => env('GIT_BRANCH'),
    |       'commitHash' => env('GIT_COMMIT'),
    |   ],
    */
    'git' => null,

    /*
    |--------------------------------------------------------------------------
    | Packages override  (optional — auto-detected via Composer by default)
    |--------------------------------------------------------------------------
    | Supply your own package map instead of the auto-detected Composer list.
    | Example: ['laravel/framework' => '^11.0', 'my-pkg' => '1.0.0']
    */
    'packages' => null,

    /*
    |--------------------------------------------------------------------------
    | Debug
    |--------------------------------------------------------------------------
    */
    'debug' => env('PANTREE_DEBUG', false),

];
