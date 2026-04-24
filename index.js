/**
 * Pantree JavaScript SDK  v1.1.0
 *
 * Usage:
 *   import Pantree from "@pantree/js";
 *   Pantree.init({
 *     dsn: "https://API_KEY:INGEST_SECRET@your-pantree.com/api/ingest",
 *     environment: "production",
 *     healthReporting: true,   // enable encrypted health reports every 30 min
 *   });
 *
 * DSN format:  https://<apiKey>:<ingestSecret>@<host>/api/ingest
 */

/* ================================================================== DSN */

function parseDsn(dsn) {
    const url = new URL(dsn);
    if (!url.username) throw new Error("[Pantree] DSN is missing the API key");
    if (!url.password) throw new Error("[Pantree] DSN is missing the ingest secret");
    const base = `${url.protocol}//${url.host}`;
    return {
        apiKey:         decodeURIComponent(url.username),
        ingestSecret:   decodeURIComponent(url.password),
        endpoint:       `${base}/api/ingest`,
        healthEndpoint: `${base}/api/health-report`,
        ipEndpoint:     `${base}/api/ip`,
    };
}

/* ================================================================== HMAC signature (for error ingest) */

async function createSignature(payload, secret) {
    const enc = new TextEncoder();
    const key = await crypto.subtle.importKey(
        "raw", enc.encode(secret),
        { name: "HMAC", hash: "SHA-256" },
        false, ["sign"],
    );
    const sig = await crypto.subtle.sign("HMAC", key, enc.encode(payload));
    return [...new Uint8Array(sig)].map(b => b.toString(16).padStart(2, "0")).join("");
}

/* ================================================================== AES-GCM encryption (for health reports) */

const HEALTH_SALT = new TextEncoder().encode("pantree-health-v1");
const HEALTH_INFO = new TextEncoder().encode("health-key");
const SDK_NAME = "@pantree-lab/javascript-sdk";
const SDK_VERSION = "0.1.13";

async function loadAppPackageMap() {
    try {
        const fs = await import("node:fs");
        const path = await import("node:path");
        const seen = new Set();
        const candidates = [];
        if (process.env.npm_package_json) candidates.push(process.env.npm_package_json);
        if (process.env.INIT_CWD) candidates.push(path.join(process.env.INIT_CWD, "package.json"));
        candidates.push(path.join(process.cwd(), "package.json"));

        let walk = process.cwd();
        for (let i = 0; i < 6; i++) {
            const candidate = path.join(walk, "package.json");
            candidates.push(candidate);
            const parent = path.dirname(walk);
            if (parent === walk) break;
            walk = parent;
        }

        for (const candidate of candidates) {
            if (!candidate || seen.has(candidate)) continue;
            seen.add(candidate);
            if (!fs.existsSync(candidate)) continue;

            const raw = fs.readFileSync(candidate, "utf8");
            const parsed = JSON.parse(raw);
            if (!parsed || typeof parsed !== "object") continue;

            const deps = parsed.dependencies && typeof parsed.dependencies === "object"
                ? parsed.dependencies
                : {};
            const devDeps =
                parsed.devDependencies && typeof parsed.devDependencies === "object"
                    ? parsed.devDependencies
                    : {};
            const merged = { ...deps, ...devDeps };
            const packageCount = Object.keys(merged).length;
            if (packageCount === 0) continue;

            const limitedEntries = Object.entries(merged).slice(0, 250);
            const installedPackages = Object.fromEntries(limitedEntries);
            if (packageCount > limitedEntries.length) {
                installedPackages.__truncated =
                    `true (${packageCount - limitedEntries.length} more)`;
            }
            return { installedPackages };
        }
    } catch {
        // ignore and fallback
    }

    return { installedPackages: {} };
}

async function deriveHealthKey(ingestSecret) {
    const raw = new TextEncoder().encode(ingestSecret);
    const km  = await crypto.subtle.importKey("raw", raw, { name: "HKDF" }, false, ["deriveKey"]);
    return crypto.subtle.deriveKey(
        { name: "HKDF", hash: "SHA-256", salt: HEALTH_SALT, info: HEALTH_INFO },
        km,
        { name: "AES-GCM", length: 256 },
        false,
        ["encrypt"],
    );
}

