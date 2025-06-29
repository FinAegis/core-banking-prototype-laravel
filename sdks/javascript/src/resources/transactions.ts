import { AxiosInstance } from 'axios';
import { Transaction, ApiResponse, PaginatedResponse, ListParams } from '../types';

export class Transactions {
  constructor(private client: AxiosInstance) {}

  /**
   * List all transactions
   */
  async list(params?: ListParams): Promise<PaginatedResponse<Transaction>> {
    const response = await this.client.get('/transactions', { params });
    return response.data;
  }

  /**
   * Get transaction details
   */
  async get(id: string): Promise<ApiResponse<Transaction>> {
    const response = await this.client.get(`/transactions/${id}`);
    return response.data;
  }
}