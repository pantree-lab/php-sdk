<?php

/**
 * Pantree PHP SDK — health report cron script
 *
 * Schedule this file to run every 30 minutes:
 *   * /30 * * * *  php /path/to/project/health-report.php >> /var/log/pantree-health.log 2>&1
 *
 * Environment:
 *   PANTREE_DSN=https://key:secret@your-pantree.com/api/ingest
 */

require __DIR__ . '/../vendor/autoload.php';

use Pantree\PantreeClient;

$dsn = getenv('PANTREE_DSN');
if (!$dsn) {
    fwrite(STDERR, '[Pantree] PANTREE_DSN is not set' . PHP_EOL);
    exit(1);
}

$pantree = PantreeClient::fromDsn($dsn);

$result = $pantree->sendHealthReport();

$status  = $result['status'] ?? 0;
$success = $status === 200;

echo sprintf(
    '[%s] Health report: %s (HTTP %d)%s',
    date('Y-m-d H:i:s'),
    $success ? 'OK' : 'FAILED',
    $status,
    PHP_EOL,
);

exit($success ? 0 : 1);
