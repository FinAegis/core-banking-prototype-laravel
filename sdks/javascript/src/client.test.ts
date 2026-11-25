import { AxiosError, AxiosInstance, AxiosResponse, InternalAxiosRequestConfig } from 'axios';
import { FinAegis } from './client';
import { FinAegisError } from './errors';

type Outcome = { status: number; data?: unknown } | { networkError: string };

/**
 * Replaces the HTTP transport with a scripted one. Each attempt takes the next
 * outcome from the list and records the request it was given.
 */
function scriptTransport(sdk: FinAegis, outcomes: Outcome[]): InternalAxiosRequestConfig[] {
  const seen: InternalAxiosRequestConfig[] = [];
  const client = (sdk as unknown as { client: AxiosInstance }).client;

  client.defaults.adapter = async (config: InternalAxiosRequestConfig): Promise<AxiosResponse> => {
    seen.push(config);
    const outcome = outcomes[Math.min(seen.length - 1, outcomes.length - 1)];

    if ('networkError' in outcome) {
      throw new AxiosError('socket hang up', outcome.networkError, config);
    }

    const response: AxiosResponse = {
      status: outcome.status,
      statusText: String(outcome.status),
      data: outcome.data ?? {},
      headers: {},
      config,
    };

    if (outcome.status >= 400) {
      throw new AxiosError('Request failed', 'ERR_BAD_RESPONSE', config, null, response);
    }

    return response;
  };

  return seen;
}

const transfer = {
  from_account: '11111111-1111-4111-8111-111111111111',
  to_account: '22222222-2222-4222-8222-222222222222',
  amount: 25,
  asset_code: 'USD',
};

const created = { status: 201, data: { data: { uuid: 't-1', status: 'pending' } } };
const uuidPattern = /^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/;

function keysSent(requests: InternalAxiosRequestConfig[]): Array<string | undefined> {
  return requests.map((request) => request.headers.get('Idempotency-Key') as string | undefined);
}

describe('FinAegis retries', () => {
  it('sends the same Idempotency-Key on every retry of a transfer after a 5xx', async () => {
    const sdk = new FinAegis({ apiKey: 'test', maxRetries: 2 });
    const requests = scriptTransport(sdk, [{ status: 503 }, { status: 502 }, created]);

    const result = await sdk.transfers.create(transfer);

    expect(result).toEqual(created.data);
    expect(requests).toHaveLength(3);
    const keys = keysSent(requests);
    expect(keys[0]).toMatch(uuidPattern);
    expect(new Set(keys)).toEqual(new Set([keys[0]]));
  });

  it('sends the same Idempotency-Key when it retries after a dropped connection', async () => {
    const sdk = new FinAegis({ apiKey: 'test', maxRetries: 1 });
    const requests = scriptTransport(sdk, [{ networkError: 'ECONNRESET' }, created]);

    await sdk.transfers.create(transfer);

    expect(requests).toHaveLength(2);
    const keys = keysSent(requests);
    expect(keys[0]).toMatch(uuidPattern);
    expect(keys[1]).toBe(keys[0]);
  });

  it('uses a new key for each separate transfer', async () => {
    const sdk = new FinAegis({ apiKey: 'test', maxRetries: 0 });
    const requests = scriptTransport(sdk, [created]);

    await sdk.transfers.create(transfer);
    await sdk.transfers.create(transfer);

    const keys = keysSent(requests);
    expect(keys[0]).toMatch(uuidPattern);
    expect(keys[1]).toMatch(uuidPattern);
    expect(keys[1]).not.toBe(keys[0]);
  });

  it('uses the key the caller passes and repeats it on retry', async () => {
    const sdk = new FinAegis({ apiKey: 'test', maxRetries: 1 });
    const requests = scriptTransport(sdk, [{ status: 500 }, created]);

    await sdk.transfers.create(transfer, { idempotencyKey: 'order-2026-11-24-0001' });

    expect(keysSent(requests)).toEqual(['order-2026-11-24-0001', 'order-2026-11-24-0001']);
  });

  it('makes exactly one attempt when maxRetries is 0', async () => {
    const sdk = new FinAegis({ apiKey: 'test', maxRetries: 0 });
    const requests = scriptTransport(sdk, [{ status: 503 }, created]);

    await expect(sdk.transfers.create(transfer)).rejects.toMatchObject({ statusCode: 503 });
    expect(requests).toHaveLength(1);
  });

  it('keeps the status code on the error after the retries run out', async () => {
    const sdk = new FinAegis({ apiKey: 'test', maxRetries: 1 });
    const requests = scriptTransport(sdk, [{ status: 503, data: { message: 'Service unavailable' } }]);

    const error = await sdk.transfers.create(transfer).catch((e: unknown) => e);

    expect(requests).toHaveLength(2);
    expect(error).toBeInstanceOf(FinAegisError);
    expect(error).toMatchObject({ statusCode: 503, message: 'Service unavailable' });
  });

  it('does not send an Idempotency-Key on GET and still retries it', async () => {
    const sdk = new FinAegis({ apiKey: 'test', maxRetries: 1 });
    const requests = scriptTransport(sdk, [{ status: 503 }, { status: 200, data: { data: { uuid: 't-1' } } }]);

    await sdk.transfers.get('t-1');

    expect(requests).toHaveLength(2);
    expect(keysSent(requests)).toEqual([undefined, undefined]);
  });

  it('adds a key to money-moving account calls and to custom POST requests', async () => {
    const sdk = new FinAegis({ apiKey: 'test', maxRetries: 0 });
    const requests = scriptTransport(sdk, [created]);

    await sdk.accounts.deposit('acc-1', 10, 'USD', { idempotencyKey: 'deposit-key-0000000001' });
    await sdk.accounts.withdraw('acc-1', 10);
    await sdk.request({ method: 'POST', path: '/transfers', data: transfer, idempotencyKey: 'custom-key-00000000001' });

    const keys = keysSent(requests);
    expect(keys[0]).toBe('deposit-key-0000000001');
    expect(keys[1]).toMatch(uuidPattern);
    expect(keys[2]).toBe('custom-key-00000000001');
  });
});
