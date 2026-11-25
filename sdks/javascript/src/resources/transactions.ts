import { AxiosInstance } from 'axios';
import { Transaction, ApiResponse, PaginatedResponse, ListParams } from '../types';

export class Transactions {
  constructor(private client: AxiosInstance) {}

  /**
   * List all transactions
   *
   * @deprecated The server has no such route (it never worked and was removed in FinAegis v7.18.4). This method will be removed in the next major version.
   */
  async list(params?: ListParams): Promise<PaginatedResponse<Transaction>> {
    const response = await this.client.get('/transactions', { params });
    return response.data;
  }

  /**
   * Get transaction details
   *
   * @deprecated The server has no such route (it never worked and was removed in FinAegis v7.18.4). This method will be removed in the next major version.
   */
  async get(id: string): Promise<ApiResponse<Transaction>> {
    const response = await this.client.get(`/transactions/${id}`);
    return response.data;
  }
}