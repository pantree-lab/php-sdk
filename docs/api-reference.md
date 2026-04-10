# API Reference — @pantree/js

## Default export — `Pantree` singleton

The recommended way to use the SDK. One shared `PantreeClient` instance for your entire application.

```js
import Pantree from "@pantree/js";
```

---

### `Pantree.init(options)`

Initialises the client. Must be called before any other method.

```ts
Pantree.init({
  dsn:             string,                          // required
  environment?:    string,                          // default: "production"
  release?:        string,                          // default: null
  debug?:          boolean,                         // default: false
  healthReporting?: boolean | { interval?: number } // default: false
})
```

Immediately sends an initial health report and starts the interval timer when `healthReporting` is truthy.

---

### `Pantree.captureException(err, extra?)`

Captures a thrown `Error` (or any value) and sends it to the ingest endpoint.

```js
await Pantree.captureException(err);
await Pantree.captureException(err, {
  level:   "error",
  user:    { id: "usr_1", email: "alice@example.com" },
  context: { page: "/checkout" },
});
```

Returns the server response JSON, or `null` on network failure.

---

### `Pantree.captureMessage(message, extra?)`

Captures an arbitrary string message.

```js
await Pantree.captureMessage("Rate limit hit", { level: "warning" });
```

---

### `Pantree.sendHealthReport()`

Immediately collects system information, encrypts it, and sends it to `/api/health-report`.

```js
const result = await Pantree.sendHealthReport();
// { success: true }
```

Returns the server response JSON, or `null` on failure.

---

### `Pantree.stopHealthReporter()`

Clears the periodic health report timer. Call on graceful shutdown.

```js
Pantree.stopHealthReporter();
```

---

## Named exports

### `PantreeClient`

The underlying class. Use when you need multiple isolated instances.

```js
import { PantreeClient } from "@pantree/js";

const client = new PantreeClient();
client.init({ dsn: "..." });
await client.captureException(err);
```

---

### `parseDsn(dsn)`

Parses a DSN string into its components.

```js
import { parseDsn } from "@pantree/js";

const { apiKey, ingestSecret, endpoint, healthEndpoint, ipEndpoint } = parseDsn(dsn);
```

---

### `createPantreeSignature(payload, secret)`

Computes the HMAC-SHA-256 hex signature used in the `x-pantree-signature` request header.

```js
import { createPantreeSignature } from "@pantree/js";

const sig = await createPantreeSignature(JSON.stringify(payload), ingestSecret);
```

---

### `encryptHealth(data, ingestSecret)`

Encrypts an arbitrary object with AES-256-GCM using a key derived from `ingestSecret`.

```js
import { encryptHealth } from "@pantree/js";

const { iv, ciphertext } = await encryptHealth({ foo: "bar" }, ingestSecret);
```

---

### `sendPantreeEvent({ endpoint, projectKey, ingestSecret, event })` _(deprecated)_

Legacy function kept for backward compatibility. Use `Pantree.init()` + `captureException()` instead.
