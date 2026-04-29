# Changelog — pantree/pantree-php

All notable changes are documented here.

Format: [Keep a Changelog](https://keepachangelog.com/en/1.0.0/) · Versioning: [SemVer](https://semver.org/)

---

## [2.0.0] — 2026-04-29

### Added
- **Laravel integration merged into this package** — `pantree/pantree-laravel` is now retired; all Laravel support lives here.
- `src/Laravel/Pantree.php` — static facade wrapper (`Pantree::captureException`, `::captureMessage`, `::sendHealthReport`, `::reset`).
- `src/Laravel/PantreeServiceProvider.php` — auto-discovered service provider; registers exception capture via `ExceptionHandler::reportable()` and optional 30-minute health report scheduler.
- `config/pantree.php` — publishable Laravel config (`PANTREE_DSN`, `PANTREE_ENVIRONMENT`, `PANTREE_HEALTH_REPORTING`, `PANTREE_DEBUG`).
- Laravel package auto-discovery via `extra.laravel.providers` in `composer.json`.
- Examples: `laravel-exception-handler.php`, `laravel-health-command.php`.

### Changed
- `composer.json` now suggests `illuminate/support` and `illuminate/http` instead of requiring them — raw PHP users have zero Laravel dependencies.
- PSR-4 autoload covers both `Pantree\` (core) and `Pantree\Laravel\` (Laravel) under the single `src/` root.

### Migration from `pantree/pantree-laravel`

Replace in `composer.json`:
```json
"pantree/pantree-laravel": "^1.1"
```
with:
```json
"pantree/pantree-php": "^2.0"
```

All import paths remain identical — `use Pantree\Laravel\Pantree;` and `use Pantree\Laravel\PantreeServiceProvider;` are unchanged.

---

## [1.1.0] — 2026-04-08

### Added
- `PantreeClient::fromDsn(string $dsn, ...)` — static DSN constructor.
- `sendHealthReport()` — AES-256-GCM encrypted health snapshot.
- `encryptHealth(array $data)` — internal HKDF + AES-GCM encryption.
- System info collection: OS, memory, disk, network, machine ID, container detection, Git trace.
- `$ipEndpoint` derived from ingest endpoint base.
- `ext-openssl` declared as a required extension.

### Changed
- Constructor derives `$healthEndpoint` and `$ipEndpoint` from the ingest endpoint base path.

---

## [1.0.0] — 2026-01-01

### Added
- Initial release.
- `PantreeClient` with `send(array $event)`.
- HMAC-SHA-256 request signing.
- cURL-based HTTP transport.
