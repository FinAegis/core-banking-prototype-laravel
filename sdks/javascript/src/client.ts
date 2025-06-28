import axios, { AxiosInstance } from 'axios';
import axiosRetry from 'axios-retry';
import { 
  FinAegisConfig, 
  FinAegisEnvironment,
  ApiResponse,
  PaginatedResponse 
} from './types';
import { FinAegisError } from './errors';
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

    const baseURL = this.getBaseURL(config.environment || 'production');
    
    this.client = axios.create({
      baseURL,
      headers: {
        'Authorization': `Bearer ${config.apiKey}`,
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'User-Agent': `FinAegis-JS-SDK/${this.getVersion()}`
      },
      timeout: config.timeout || 30000,
    });

    // Configure retry logic
    axiosRetry(this.client, {
      retries: config.maxRetries || 3,
      retryDelay: axiosRetry.exponentialDelay,
      retryCondition: (error) => {
        return axiosRetry.isNetworkOrIdempotentRequestError(error) ||
               (error.response?.status ?? 0) >= 500;
      }
    });

    // Add response interceptor for error handling
    this.client.interceptors.response.use(
      (response) => response,
      (error) => {
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
      'production': 'https://api.finaegis.org/v2',
      'sandbox': 'https://api-sandbox.finaegis.org/v2',
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
  }): Promise<ApiResponse<T>> {
    const response = await this.client.request({
      method: config.method,
      url: config.path,
      params: config.params,
      data: config.data
    });
    
    return response.data;
  }
}