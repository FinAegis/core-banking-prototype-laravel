import * as crypto from 'crypto';
import { AxiosRequestConfig } from 'axios';
import { RequestOptions } from './types';

/** The HTTP header the API reads to recognise a repeated request. */
export const IDEMPOTENCY_HEADER = 'Idempotency-Key';

/**
 * Creates a random version 4 UUID to use as an Idempotency-Key.
 */
export function generateIdempotencyKey(): string {
  if (typeof crypto.randomUUID === 'function') {
    return crypto.randomUUID();
  }

  // Node versions before 14.17 have no randomUUID.
  const bytes = crypto.randomBytes(16);
  bytes[6] = (bytes[6] & 0x0f) | 0x40;
  bytes[8] = (bytes[8] & 0x3f) | 0x80;
  const hex = bytes.toString('hex');

  return `${hex.slice(0, 8)}-${hex.slice(8, 12)}-${hex.slice(12, 16)}-${hex.slice(16, 20)}-${hex.slice(20)}`;
}

/**
 * Request config that carries the caller's Idempotency-Key, if they gave one.
 * Without one, the client adds a fresh key to the POST before it is sent.
 */
export function idempotencyConfig(options?: RequestOptions): AxiosRequestConfig {
  if (!options?.idempotencyKey) {
    return {};
  }

  return { headers: { [IDEMPOTENCY_HEADER]: options.idempotencyKey } };
}
