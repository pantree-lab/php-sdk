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
    | every 10 minutes via Laravel's task scheduler.
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
    | Health Report Throttle
    |--------------------------------------------------------------------------
    | Prevents health reports from being sent too frequently when called from
    | request lifecycle hooks (for example AppServiceProvider::boot()).
    |
    | Minimum allowed gap between reports, in seconds. Default is 600 (10 min).
    */
    'health_min_interval_seconds' => (int) env('PANTREE_HEALTH_MIN_INTERVAL_SECONDS', 600),

    /*
    |--------------------------------------------------------------------------
    | Health state store path
    |--------------------------------------------------------------------------
    | Lightweight local state file used to keep the last report timestamp.
    */
    'health_state_path' => env('PANTREE_HEALTH_STATE_PATH', storage_path('framework/cache/pantree-health-state.json')),

    /*
    |--------------------------------------------------------------------------
    | Reject when too soon
    |--------------------------------------------------------------------------
    | false => sendHealthReport() quietly skips and returns skipped=true
    | true  => sendHealthReport() returns 429-like response when throttled
    */
    'health_reject_too_soon' => (bool) env('PANTREE_HEALTH_REJECT_TOO_SOON', false),

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
