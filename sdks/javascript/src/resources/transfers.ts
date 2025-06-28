import { AxiosInstance } from 'axios';
import { Transfer, CreateTransferParams, ApiResponse } from '../types';

export class Transfers {
  constructor(private client: AxiosInstance) {}

  /**
   * Create a new transfer
   */
  async create(data: CreateTransferParams): Promise<ApiResponse<Transfer>> {
    const response = await this.client.post('/transfers', data);
    return response.data;
  }

  /**
   * Get transfer details
   */
  async get(uuid: string): Promise<ApiResponse<Transfer>> {
    const response = await this.client.get(`/transfers/${uuid}`);
    return response.data;
  }
}