async function encryptHealth(data, ingestSecret) {
    const key       = await deriveHealthKey(ingestSecret);
    const iv        = crypto.getRandomValues(new Uint8Array(12));
    const plaintext = new TextEncoder().encode(JSON.stringify(data));
    const encrypted = await crypto.subtle.encrypt({ name: "AES-GCM", iv }, key, plaintext);
    const toB64     = buf => btoa(String.fromCharCode(...new Uint8Array(buf)));
    return { iv: toB64(iv.buffer), ciphertext: toB64(encrypted) };
}

/* ================================================================== System / git info collectors */

/** Detect Node.js runtime */
function isNode() {
    return typeof process !== "undefined" && !!process.versions?.node;
}

/** Run a shell command silently; return trimmed stdout or fallback */
function sh(cmd, fallback = "") {
    try {
        // dynamic require so bundlers don't try to include child_process in browser builds
        // biome-ignore lint/security/noGlobalEval: intentional dynamic require for Node-only path
        const exec = (0, eval)('require')('child_process').execSync;
        return exec(cmd, { encoding: "utf8", stdio: ["pipe", "pipe", "pipe"] }).trim();
    } catch {
        return fallback;
    }
}

function collectGitContext() {
    const rawLog = sh("git log -10 --format=%H%x00%an%x00%ae%x00%ai%x00%s");
    const commits = rawLog
        ? rawLog.split("\n").filter(Boolean).map(line => {
            const [hash, authorName, authorEmail, date, message] = line.split("\0");
            return { hash, authorName, authorEmail, date, message };
          })
        : [];

    return {
        username:    sh("git config user.name")             || undefined,
        email:       sh("git config user.email")            || undefined,
        branch:      sh("git rev-parse --abbrev-ref HEAD")  || undefined,
        repoUrl:     sh("git remote get-url origin")        || undefined,
        commitHash:  sh("git rev-parse HEAD")               || undefined,
        tag:         sh("git describe --tags --abbrev=0")   || undefined,
        commits:     commits.length > 0 ? commits : undefined,
    };
}

async function collectHealth(config) {
    const reportedAt = new Date().toISOString();

    if (!isNode()) {
        // Browser — collect what we can
        return {
            os:   { platform: navigator.platform ?? "browser" },
            meta: { sdkVersion: SDK_VERSION, reportedAt },
        };
    }

    // Node.js — use built-in os module + optional systeminformation
    const os = await import("os").catch(() => null);
    if (!os) return { meta: { sdkVersion: SDK_VERSION, reportedAt } };

    const totalMem = os.totalmem();
    const freeMem  = os.freemem();

    // Primary non-loopback IPv4 interface
    let localIp = "", mac = "", iface = "";
    const nets = os.networkInterfaces();
    for (const [name, addrs = []] of Object.entries(nets)) {
        const v4 = addrs.find(a => a.family === "IPv4" && !a.internal);
        if (v4) { localIp = v4.address; mac = v4.mac; iface = name; break; }
    }

    // Public IP — ask our own server (best-effort, non-blocking)
    let publicIp = "";
    try {
        const ctrl = new AbortController();
        const t = setTimeout(() => ctrl.abort(), 3000);
        publicIp = await fetch(config.ipEndpoint, { signal: ctrl.signal })
            .then(r => r.text()).finally(() => clearTimeout(t));
        publicIp = publicIp.trim();
    } catch { /* ignore */ }

    // Machine ID
    let machineId = "";
    try {
        const fs = await import("fs");
        machineId = fs.readFileSync("/etc/machine-id", "utf8").trim();
    } catch { /* not Linux or no permission */ }

    // Disk (first non-tmpfs mount)
    let totalGbDisk = 0, freeGbDisk = 0, usedPctDisk = 0;
    try {
        const fs = await import("fs");
        const stat = fs.statfsSync ? fs.statfsSync("/") : null;
        if (stat) {
            totalGbDisk = +((stat.blocks * stat.bsize) / 1e9).toFixed(2);
            freeGbDisk  = +((stat.bfree  * stat.bsize) / 1e9).toFixed(2);
            usedPctDisk = +(((stat.blocks - stat.bfree) / stat.blocks) * 100).toFixed(1);
        }
    } catch { /* statfs not available in this Node version */ }

    // Container / VM detection
    const isContainer = !!(
        process.env.container ||
        process.env.KUBERNETES_SERVICE_HOST ||
        process.env.DOCKER_CONTAINER
    );

    return {
        os: {
            platform: os.platform(),
            release:  os.release(),
            arch:     os.arch(),
            hostname: os.hostname(),
            uptime:   Math.floor(os.uptime()),
        },
        memory: {
            totalGb:     +((totalMem) / 1e9).toFixed(2),
            freeGb:      +((freeMem)  / 1e9).toFixed(2),
            usedPercent: +(((totalMem - freeMem) / totalMem) * 100).toFixed(1),
        },
        ...(totalGbDisk > 0 ? {
            storage: { totalGb: totalGbDisk, freeGb: freeGbDisk, usedPercent: usedPctDisk },
        } : {}),
        network: { localIp, publicIp, mac, iface },
        machine: { id: machineId || undefined, isContainer },
        git: {
            username:   sh("git config user.name"),
            email:      sh("git config user.email"),
            commitHash: sh("git rev-parse HEAD"),
            branch:     sh("git rev-parse --abbrev-ref HEAD"),
            tag:        sh("git describe --tags --abbrev=0"),
            repoUrl:    sh("git remote get-url origin"),
        },
        meta: {
            sdkVersion: SDK_VERSION,
            nodeVersion: process.version,
            reportedAt,
        },
    };
}

