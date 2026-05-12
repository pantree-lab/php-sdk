# pantree/pantree-php

<p align="center">
  <a href="https://packagist.org/packages/pantree/pantree-php" title="View on Packagist">
    <img src="./arts/250x250_without_brand_title.png" alt="Pantree" width="250" height="250" />
  </a>
</p>

<p align="center">
  <a href="https://packagist.org/packages/pantree/pantree-php"><img src="https://img.shields.io/packagist/v/pantree/pantree-php.svg" alt="Packagist version" /></a>
</p>

Official PHP & Laravel SDK for [Pantree](https://pantree.dev) — error monitoring and encrypted health reporting.

Supports **raw PHP 8.1+** and **Laravel 10, 11, 12**.  
Requires `ext-curl` and `ext-openssl`.

---

## Table of Contents

- [Installation](#installation)
- [Quick Start](#quick-start)
  - [Raw PHP](#raw-php)
  - [Laravel](#laravel)
- [Configuration](#configuration)
- [Capturing Errors](#capturing-errors)
- [Health Reporting](#health-reporting)
- [Package Layout](#package-layout)
- [API Reference](#api-reference)
- [Changelog](#changelog)

---

## Installation

```bash
composer require pantree/pantree-php
```

For Laravel, publish the config file:

```bash
php artisan vendor:publish --tag=pantree-config
```

---

## Quick Start

### Raw PHP

```php
<?php

use Pantree\PantreeClient;

$pantree = PantreeClient::fromDsn($_ENV['PANTREE_DSN']);

try {
    riskyOperation();
} catch (\Throwable $e) {
    $pantree->captureException($e);
    throw $e;
}
```

### Laravel

Add your DSN to `.env`:

```env
PANTREE_DSN=https://pk_abc123:sk_xyz789@your-pantree.com/api/ingest
```

The `PantreeServiceProvider` is auto-discovered and wires up exception capture automatically.
No manual registration required.

Manual capture from anywhere in your app:

```php
use Pantree\Laravel\Pantree;

Pantree::captureException($e);
Pantree::captureMessage('Something notable happened', 'warning');
Pantree::sendHealthReport();
```

---

## Configuration

### DSN constructor (recommended)

Your DSN is available in **Project → Settings** on your Pantree dashboard:

```
https://<apiKey>:<ingestSecret>@your-pantree.com/api/ingest
```

**Raw PHP:**

```php
$pantree = PantreeClient::fromDsn(
    dsn:         $_ENV['PANTREE_DSN'],
    environment: 'production',   // optional, default 'production'
    debug:       false,          // optional, logs to error_log
);
```

**Laravel** — set env vars:

```env
PANTREE_DSN=https://pk_abc123:sk_xyz789@your-pantree.com/api/ingest
PANTREE_ENVIRONMENT=production      # defaults to app()->environment()
PANTREE_HEALTH_REPORTING=true       # enable auto health reports every 10 min
PANTREE_HEALTH_MIN_INTERVAL_SECONDS=600
PANTREE_DEBUG=false
```

### Legacy constructor (raw PHP)

```php
$pantree = new PantreeClient(
    endpoint:     'https://your-pantree.com/api/ingest',
    projectKey:   'pk_abc123',
    ingestSecret: 'sk_xyz789',
    environment:  'production',
    debug:        false,
);
```

### Legacy env vars (Laravel)

If you don't have a DSN, the following are used instead:

```env
PANTREE_ENDPOINT=https://your-pantree.com/api/ingest
PANTREE_PROJECT_KEY=pk_abc123
PANTREE_INGEST_SECRET=sk_xyz789
```

---

## Capturing Errors

### `captureException(Throwable $e, array $extra = [])`

```php
// Raw PHP
$pantree->captureException($e);
$pantree->captureException($e, [
    'user'    => ['id' => $userId, 'email' => $email],
    'context' => ['orderId' => $orderId],
]);

// Laravel
Pantree::captureException($e);
Pantree::captureException($e, [
    'user'    => ['id' => auth()->id(), 'email' => auth()->user()?->email],
    'context' => ['orderId' => $order->id],
]);
```

### `captureMessage(string $message, string $level = 'info', array $extra = [])`

```php
// Raw PHP
$pantree->captureMessage('Slow query detected', 'warning', [
    'context' => ['queryMs' => 4200],
]);

// Laravel
Pantree::captureMessage('Queue backlog is growing', 'warning', [
    'context' => ['depth' => 3200],
]);
```

### `send(array $event)` — Raw PHP only

Low-level method for full control over the event payload.

```php
$pantree->send([
    'title'       => 'Payment timeout',
    'message'     => 'Stripe API did not respond within 10 s',
    'stack'       => (new \Exception)->getTraceAsString(),
    'level'       => 'error',
    'runtime'     => 'php-cli',
    'environment' => 'production',
    'user'        => ['id' => 42],
    'context'     => ['provider' => 'stripe'],
]);
```

### Event fields

| Field | Type | Description |
|---|---|---|
| `message` | string | Human-readable description (**required**) |
| `title` | string | Short error title / class name |
| `stack` | string | Stack trace |
| `level` | string | `error` / `warning` / `info` / `debug` |
| `runtime` | string | e.g. `php`, `php-cli`, `laravel` |
| `environment` | string | Deployment environment |
| `url` | string | `$_SERVER['REQUEST_URI']` or custom |
| `commit` | array | `['message' => ..., 'author' => ...]` |
| `user` | array | `['id' => ..., 'email' => ...]` |
| `context` | array | Any additional key-value pairs |

---

## Health Reporting

Collects OS, memory, disk, network, machine ID, container detection, and Git info — encrypted with **AES-256-GCM** before sending. The SDK stores the last health report timestamp and sends at most one report every 10 minutes by default, even if `sendHealthReport()` is called from Laravel `boot()` or another hot path.

### Raw PHP — cron script

```php
// cron-health.php
require __DIR__ . '/vendor/autoload.php';

use Pantree\PantreeClient;

$pantree = PantreeClient::fromDsn($_ENV['PANTREE_DSN']);
$result  = $pantree->sendHealthReport();

echo $result['status'] === 200 ? "OK\n" : "Failed\n";
```

Crontab:

```cron
*/10 * * * *  php /path/to/your/project/cron-health.php >> /var/log/pantree-health.log 2>&1
```

### Laravel — automatic scheduler

Set `PANTREE_HEALTH_REPORTING=true` in `.env` and make sure the Laravel scheduler runs:

```cron
* * * * *  www-data  php /var/www/html/artisan schedule:run >> /dev/null 2>&1
```

The service provider registers a scheduled task automatically — no manual setup needed.

---

## Package Layout

```
src/
  PantreeClient.php               ← core client (raw PHP + base for Laravel)
  Laravel/
    Pantree.php                   ← static facade wrapper (Laravel only)
    PantreeServiceProvider.php    ← auto-discovery, exception wiring, scheduler
config/
  pantree.php                     ← Laravel config (published via artisan)
examples/
  basic-usage.php                 ← raw PHP usage
  health-report.php               ← raw PHP cron health script
  laravel-exception-handler.php   ← custom Laravel exception handler
  laravel-health-command.php      ← custom Artisan health command
docs/
  api-reference.md
  getting-started.md
  health-reporting.md
```

---

## API Reference

See [`docs/api-reference.md`](./docs/api-reference.md).

---

## Changelog

See [`CHANGELOG.md`](./CHANGELOG.md).

---

## License

MIT
