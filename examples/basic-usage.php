<?php

/**
 * Pantree PHP SDK — basic usage example
 *
 * Run:
 *   PANTREE_DSN=https://key:secret@your-pantree.com/api/ingest php basic-usage.php
 */

require __DIR__ . '/../vendor/autoload.php';

use Pantree\PantreeClient;

$pantree = PantreeClient::fromDsn(
    dsn:   getenv('PANTREE_DSN'),
    debug: true,
);

// ── Capture an exception ──────────────────────────────────────────────────────

try {
    throw new \RuntimeException('Something went wrong in the payment service');
} catch (\Throwable $e) {
    $result = $pantree->captureException($e, [
        'user'    => ['id' => 42, 'email' => 'alice@example.com'],
        'context' => ['orderId' => 'ord_99', 'amount' => 199.99],
    ]);
    echo 'captureException status: ' . $result['status'] . PHP_EOL;
}

// ── Capture a message ─────────────────────────────────────────────────────────

$result = $pantree->captureMessage('Rate limit hit for IP 1.2.3.4', 'warning', [
    'context' => ['ip' => '1.2.3.4', 'endpoint' => '/api/orders'],
]);
echo 'captureMessage status: ' . $result['status'] . PHP_EOL;

// ── Low-level send ────────────────────────────────────────────────────────────

$result = $pantree->send([
    'title'       => 'Custom event',
    'message'     => 'Database connection pool exhausted',
    'level'       => 'error',
    'runtime'     => 'php-worker',
    'environment' => 'production',
    'context'     => ['pool_size' => 20, 'waiting' => 35],
]);
echo 'send status: ' . $result['status'] . PHP_EOL;