function mergeContext(base, extra) {
    const out = { ...base };
    if (!extra || typeof extra !== "object") return out;
    for (const [k, v] of Object.entries(extra)) {
        if (
            v &&
            typeof v === "object" &&
            !Array.isArray(v) &&
            out[k] &&
            typeof out[k] === "object" &&
            !Array.isArray(out[k])
        ) {
            out[k] = { ...out[k], ...v };
        } else {
            out[k] = v;
        }
    }
    return out;
}

function captureAppCallerStack(label) {
    try {
        const err = new Error(label);
        const lines = (err.stack || "")
            .split("\n")
            .map((l) => l.trim())
            .filter(Boolean);
        const filteredFrames = lines.filter(
            (line) =>
                line.startsWith("at ") &&
                !line.includes("@pantree-lab/javascript-sdk") &&
                !line.includes("node_modules\\@pantree-lab\\javascript-sdk") &&
                !line.includes("node_modules/@pantree-lab/javascript-sdk"),
        );
        if (filteredFrames.length === 0) return undefined;
        return [`Error: ${label}`, ...filteredFrames].join("\n");
    } catch {
        return undefined;
    }
}

async function collectRuntimeContext(config = null) {
    const capturedAt = new Date().toISOString();
    const sdk = {
        name: SDK_NAME,
        version: SDK_VERSION,
    };

    if (isNode()) {
        const { installedPackages } = config?.packages
            ? { installedPackages: config.packages }
            : await loadAppPackageMap();
        const gitInfo = config?.git ?? collectGitContext();

        return {
            sdk,
            trace: {
                source: "pantree-js",
                runtime: "node",
                capturedAt,
            },
            runtime: {
                nodeVersion: process.version,
            },
            os: {
                platform: process.platform,
                arch: process.arch,
            },
            packages: {
                sdk: `${sdk.name}@${sdk.version}`,
                app:
                    process.env.npm_package_name && process.env.npm_package_version
                        ? `${process.env.npm_package_name}@${process.env.npm_package_version}`
                        : undefined,
                ...installedPackages,
            },
            git: gitInfo,
        };
    }

    return {
        sdk,
        trace: {
            source: "pantree-js",
            runtime: "browser",
            capturedAt,
        },
        runtime: {
            userAgent: typeof navigator !== "undefined" ? navigator.userAgent : undefined,
            language: typeof navigator !== "undefined" ? navigator.language : undefined,
        },
        os: {
            platform: typeof navigator !== "undefined" ? navigator.platform : undefined,
        },
        packages: {
            sdk: `${sdk.name}@${sdk.version}`,
            ...(config?.packages ?? {}),
        },
        git: config?.git ?? undefined,
    };
}

/* ================================================================== Client */

class PantreeClient {
    #config       = null;
    #healthTimer  = null;

    /**
     * @param {{
     *   dsn: string,
     *   environment?: string,
     *   release?: string,
     *   debug?: boolean,
     *   healthReporting?: boolean | { interval?: number },
     *   packages?: Record<string, string>,
     *   git?: { username?: string, email?: string, branch?: string, repoUrl?: string, commitHash?: string, tag?: string, commits?: Array<{ hash: string, authorName: string, authorEmail: string, date: string, message: string }> }
     * }} options
     */
    init(options = {}) {
        if (!options?.dsn) throw new Error("[Pantree] init() requires a `dsn` option");
        const { apiKey, ingestSecret, endpoint, healthEndpoint, ipEndpoint } = parseDsn(options.dsn);
        this.#config = {
            apiKey,
            ingestSecret,
            endpoint,
            healthEndpoint,
            ipEndpoint,
            environment:     options.environment ?? "production",
            release:         options.release     ?? null,
            debug:           options.debug       ?? false,
            packages:        options.packages    ?? null,
            git:             options.git         ?? null,
        };

