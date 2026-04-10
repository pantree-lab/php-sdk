# Changelog

All notable changes to `@pantree/js` are documented here.

Format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).  
Versioning follows [Semantic Versioning](https://semver.org/).

---

## [1.1.0] — 2026-04-08

### Added
- **Health reporting** — `healthReporting: true | { interval }` option in `init()` enables periodic AES-256-GCM encrypted system snapshots sent to `/api/health-report`.
- `sendHealthReport()` — public method to trigger a one-off health report.
- `stopHealthReporter()` — stops the periodic timer on graceful shutdown.
- `encryptHealth` named export for custom encryption workflows.
- Public IP resolution now uses your own Pantree server's `/api/ip` endpoint instead of a third-party service.
- Node.js system info collection: OS, RAM, disk, network interfaces, machine ID, container detection, and Git trace — all dependency-free using built-in `os` and `fs` modules.
- DSN parser now also derives `healthEndpoint` and `ipEndpoint` from the same host.

### Changed
- Singleton version bumped to `1.1.0`.
- `parseDsn` now returns `healthEndpoint` and `ipEndpoint` alongside `endpoint`.

---

## [0.1.0] — 2026-01-01

### Added
- Initial release.
- DSN-based initialisation (`Pantree.init({ dsn })`).
- `captureException(err, extra?)` — capture `Error` objects.
- `captureMessage(message, extra?)` — capture arbitrary messages.
- HMAC-SHA-256 request signing for the ingest endpoint.
- Legacy `sendPantreeEvent()` export for backward compatibility.
- Named exports: `PantreeClient`, `parseDsn`, `createPantreeSignature`.
