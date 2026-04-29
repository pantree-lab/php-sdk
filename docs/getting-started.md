# Getting Started — pantree/pantree-php

## Requirements

| | Minimum |
|---|---|
| PHP | 8.1 |
| Extensions | `ext-curl`, `ext-openssl` |
| Pantree | self-hosted or cloud |

## 1. Install

```bash
composer require pantree/pantree-php
```

## 2. Get your DSN

1. Open the Pantree dashboard.
2. Go to **Projects → [your project] → Settings**.
3. Copy the **DSN**:
   ```
   https://pk_abc123:sk_xyz789@your-pantree.com/api/ingest
   ```

## 3. Configure

Store the DSN in your environment — never commit secrets:

```env
# .env
PANTREE_DSN=https://pk_abc123:sk_xyz789@your-pantree.com/api/ingest
```

Load it with your preferred method (e.g. `vlucas/phpdotenv`, a framework `.env` loader, or server environment variables).

## 4. Initialise and capture

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use Pantree\PantreeClient;

$pantree = PantreeClient::fromDsn($_ENV['PANTREE_DSN']);

set_exception_handler(function (\Throwable $e) use ($pantree) {
    $pantree->captureException($e);
});

// Your app code…
```

## 5. Add health reporting (optional)

Create a dedicated cron script:

```php
<?php
// cron-health.php
require __DIR__ . '/vendor/autoload.php';

use Pantree\PantreeClient;

$pantree = PantreeClient::fromDsn($_ENV['PANTREE_DSN']);
$pantree->sendHealthReport();
```

Register it in crontab:

```cron
*/30 * * * *  php /var/www/html/cron-health.php
```

## Next steps

- [API Reference](./api-reference.md)
- [Health Reporting](./health-reporting.md)
