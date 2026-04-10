# Getting Started — @pantree/js

## Prerequisites

| Requirement | Version |
|---|---|
| Node.js | ≥ 18 |
| A Pantree instance | self-hosted or cloud |

## 1. Install

```bash
npm install @pantree/js
```

## 2. Get your DSN

1. Open your Pantree dashboard.
2. Navigate to **Projects → [your project] → Settings**.
3. Copy the **DSN** — it looks like:
   ```
   https://pk_abc123:sk_xyz789@your-pantree.com/api/ingest
   ```

## 3. Initialise

```js
// instrumentation.js  (or app entry point)
import Pantree from "@pantree/js";

Pantree.init({
  dsn: process.env.PANTREE_DSN,
  environment: process.env.NODE_ENV ?? "production",
  release: process.env.npm_package_version,
  debug: process.env.NODE_ENV === "development",
});
```

Store your DSN in an environment variable — never hard-code secrets.

```env
# .env
PANTREE_DSN=https://pk_abc123:sk_xyz789@your-pantree.com/api/ingest
```

## 4. Capture your first error

```js
try {
  await processPayment(order);
} catch (err) {
  await Pantree.captureException(err, {
    user:    { id: session.userId },
    context: { orderId: order.id },
  });
  throw err; // re-throw if needed
}
```

## 5. (Optional) Enable health reporting

```js
Pantree.init({
  dsn: process.env.PANTREE_DSN,
  healthReporting: true,   // sends an encrypted report every 30 minutes
});
```

See [health-reporting.md](./health-reporting.md) for details.

## Next steps

- [API Reference](./api-reference.md)
- [Health Reporting](./health-reporting.md)
- [Migration Guide](./migration.md)
