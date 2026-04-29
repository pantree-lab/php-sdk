<?php

namespace Pantree;

/**
 * Pantree PHP SDK  v1.1.0
 *
 * Usage (DSN-based, recommended):
 *   $pantree = PantreeClient::fromDsn('https://API_KEY:SECRET@your-pantree.com/api/ingest');
 *   $pantree->captureException($e);
 *   $pantree->sendHealthReport();   // call from a cron every 30 min
 *
 * Legacy constructor also supported:
 *   $pantree = new PantreeClient($endpoint, $projectKey, $ingestSecret);
 */
class PantreeClient
{
    private string $endpoint;
    private string $healthEndpoint;
    private string $ipEndpoint;
    private string $projectKey;
    private string $ingestSecret;
    private string $environment;
    private bool   $debug;

    // ---------------------------------------------------------------- constructor

    public function __construct(
        string $endpoint,
        string $projectKey,
        string $ingestSecret,
        string $environment = 'production',
        bool   $debug       = false
    ) {
        $this->endpoint      = rtrim($endpoint, '/');
        $this->projectKey    = $projectKey;
        $this->ingestSecret  = $ingestSecret;
        $this->environment   = $environment;
        $this->debug         = $debug;

        // Derive companion endpoints from ingest endpoint base
        $base = (string) preg_replace('#/api/ingest$#', '', $this->endpoint);
        $this->healthEndpoint = $base . '/api/health-report';
        $this->ipEndpoint     = $base . '/api/ip';
    }

    /** Construct from a DSN string: https://apiKey:ingestSecret@host/api/ingest */
    public static function fromDsn(
        string $dsn,
        string $environment = 'production',
        bool   $debug       = false
    ): self {
        $parts = parse_url($dsn);
        if (!$parts || empty($parts['user']) || empty($parts['pass'])) {
            throw new \InvalidArgumentException('[Pantree] Invalid DSN — must contain API key and ingest secret');
        }
        $scheme   = $parts['scheme'] ?? 'https';
        $host     = $parts['host']   ?? '';
        $port     = isset($parts['port']) ? ':' . $parts['port'] : '';
        $path     = $parts['path']   ?? '/api/ingest';
        $endpoint = "{$scheme}://{$host}{$port}{$path}";
        return new self($endpoint, urldecode($parts['user']), urldecode($parts['pass']), $environment, $debug);
    }

    // ---------------------------------------------------------------- error capture

    /** Capture a Throwable and send it to Pantree. */
    public function captureException(\Throwable $e, array $extra = []): array
    {
        return $this->send(array_merge([
            'title'   => get_class($e),
            'message' => $e->getMessage(),
            'stack'   => $e->getTraceAsString(),
            'level'   => 'error',
        ], $extra));
    }

    /** Capture an arbitrary message. */
    public function captureMessage(string $message, string $level = 'info', array $extra = []): array
    {
        return $this->send(array_merge(['message' => $message, 'level' => $level], $extra));
    }

