import axios, { AxiosInstance } from 'axios';
import axiosRetry from 'axios-retry';
import {
  FinAegisConfig,
  FinAegisEnvironment,
  ApiResponse
} from './types';
import { FinAegisError } from './errors';
import { IDEMPOTENCY_HEADER, generateIdempotencyKey, idempotencyConfig } from './idempotency';
import { Accounts } from './resources/accounts';
import { Transactions } from './resources/transactions';
import { Transfers } from './resources/transfers';
import { Assets } from './resources/assets';
import { Baskets } from './resources/baskets';
import { Webhooks } from './resources/webhooks';
import { ExchangeRates } from './resources/exchange-rates';
import { GCU } from './resources/gcu';

export class FinAegis {
  private readonly client: AxiosInstance;
  
  // Resources
  public readonly accounts: Accounts;
  public readonly transactions: Transactions;
  public readonly transfers: Transfers;
  public readonly assets: Assets;
  public readonly baskets: Baskets;
  public readonly webhooks: Webhooks;
  public readonly exchangeRates: ExchangeRates;
  public readonly gcu: GCU;

  constructor(config: FinAegisConfig) {
    if (!config.apiKey) {
      throw new FinAegisError('API key is required');
    }

    const baseURL = config.baseURL || this.getBaseURL(config.environment || 'production');
    
    this.client = axios.create({
      baseURL,
      headers: {
        'Authorization': `Bearer ${config.apiKey}`,
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'User-Agent': `FinAegis-JS-SDK/${this.getVersion()}`
      },
      timeout: config.timeout ?? 30000,
    });

    // Give every POST an Idempotency-Key before it is first sent. A retry
    // sends the same request config again, so it carries the same key, and
    // the server answers it with the first response instead of running it
    // a second time.
    this.client.interceptors.request.use((request) => {
      if (request.method?.toLowerCase() === 'post' && !request.headers.has(IDEMPOTENCY_HEADER)) {
        request.headers.set(IDEMPOTENCY_HEADER, generateIdempotencyKey());
      }
      return request;
    });

    // Configure retry logic. 0 means no retries.
    axiosRetry(this.client, {
      retries: config.maxRetries ?? 3,
      retryDelay: axiosRetry.exponentialDelay,
      retryCondition: (error) => {
        const method = error.config?.method?.toLowerCase();

        // A POST can move money. Send it again only when it carries an
        // Idempotency-Key, so the server can recognise the repeat.
        if (method === 'post' || method === 'patch') {
          if (!error.config?.headers?.has(IDEMPOTENCY_HEADER)) {
            return false;
          }
          return axiosRetry.isNetworkError(error) ||
                 (error.response?.status ?? 0) >= 500;
        }

        return axiosRetry.isNetworkOrIdempotentRequestError(error) ||
               (error.response?.status ?? 0) >= 500;
      }
    });

    // Add response interceptor for error handling
    this.client.interceptors.response.use(
      (response) => response,
      (error) => {
        // A retried request passes through this handler twice. Keep the
        // error from the last attempt, with its status code.
        if (error instanceof FinAegisError) {
          throw error;
        }
        if (error.response) {
          throw new FinAegisError(
            error.response.data?.message || error.message,
            error.response.status,
            error.response.data
          );
        }
        throw new FinAegisError(error.message);
      }
    );

    // Initialize resources
    this.accounts = new Accounts(this.client);
    this.transactions = new Transactions(this.client);
    this.transfers = new Transfers(this.client);
    this.assets = new Assets(this.client);
    this.baskets = new Baskets(this.client);
    this.webhooks = new Webhooks(this.client);
    this.exchangeRates = new ExchangeRates(this.client);
    this.gcu = new GCU(this.client);
  }

  private getBaseURL(environment: FinAegisEnvironment): string {
    const urls: Record<FinAegisEnvironment, string> = {
      'production': 'https://api.zelta.app/v2',
      'sandbox': 'https://api-sandbox.zelta.app/v2',
      'local': 'http://localhost:8000/api/v2'
    };
    
    return urls[environment] || urls.production;
  }

  private getVersion(): string {
    try {
      return require('../package.json').version;
    } catch {
      return '1.0.0';
    }
  }

  /**
   * Make a custom API request
   */
  public async request<T = any>(config: {
    method: 'GET' | 'POST' | 'PUT' | 'DELETE' | 'PATCH';
    path: string;
    params?: Record<string, any>;
    data?: any;
    /** Idempotency-Key for a POST. The SDK makes one when you leave it out. */
    idempotencyKey?: string;
  }): Promise<ApiResponse<T>> {
    const response = await this.client.request({
      method: config.method,
      url: config.path,
      params: config.params,
      data: config.data,
      ...idempotencyConfig({ idempotencyKey: config.idempotencyKey })
    });
    
    return response.data;
  }
}