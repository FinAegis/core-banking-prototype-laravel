import { AxiosInstance } from 'axios';
import { Asset, ApiResponse, PaginatedResponse, ListParams } from '../types';

export class Assets {
  constructor(private client: AxiosInstance) {}

  /**
   * List all assets
   */
  async list(params?: ListParams): Promise<PaginatedResponse<Asset>> {
    const response = await this.client.get('/assets', { params });
    return response.data;
  }

  /**
   * Get asset details
   */
  async get(code: string): Promise<ApiResponse<Asset>> {
    const response = await this.client.get(`/assets/${code}`);
    return response.data;
  }

  /**
   * Create a new asset
   *
   * @deprecated The server has no such route (it never worked and was removed in FinAegis v7.18.4). This method will be removed in the next major version.
   */
  async create(data: {
    code: string;
    name: string;
    type: 'fiat' | 'crypto' | 'commodity';
    decimals?: number;
  }): Promise<ApiResponse<Asset>> {
    const response = await this.client.post('/assets', data);
    return response.data;
  }

  /**
   * Update an asset
   *
   * @deprecated The server has no such route (it never worked and was removed in FinAegis v7.18.4). This method will be removed in the next major version.
   */
  async update(code: string, data: Partial<{
    name: string;
    type: 'fiat' | 'crypto' | 'commodity';
    decimals: number;
    is_active: boolean;
  }>): Promise<ApiResponse<Asset>> {
    const response = await this.client.put(`/assets/${code}`, data);
    return response.data;
  }

  /**
   * Delete an asset
   *
   * @deprecated The server has no such route (it never worked and was removed in FinAegis v7.18.4). This method will be removed in the next major version.
   */
  async delete(code: string): Promise<ApiResponse<{ message: string }>> {
    const response = await this.client.delete(`/assets/${code}`);
    return response.data;
  }
}