    /** Low-level send method. */
    public function send(array $event): array
    {
        $payload = json_encode(array_merge([
            'message'     => $event['message'] ?? '',
            'title'       => $event['title']       ?? null,
            'stack'       => $event['stack']       ?? null,
            'level'       => $event['level']       ?? 'error',
            'runtime'     => $event['runtime']     ?? 'php',
            'environment' => $event['environment'] ?? $this->environment,
            'url'         => $event['url']         ?? ($_SERVER['REQUEST_URI'] ?? null),
            'commit'      => $event['commit']      ?? null,
            'user'        => $event['user']        ?? null,
            'breadcrumbs' => $event['breadcrumbs'] ?? null,
            'context'     => $event['context']     ?? null,
        ]), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if ($payload === false) {
            throw new \RuntimeException('[Pantree] Failed to encode payload');
        }

        $signature = hash_hmac('sha256', $payload, $this->ingestSecret);
        return $this->http($this->endpoint, $payload, $signature);
    }

    // ---------------------------------------------------------------- health reporting

    /**
     * Collect system + git information, encrypt it with AES-256-GCM,
     * and POST to /api/health-report.
     *
     * Call this from a cron job every 30 minutes:
     *   * / 30 * * * *   php artisan pantree:health   (Laravel)
     *   * / 30 * * * *   php /path/to/health.php     (plain PHP)
     */
    public function sendHealthReport(): array
    {
        $health = $this->collectHealth();
        $reportedAt = $health['meta']['reportedAt'] ?? date('c');

        ['iv' => $iv, 'ciphertext' => $ciphertext] = $this->encryptHealth($health);

        $payload = json_encode([
            'iv'         => $iv,
            'ciphertext' => $ciphertext,
            'reportedAt' => $reportedAt,
        ], JSON_UNESCAPED_SLASHES);

        if ($payload === false) {
            throw new \RuntimeException('[Pantree] Failed to encode health payload');
        }

        if ($this->debug) {
            error_log('[Pantree] Sending health report to ' . $this->healthEndpoint);
        }

        return $this->httpHealth($this->healthEndpoint, $payload);
    }

    // ---------------------------------------------------------------- health data collection

    private function collectHealth(): array
    {
        $reportedAt = (new \DateTime())->format('Y-m-d\TH:i:s\Z');

        // OS info
        $os = [
            'platform' => PHP_OS_FAMILY,
            'release'  => php_uname('r'),
            'arch'     => php_uname('m'),
            'hostname' => gethostname() ?: '',
            'uptime'   => $this->getUptime(),
        ];

        // Memory (from /proc/meminfo on Linux; fallback to PHP process memory)
        $memory = $this->getMemoryInfo();

        // Disk
        $totalDisk = @disk_total_space('/') ?: 0;
        $freeDisk  = @disk_free_space('/')  ?: 0;
        $storage   = $totalDisk > 0 ? [
            'totalGb'     => round($totalDisk / 1e9, 2),
            'freeGb'      => round($freeDisk  / 1e9, 2),
            'usedPercent' => round((($totalDisk - $freeDisk) / $totalDisk) * 100, 1),
        ] : null;

        // Network
        $localIp  = gethostbyname(gethostname() ?: 'localhost');
        $publicIp = $this->fetchPublicIp($this->ipEndpoint);

        $network = [
            'localIp'  => $localIp !== gethostname() ? $localIp : '',
            'publicIp' => $publicIp,
        ];

        // Container / VM detection
        $isContainer = file_exists('/.dockerenv')
            || !empty(getenv('KUBERNETES_SERVICE_HOST'))
            || !empty(getenv('container'));

        $machineId = '';
        if (file_exists('/etc/machine-id')) {
            $machineId = trim((string) @file_get_contents('/etc/machine-id'));
        }

        $machine = [
            'id'          => $machineId ?: null,
            'isContainer' => $isContainer,
        ];

        // Git
        $git = [
            'username'   => $this->git('git config user.name'),
            'email'      => $this->git('git config user.email'),
            'commitHash' => $this->git('git rev-parse HEAD'),
            'branch'     => $this->git('git rev-parse --abbrev-ref HEAD'),
            'tag'        => $this->git('git describe --tags --abbrev=0'),
            'repoUrl'    => $this->git('git remote get-url origin'),
        ];

        $result = [
            'os'      => $os,
            'network' => $network,
            'machine' => $machine,
            'git'     => $git,
            'meta'    => [
                'sdkVersion' => '1.1.0',
                'phpVersion' => PHP_VERSION,
                'reportedAt' => $reportedAt,
            ],
        ];

        if ($memory)  $result['memory']  = $memory;
        if ($storage) $result['storage'] = $storage;

        return $result;
    }

    private function getUptime(): int
    {
        if (PHP_OS_FAMILY === 'Linux' && file_exists('/proc/uptime')) {
            $data = @file_get_contents('/proc/uptime');
            if ($data !== false) {
                return (int) explode(' ', $data)[0];
            }
        }
        return 0;
    }

    private function getMemoryInfo(): ?array
    {
        if (PHP_OS_FAMILY === 'Linux' && file_exists('/proc/meminfo')) {
            $info = @file_get_contents('/proc/meminfo');
            if ($info !== false) {
                preg_match('/MemTotal:\s+(\d+)\s+kB/', $info, $total);
                preg_match('/MemAvailable:\s+(\d+)\s+kB/', $info, $avail);
                if ($total && $avail) {
                    $totalBytes = (int)$total[1] * 1024;
                    $freeBytes  = (int)$avail[1] * 1024;
                    return [
                        'totalGb'     => round($totalBytes / 1e9, 2),
                        'freeGb'      => round($freeBytes  / 1e9, 2),
                        'usedPercent' => round((($totalBytes - $freeBytes) / $totalBytes) * 100, 1),
                    ];
                }
            }
        }
        // Fallback: PHP process memory only
        $used  = memory_get_usage(true);
        $limit = $this->parseMemoryLimit(ini_get('memory_limit'));
        if ($limit > 0) {
            return [
                'totalGb'     => round($limit / 1e9, 2),
                'freeGb'      => round(($limit - $used) / 1e9, 2),
                'usedPercent' => round(($used / $limit) * 100, 1),
            ];
        }
        return null;
    }

    private function parseMemoryLimit(string $val): int
    {
        $val = trim($val);
        $last = strtolower($val[-1] ?? '');
        $num  = (int) $val;
        if ($last === 'g') return $num * 1024 * 1024 * 1024;
        if ($last === 'm') return $num * 1024 * 1024;
        if ($last === 'k') return $num * 1024;
        return $num;
    }

    private function fetchPublicIp(string $url): string
    {
        try {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 3,
                CURLOPT_CONNECTTIMEOUT => 2,
            ]);
            $ip = (string) curl_exec($ch);
            curl_close($ch);
            return filter_var(trim($ip), FILTER_VALIDATE_IP) ? trim($ip) : '';
        } catch (\Throwable) {
            return '';
        }
    }

    private function git(string $cmd): string
    {
        try {
            $out = @shell_exec($cmd . ' 2>/dev/null');
            return $out !== null ? trim($out) : '';
        } catch (\Throwable) {
            return '';
        }
    }

    // ---------------------------------------------------------------- AES-256-GCM encryption

    /**
     * Encrypt health data using AES-256-GCM with a key derived via HKDF-SHA-256.
     * The output matches what the Pantree server expects:
     *   { iv: base64(12 bytes), ciphertext: base64(ciphertext + 16-byte tag) }
     *
     * Requires: PHP 7.1+ (openssl), hash_hkdf (PHP 7.1.2+)
     */
    private function encryptHealth(array $data): array
    {
        // HKDF key derivation — same parameters as JS/server side
        $key = hash_hkdf('sha256', $this->ingestSecret, 32, 'health-key', 'pantree-health-v1', true);

        // 12-byte random IV
        $iv = random_bytes(12);

        // AES-256-GCM encryption; $tag receives the 16-byte auth tag
        $tag = '';
        $ciphertext = openssl_encrypt(
            json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'aes-256-gcm',
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,      // out: 16-byte auth tag
            '',        // additional authenticated data (none)
            16         // tag length
        );

        if ($ciphertext === false) {
            throw new \RuntimeException('[Pantree] AES-GCM encryption failed: ' . openssl_error_string());
        }

        // Web Crypto API expects tag appended to ciphertext — match that format
        return [
            'iv'         => base64_encode($iv),
            'ciphertext' => base64_encode($ciphertext . $tag),
        ];
    }

    // ---------------------------------------------------------------- HTTP helpers

    private function http(string $url, string $payload, string $signature): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'x-pantree-key: '       . $this->projectKey,
                'x-pantree-signature: ' . $signature,
            ],
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
        ]);

        $response   = curl_exec($ch);
        $error      = curl_error($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false) {
            throw new \RuntimeException('[Pantree] Request failed: ' . $error);
        }

        return ['status' => $statusCode, 'body' => json_decode((string) $response, true)];
    }

    private function httpHealth(string $url, string $payload): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'x-pantree-key: ' . $this->projectKey,
            ],
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
        ]);

        $response   = curl_exec($ch);
        $error      = curl_error($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false) {
            if ($this->debug) error_log('[Pantree] Health report failed: ' . $error);
            return ['status' => 0, 'body' => null];
        }

        return ['status' => $statusCode, 'body' => json_decode((string) $response, true)];
    }
}
