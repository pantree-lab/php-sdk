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
            $release  = config('pantree.release')  ?: null;
            $git      = config('pantree.git')      ?: null;
            $packages = config('pantree.packages') ?: null;

            $dsn = config('pantree.dsn');
            if ($dsn) {
                self::$client = PantreeClient::fromDsn(
                    $dsn,
                    config('pantree.environment', app()->environment()),
                    config('pantree.debug', false),
                    $release,
                    $git,
                    $packages,
                    (int) config('pantree.health_min_interval_seconds', 600),
                    config('pantree.health_state_path'),
                );
            } else {
                self::$client = new PantreeClient(
                    config('pantree.endpoint', ''),
                    config('pantree.project_key', ''),
                    config('pantree.ingest_secret', ''),
                    config('pantree.environment', app()->environment()),
                    config('pantree.debug', false),
                    $release,
                    $git,
                    $packages,
                    (int) config('pantree.health_min_interval_seconds', 600),
                    config('pantree.health_state_path'),
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
        $result = self::client()->sendHealthReport();
        if (
            config('pantree.health_reject_too_soon', true) &&
            ($result['body']['skipped'] ?? false) === true &&
            ($result['body']['reason'] ?? null) === 'health-report-throttled'
        ) {
            return [
                'status' => 429,
                'body' => [
                    'success' => false,
                    'message' => 'Health report throttled. Minimum interval is 10 minutes.',
                    'retry_after_seconds' => $result['body']['retry_after_seconds'] ?? 600,
                ],
            ];
        }

        return $result;
    }

    /** Reset the singleton (useful in tests). */
    public static function reset(): void
    {
        self::$client = null;
    }
}
