# Health Reporting — @pantree/js

Health reporting allows your Pantree server to monitor every running instance of your application — OS, memory, disk, network, and Git state — without exposing sensitive data to anyone other than super-admins.

## How it works

```
SDK                              Pantree Server
 │                                    │
 ├─ collect system info               │
 ├─ HKDF-SHA-256(ingestSecret) ──→ AES-256-GCM key
 ├─ AES-GCM encrypt(payload)         │
 └─ POST /api/health-report ────────→ store { iv, ciphertext }
                                      │
                          Super-admin requests Health Monitor
                                      │
                          server decrypts with same derived key
                                      │
                          displays OS, RAM, Git info, etc.
```

All encryption happens **on the SDK side** before the payload leaves the machine. The server stores only ciphertext.

## Enabling

```js
Pantree.init({
  dsn: "https://API_KEY:SECRET@your-pantree.com/api/ingest",
  healthReporting: true,          // every 30 minutes
});

// Custom interval (milliseconds)
Pantree.init({
  dsn: "...",
  healthReporting: { interval: 10 * 60 * 1000 },   // every 10 minutes
});
```

The first report is sent **immediately** on `init()`, then on the configured interval.

## Manual trigger

```js
const result = await Pantree.sendHealthReport();
console.log(result);  // { success: true }
```

## Graceful shutdown

```js
process.on("SIGTERM", () => {
  Pantree.stopHealthReporter();
  process.exit(0);
});
```

## Collected data

### Node.js

| Category | Fields |
|---|---|
| **OS** | platform, kernel release, architecture, hostname, uptime (seconds) |
| **Memory** | total GB, free GB, used % (read from `/proc/meminfo` on Linux) |
| **Disk** | total GB, free GB, used % (root filesystem) |
| **Network** | local IP, public IP (via `/api/ip` on your server), MAC, interface |
| **Machine** | `/etc/machine-id`, container/VM detection (`DOCKER_CONTAINER`, `KUBERNETES_SERVICE_HOST`) |
| **Git** | username, email, commit hash, branch, tag, remote URL |
| **Meta** | SDK version, Node version, ISO timestamp |

### Browser

In a browser context the SDK collects only `navigator.platform` and timestamp. Full system info is Node.js only.

## Cryptography details

| Property | Value |
|---|---|
| Key derivation | HKDF-SHA-256 |
| Salt | `"pantree-health-v1"` (UTF-8) |
| Info | `"health-key"` (UTF-8) |
| Output key | AES-256-GCM, 256-bit |
| IV | 12 random bytes (per report) |
| Auth tag | 16 bytes (appended to ciphertext) |
| Encoding | Base64 |
| API | Web Crypto (`crypto.subtle`) — no dependencies |
