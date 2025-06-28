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
   */
  async delete(code: string): Promise<ApiResponse<{ message: string }>> {
    const response = await this.client.delete(`/assets/${code}`);
    return response.data;
  }
}