export interface PantreeDsnConfig {
  apiKey: string;
  ingestSecret: string;
  endpoint: string;
  healthEndpoint: string;
  ipEndpoint: string;
}

export interface PantreeHealthReportingOptions {
  interval?: number;
}

export interface PantreeInitOptions {
  dsn: string;
  environment?: string;
  release?: string | null;
  debug?: boolean;
  healthReporting?: boolean | PantreeHealthReportingOptions;
}

export interface PantreeCapturedEvent {
  message: string;
  title?: string | null;
  stack?: string | null;
  level?: string;
  runtime?: string | null;
  environment?: string | null;
  url?: string | null;
  commit?: string | null;
  user?: unknown;
  breadcrumbs?: unknown;
  context?: unknown;
}

export interface PantreeSendLegacyOptions {
  endpoint: string;
  projectKey: string;
  ingestSecret: string;
  event: {
    message: string;
    stack?: string;
    [key: string]: unknown;
  };
}

export declare class PantreeClient {
  init(options: PantreeInitOptions): void;
  stopHealthReporter(): void;
  sendHealthReport(): Promise<unknown | null>;
  captureException(err: unknown, extra?: Record<string, unknown>): Promise<unknown | null>;
  captureMessage(message: string, extra?: Record<string, unknown>): Promise<unknown | null>;
}

declare const Pantree: PantreeClient;
export default Pantree;

export declare function parseDsn(dsn: string): PantreeDsnConfig;
export declare function createPantreeSignature(payload: string, secret: string): Promise<string>;
export declare function encryptHealth(
  data: unknown,
  ingestSecret: string
): Promise<{ iv: string; ciphertext: string }>;
export declare function sendPantreeEvent(options: PantreeSendLegacyOptions): Promise<unknown | null>;
