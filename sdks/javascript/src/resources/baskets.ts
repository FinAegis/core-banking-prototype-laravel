import { AxiosInstance } from 'axios';
import { Basket, ApiResponse, PaginatedResponse, ListParams } from '../types';

export class Baskets {
  constructor(private client: AxiosInstance) {}

  /**
   * List all baskets
   */
  async list(params?: ListParams): Promise<PaginatedResponse<Basket>> {
    const response = await this.client.get('/baskets', { params });
    return response.data;
  }

  /**
   * Get basket details
   */
  async get(code: string): Promise<ApiResponse<Basket>> {
    const response = await this.client.get(`/baskets/${code}`);
    return response.data;
  }

  /**
   * Get basket value
   */
  async getValue(code: string): Promise<ApiResponse<{
    code: string;
    value_usd: number;
    last_updated: string;
  }>> {
    const response = await this.client.get(`/baskets/${code}/value`);
    return response.data;
  }

  /**
   * Get basket value history
   */
  async getHistory(code: string, params?: {
    period?: '24h' | '7d' | '30d' | '90d' | '1y' | 'all';
    interval?: 'hourly' | 'daily' | 'weekly' | 'monthly';
  }): Promise<ApiResponse<Array<{
    timestamp: string;
    value_usd: number;
  }>>> {
    const response = await this.client.get(`/baskets/${code}/history`, { params });
    return response.data;
  }

  /**
   * Get basket performance metrics
   */
  async getPerformance(code: string): Promise<ApiResponse<{
    code: string;
    performance: {
      '24h': { change_usd: number; change_percent: number };
      '7d': { change_usd: number; change_percent: number };
      '30d': { change_usd: number; change_percent: number };
      '1y': { change_usd: number; change_percent: number };
    };
  }>> {
    const response = await this.client.get(`/baskets/${code}/performance`);
    return response.data;
  }

  /**
   * Create a new basket
   */
  async create(data: {
    code: string;
    name: string;
    description?: string;
    composition: Record<string, number>;
  }): Promise<ApiResponse<Basket>> {
    const response = await this.client.post('/baskets', data);
    return response.data;
  }

  /**
   * Rebalance a basket
   */
  async rebalance(code: string, newComposition: Record<string, number>): Promise<ApiResponse<{
    message: string;
    basket: Basket;
  }>> {
    const response = await this.client.post(`/baskets/${code}/rebalance`, {
      composition: newComposition
    });
    return response.data;
  }

  /**
   * Compose basket assets into basket tokens
   */
  async compose(accountUuid: string, basketCode: string, amount: number): Promise<ApiResponse<{
    message: string;
    transaction_id: string;
  }>> {
    const response = await this.client.post(`/accounts/${accountUuid}/baskets/compose`, {
      basket_code: basketCode,
      amount
    });
    return response.data;
  }

  /**
   * Decompose basket tokens into underlying assets
   */
  async decompose(accountUuid: string, basketCode: string, amount: number): Promise<ApiResponse<{
    message: string;
    transaction_id: string;
  }>> {
    const response = await this.client.post(`/accounts/${accountUuid}/baskets/decompose`, {
      basket_code: basketCode,
      amount
    });
    return response.data;
  }
}