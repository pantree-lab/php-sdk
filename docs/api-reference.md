# API Reference — pantree/pantree-php

---

## `PantreeClient` — Raw PHP

### `PantreeClient::fromDsn(string $dsn, string $environment = 'production', bool $debug = false): self` _(static)_

Preferred constructor. Parses a DSN and returns a configured client.

```php
$pantree = PantreeClient::fromDsn(
    dsn:         'https://pk_xxx:sk_xxx@your-pantree.com/api/ingest',
    environment: 'production',
    debug:       false,
);
```

---

### `new PantreeClient(string $endpoint, string $projectKey, string $ingestSecret, string $environment = 'production', bool $debug = false)`

Legacy constructor for backward compatibility.

---

### `captureException(\Throwable $e, array $extra = []): array`

Captures a throwable and sends it to the ingest endpoint.

```php
$pantree->captureException($e);
$pantree->captureException($e, ['user' => ['id' => 1, 'email' => 'alice@example.com']]);
```

Returns `['status' => int, 'body' => array]`.

---

### `captureMessage(string $message, string $level = 'info', array $extra = []): array`

Captures an arbitrary message.

```php
$pantree->captureMessage('Cache miss rate high', 'warning');
```

Valid levels: `error`, `warning`, `info`, `debug`.

---

### `send(array $event): array`

Low-level method. Builds and signs the request, returns the raw response.

```php
$pantree->send([
    'message' => 'Something happened',
    'level'   => 'info',
    'context' => ['key' => 'value'],
]);
```

**Event fields:**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `message` | string | Yes | Human-readable description |
| `title` | string | No | Short error title / exception class |
| `stack` | string | No | Stack trace string |
| `level` | string | No | `error` / `warning` / `info` / `debug` |
| `runtime` | string | No | e.g. `php`, `php-cli`, `laravel` |
| `environment` | string | No | Overrides the configured environment |
| `url` | string | No | Request URL (auto-detected from `$_SERVER`) |
| `commit` | array | No | `['message' => ..., 'author' => ...]` |
| `user` | array | No | `['id' => ..., 'email' => ...]` |
| `breadcrumbs` | array | No | Ordered list of recent events |
| `context` | array | No | Any additional key-value pairs |

---

### `sendHealthReport(): array`

Collects system info, encrypts it with AES-256-GCM, and posts to `/api/health-report`.

```php
$result = $pantree->sendHealthReport();
// ['status' => 200, 'body' => ['success' => true]]
```

Collected data: OS, memory, disk, local/public IP, machine ID, container detection, Git info.

---

## `Pantree` — Laravel Facade

All methods are static. The underlying `PantreeClient` is lazily instantiated from config on first call.

### `Pantree::captureException(\Throwable $e, array $extra = []): array`

```php
use Pantree\Laravel\Pantree;

Pantree::captureException($e);
Pantree::captureException($e, [
    'user'    => ['id' => auth()->id(), 'email' => auth()->user()?->email],
    'context' => ['orderId' => $order->id],
]);
```

---

### `Pantree::captureMessage(string $message, string $level = 'info', array $extra = []): array`

```php
Pantree::captureMessage('Slow queue job detected', 'warning', [
    'context' => ['job' => ProcessPayment::class, 'durationMs' => 8500],
]);
```

---

### `Pantree::sendHealthReport(): array`

Manually send one health report. Useful in custom Artisan commands or scheduled tasks.

```php
Pantree::sendHealthReport();
```

---

### `Pantree::reset(): void`

Clears the internal singleton. Use in tests to reset state between test cases.

```php
Pantree::reset();
```

---

## `PantreeServiceProvider` — Laravel

Auto-discovered via `composer.json`. No manual registration required.

**`register()`** — merges `config/pantree.php` defaults.

**`boot()`**:
- Registers `ExceptionHandler::reportable()` to automatically capture every exception that passes through Laravel's exception handler.
- When `health_reporting = true`, registers a `Schedule::call()` every 30 minutes via `callAfterResolving(Schedule::class)`.

### Config keys (`config/pantree.php`)

| Key | Env var | Default | Description |
|-----|---------|---------|-------------|
| `dsn` | `PANTREE_DSN` | `''` | Full DSN string (preferred) |
| `endpoint` | `PANTREE_ENDPOINT` | — | Used when DSN is empty |
| `project_key` | `PANTREE_PROJECT_KEY` | `''` | Used when DSN is empty |
| `ingest_secret` | `PANTREE_INGEST_SECRET` | `''` | Used when DSN is empty |
| `environment` | `PANTREE_ENVIRONMENT` | `app()->environment()` | Deployment environment |
| `health_reporting` | `PANTREE_HEALTH_REPORTING` | `false` | Auto-schedule health reports |
| `debug` | `PANTREE_DEBUG` | `false` | Log debug info via `error_log` |
