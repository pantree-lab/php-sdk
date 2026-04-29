<?php

namespace Pantree\Laravel;

use Pantree\PantreeClient;

/**
 * Laravel facade-style wrapper around PantreeClient.
 *
 * Configure via .env:
 *   PANTREE_DSN=https://apiKey:secret@your-pantree.com/api/ingest
 *
 * Or legacy separate keys:
 *   PANTREE_ENDPOINT=https://your-pantree.com/api/ingest
 *   PANTREE_PROJECT_KEY=xxx
 *   PANTREE_INGEST_SECRET=yyy
 */
class Pantree
{
    private static ?PantreeClient $client = null;

    private static function client(): PantreeClient
    {
        if (self::$client === null) {
            $dsn = config('pantree.dsn');
            if ($dsn) {
                self::$client = PantreeClient::fromDsn(
                    $dsn,
                    config('pantree.environment', app()->environment()),
                    config('pantree.debug', false),
                );
            } else {
                self::$client = new PantreeClient(
                    config('pantree.endpoint', ''),
                    config('pantree.project_key', ''),
                    config('pantree.ingest_secret', ''),
                    config('pantree.environment', app()->environment()),
                    config('pantree.debug', false),
                );
            }
        }
        return self::$client;
    }

    /** Capture a Throwable. */
    public static function captureException(\Throwable $e, array $extra = []): array
    {
        return self::client()->captureException($e, $extra);
    }

    /** Capture a message. */
    public static function captureMessage(string $message, string $level = 'info', array $extra = []): array
    {
        return self::client()->captureMessage($message, $level, $extra);
    }

    /**
     * Send one encrypted health report immediately.
     * Called automatically by the scheduler every 30 minutes when health_reporting = true.
     */
    public static function sendHealthReport(): array
    {
        return self::client()->sendHealthReport();
    }

    /** Reset the singleton (useful in tests). */
    public static function reset(): void
    {
        self::$client = null;
    }
}
