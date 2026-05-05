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
        $gate = self::healthReportGate();
        if ($gate['allowed'] === false) {
            if (config('pantree.health_reject_too_soon', true)) {
                return [
                    'status' => 429,
                    'body' => [
                        'success' => false,
                        'message' => 'Health report throttled. Minimum interval is 10 minutes.',
                        'retry_after_seconds' => $gate['retry_after_seconds'],
                    ],
                ];
            }

            return [
                'status' => 200,
                'body' => [
                    'success' => true,
                    'skipped' => true,
                    'reason' => 'health-report-throttled',
                    'retry_after_seconds' => $gate['retry_after_seconds'],
                ],
            ];
        }

        return self::client()->sendHealthReport();
    }

    private static function healthReportGate(): array
    {
        $interval = (int) config('pantree.health_min_interval_seconds', 600);
        if ($interval <= 0) {
            return ['allowed' => true, 'retry_after_seconds' => 0];
        }

        $statePath = (string) config('pantree.health_state_path', storage_path('framework/cache/pantree-health-state.json'));
        $stateDir = dirname($statePath);
        if (!is_dir($stateDir)) {
            @mkdir($stateDir, 0775, true);
        }

        $file = @fopen($statePath, 'c+');
        if ($file === false) {
            // Fail open: never block app traffic if state store is unavailable.
            return ['allowed' => true, 'retry_after_seconds' => 0];
        }

        try {
            if (!flock($file, LOCK_EX)) {
                return ['allowed' => true, 'retry_after_seconds' => 0];
            }

            rewind($file);
            $raw = stream_get_contents($file);
            $state = is_string($raw) && $raw !== '' ? json_decode($raw, true) : [];
            $lastTs = (int) ($state['last_health_report_ts'] ?? 0);
            $nowTs = time();
            $nextAllowedTs = $lastTs + $interval;

            if ($lastTs > 0 && $nowTs < $nextAllowedTs) {
                return [
                    'allowed' => false,
                    'retry_after_seconds' => max(1, $nextAllowedTs - $nowTs),
                ];
            }

            $next = ['last_health_report_ts' => $nowTs];
            rewind($file);
            ftruncate($file, 0);
            fwrite($file, json_encode($next, JSON_UNESCAPED_SLASHES));
            fflush($file);

            return ['allowed' => true, 'retry_after_seconds' => 0];
        } finally {
            flock($file, LOCK_UN);
            fclose($file);
        }
    }

    /** Reset the singleton (useful in tests). */
    public static function reset(): void
    {
        self::$client = null;
    }
}
