import { AxiosInstance } from 'axios';
import { ExchangeRate, ApiResponse, PaginatedResponse, ListParams } from '../types';

export class ExchangeRates {
  constructor(private client: AxiosInstance) {}

  /**
   * List all exchange rates
   */
  async list(params?: ListParams): Promise<PaginatedResponse<ExchangeRate>> {
    const response = await this.client.get('/exchange-rates', { params });
    return response.data;
  }

  /**
   * Get exchange rate between two assets
   */
  async get(from: string, to: string): Promise<ApiResponse<ExchangeRate>> {
    const response = await this.client.get(`/exchange-rates/${from}/${to}`);
    return response.data;
  }

  /**
   * Convert amount between two assets
   */
  async convert(from: string, to: string, amount: number): Promise<ApiResponse<{
    from_asset: string;
    to_asset: string;
    from_amount: number;
    to_amount: number;
    rate: number;
    converted_at: string;
  }>> {
    const response = await this.client.get(`/exchange-rates/${from}/${to}/convert`, {
      params: { amount }
    });
    return response.data;
  }

  /**
   * Refresh exchange rates
   */
  async refresh(): Promise<ApiResponse<{ message: string; updated_count: number }>> {
    const response = await this.client.post('/exchange-rates/refresh');
    return response.data;
  }
}