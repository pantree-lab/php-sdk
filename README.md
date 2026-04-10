# @pantree/js

<p align="center">
  <img src="../../../arts/250x250_without_brand_title.png" alt="Pantree logo" width="88" />
</p>

Official JavaScript / Node.js SDK for [Pantree](https://pantree.dev) — lightweight error monitoring and encrypted health reporting.

Works in **Node.js 18+**, modern **browsers**, **Cloudflare Workers**, and any runtime that exposes the Web Crypto API.

---

## Table of Contents

- [Installation](#installation)
- [Quick Start](#quick-start)
- [Configuration](#configuration)
- [Capturing Errors](#capturing-errors)
- [Health Reporting](#health-reporting)
- [Framework Guides](#framework-guides)
- [API Reference](#api-reference)
- [Changelog](#changelog)

---

## Installation

```bash
npm install @pantree/js
# or
pnpm add @pantree/js
# or
yarn add @pantree/js
```

## Package Layout

- `index.js` - SDK runtime and exports
- `docs/` - API, setup, migration, and health reporting docs
- `examples/` - Node, Next.js, and browser integration examples
- `CHANGELOG.md` - release history

---

## Quick Start

Grab your DSN from **Project → Settings** in the Pantree dashboard.

```js
import Pantree from "@pantree/js";

Pantree.init({
  dsn: "https://API_KEY:INGEST_SECRET@your-pantree.com/api/ingest",
  environment: "production",
  healthReporting: true,   // send encrypted health reports every 30 min
});

// Capture an error
try {
  riskyOperation();
} catch (err) {
  Pantree.captureException(err);
}

// Capture a message
Pantree.captureMessage("User hit rate limit", { level: "warning" });
```

---

## Configuration

| Option | Type | Default | Description |
|---|---|---|---|
| `dsn` | `string` | **required** | Project DSN from the Pantree dashboard |
| `environment` | `string` | `"production"` | Deployment environment tag |
| `release` | `string` | `null` | Release / version string |
| `debug` | `boolean` | `false` | Log SDK activity to the console |
| `healthReporting` | `boolean \| { interval?: number }` | `false` | Enable periodic encrypted health reports. Pass `{ interval: ms }` to customise the interval (default 30 min) |

### DSN format

```
https://<apiKey>:<ingestSecret>@<host>/api/ingest
```

---

## Capturing Errors

### `captureException(err, extra?)`

```js
import Pantree from "@pantree/js";

Pantree.captureException(new Error("Payment failed"), {
  level: "error",
  user: { id: "usr_123", email: "alice@example.com" },
  context: { orderId: "ord_456", amount: 99.99 },
});
```

### `captureMessage(message, extra?)`

```js
Pantree.captureMessage("Slow query detected", {
  level: "warning",
  context: { queryMs: 4200 },
});
```

### Event fields

| Field | Type | Description |
|---|---|---|
| `message` | `string` | Human-readable description |
| `title` | `string` | Short error title / name |
| `stack` | `string` | Stack trace string |
| `level` | `"error" \| "warning" \| "info" \| "debug"` | Severity |
| `environment` | `string` | Override the global environment |
| `runtime` | `string` | e.g. `"node"`, `"browser"`, `"edge"` |
| `url` | `string` | URL where the error occurred |
| `commit` | `object` | `{ message, author, hash }` |
| `user` | `object` | `{ id, email, name }` |
| `breadcrumbs` | `array` | Ordered list of events leading up to the error |
| `context` | `object` | Any additional key-value pairs |

---

## Health Reporting

When `healthReporting: true` is set in `init()`, the SDK sends an **AES-256-GCM encrypted** system snapshot to `/api/health-report` every 30 minutes. Only super-admins on your Pantree instance can decrypt and read it.

**What is collected (Node.js):**

| Category | Fields |
|---|---|
| OS | platform, release, arch, hostname, uptime |
| Memory | total GB, free GB, used % |
| Disk | total GB, free GB, used % |
| Network | local IP, public IP (from your own `/api/ip`), MAC address, interface name |
| Machine | machine ID, container/VM detection |
| Git | username, email, commit hash, branch, tag, repo URL |
| Meta | SDK version, Node version, timestamp |

```js
// Manual one-off report
await Pantree.sendHealthReport();

// Custom interval — every 15 minutes
Pantree.init({
  dsn: "...",
  healthReporting: { interval: 15 * 60 * 1000 },
});

// Stop the reporter (e.g. on graceful shutdown)
Pantree.stopHealthReporter();
```

> **Privacy note:** No data is stored in plain text. The entire payload is encrypted client-side before leaving the machine.

---

## Framework Guides

### Next.js

See [`examples/next-js.js`](./examples/next-js.js) for a full `instrumentation.ts` + error boundary setup.

### Express / Node HTTP

See [`examples/node-express.js`](./examples/node-express.js).

### Browser (vanilla)

See [`examples/browser.html`](./examples/browser.html).

---

## API Reference

Full API reference: [`docs/api-reference.md`](./docs/api-reference.md)

---

## Changelog

See [`CHANGELOG.md`](./CHANGELOG.md).

---

## License

MIT