        if (this.#config.debug) console.log("[Pantree] Initialised →", endpoint);

        // Health reporting
        const hr = options.healthReporting;
        if (hr) {
            const interval = (typeof hr === "object" ? hr.interval : null) ?? 30 * 60 * 1000;
            this.sendHealthReport(); // immediate first report
            this.#healthTimer = setInterval(() => this.sendHealthReport(), interval);
            if (this.#config.debug) console.log(`[Pantree] Health reporting every ${interval / 60000}min`);
        }
    }

    /** Stop the periodic health reporter (e.g. for clean shutdown). */
    stopHealthReporter() {
        if (this.#healthTimer) {
            clearInterval(this.#healthTimer);
            this.#healthTimer = null;
        }
    }

    /** Manually send one encrypted health report now. */
    async sendHealthReport() {
        if (!this.#config) {
            console.warn("[Pantree] Call init() before sendHealthReport()");
            return null;
        }
        try {
            const health    = await collectHealth(this.#config);
            const reportedAt = health.meta?.reportedAt ?? new Date().toISOString();
            const encrypted = await encryptHealth(health, this.#config.ingestSecret);

            const res = await fetch(this.#config.healthEndpoint, {
                method:  "POST",
                headers: {
                    "content-type": "application/json",
                    "x-pantree-key": this.#config.apiKey,
                },
                body: JSON.stringify({ ...encrypted, reportedAt }),
            });
            const json = await res.json();
            if (this.#config.debug) console.log("[Pantree] Health report →", json);
            return json;
        } catch (err) {
            if (this.#config.debug) console.error("[Pantree] Health report failed →", err);
            return null;
        }
    }

    /** Capture an Error object (or any thrown value). */
    async captureException(err, extra = {}) {
        const message = err?.message ?? String(err);
        const stack   = err?.stack   ?? null;
        const title   = err?.name    ?? "Error";
        return this.#send({ title, message, stack, level: "error", ...extra });
    }

    /** Capture an arbitrary message. */
    async captureMessage(message, extra = {}) {
        const resolvedTitle =
            extra?.title ?? (typeof message === "string" ? message : String(message));
        const stack = extra?.stack ?? captureAppCallerStack(resolvedTitle);
        return this.#send({ message, title: resolvedTitle, stack, level: "info", ...extra });
    }

    async #send(event) {
        if (!this.#config) {
            console.warn("[Pantree] Call Pantree.init({ dsn }) before capturing events.");
            return null;
        }

        const context = mergeContext(await collectRuntimeContext(this.#config), event.context);
        const payload = JSON.stringify({
            message:     event.message,
            title:       event.title       ?? undefined,
            stack:       event.stack       ?? undefined,
            level:       event.level       ?? "error",
            runtime:     event.runtime     ?? undefined,
            environment: event.environment ?? this.#config.environment,
            url:         event.url         ?? (typeof location !== "undefined" ? location.href : undefined),
            commit:      event.commit      ?? undefined,
            user:        event.user        ?? undefined,
            breadcrumbs: event.breadcrumbs ?? undefined,
            context:     Object.keys(context).length > 0 ? context : undefined,
        });

        const signature = await createSignature(payload, this.#config.ingestSecret);

        try {
            const res  = await fetch(this.#config.endpoint, {
                method:  "POST",
                headers: {
                    "content-type":      "application/json",
                    "x-pantree-key":      this.#config.apiKey,
                    "x-pantree-signature": signature,
                },
                body: payload,
            });
            const json = await res.json();
            if (this.#config.debug) console.log("[Pantree] Response →", json);
            return json;
        } catch (err) {
            if (this.#config.debug) console.error("[Pantree] Send failed →", err);
            return null;
        }
    }
}

/* ================================================================== Exports */

const Pantree = new PantreeClient();
export default Pantree;

export { PantreeClient, parseDsn, createSignature as createPantreeSignature, encryptHealth };

/** Legacy compat */
export async function sendPantreeEvent({ endpoint, projectKey, ingestSecret, event }) {
    const client = new PantreeClient();
    const u = new URL(endpoint);
    u.username = encodeURIComponent(projectKey);
    u.password = encodeURIComponent(ingestSecret);
    client.init({ dsn: u.toString() });
    return client.captureException({ message: event.message, stack: event.stack, ...event });
}
