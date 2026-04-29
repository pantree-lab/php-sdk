# Health Reporting — pantree/pantree-php

## Overview

`sendHealthReport()` collects detailed system and Git information, encrypts it with AES-256-GCM, and sends it to your Pantree server's `/api/health-report` endpoint.

Only **super-admins** can decrypt and view this data. It is never stored in plain text.

## Encryption

| Property | Value |
|---|---|
| Key derivation | `hash_hkdf('sha256', $ingestSecret, 32, 'health-key', 'pantree-health-v1')` |
| Cipher | AES-256-GCM |
| IV | 12 random bytes (`random_bytes(12)`) |
| Auth tag | 16 bytes (appended to ciphertext to match Web Crypto format) |
| Encoding | Base64 |

## Calling manually

```php
$result = $pantree->sendHealthReport();
// ['status' => 200, 'body' => ['success' => true]]
```

## Cron setup

```cron
# Every 30 minutes
*/30 * * * *  php /var/www/html/cron-health.php >> /var/log/pantree.log 2>&1
```

```php
<?php
// cron-health.php
require __DIR__ . '/vendor/autoload.php';
use Pantree\PantreeClient;

$pantree = PantreeClient::fromDsn(getenv('PANTREE_DSN'), debug: true);
$result = $pantree->sendHealthReport();
echo date('c') . ' health report: ' . ($result['status'] === 200 ? 'OK' : 'FAILED') . PHP_EOL;
```

## What is collected

| Category | Method | Linux | Windows / macOS |
|---|---|---|---|
| OS | `php_uname()` | ✓ | ✓ |
| RAM | `/proc/meminfo` | ✓ | falls back to `memory_limit` |
| Disk | `disk_total_space('/')` | ✓ | ✓ |
| Local IP | `gethostbyname()` | ✓ | ✓ |
| Public IP | cURL → `/api/ip` on your server | ✓ | ✓ |
| Machine ID | `/etc/machine-id` | ✓ | — |
| Container | `/.dockerenv`, `KUBERNETES_SERVICE_HOST` | ✓ | — |
| Git | `shell_exec('git ...')` | ✓ | ✓ |
