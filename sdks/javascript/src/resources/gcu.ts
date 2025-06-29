import { AxiosInstance } from 'axios';
import { GCUInfo, ApiResponse } from '../types';

export class GCU {
  constructor(private client: AxiosInstance) {}

  /**
   * Get GCU information
   */
  async getInfo(): Promise<ApiResponse<GCUInfo>> {
    const response = await this.client.get('/gcu');
    return response.data;
  }

  /**
   * Get real-time GCU composition
   */
  async getComposition(): Promise<ApiResponse<GCUInfo>> {
    const response = await this.client.get('/gcu/composition');
    return response.data;
  }

  /**
   * Get GCU value history
   */
  async getValueHistory(params?: {
    period?: '24h' | '7d' | '30d' | '90d' | '1y' | 'all';
    interval?: 'hourly' | 'daily' | 'weekly' | 'monthly';
  }): Promise<ApiResponse<Array<{
    timestamp: string;
    value_usd: number;
    composition_snapshot?: Record<string, number>;
  }>>> {
    const response = await this.client.get('/gcu/value-history', { params });
    return response.data;
  }

  /**
   * Get active governance polls
   */
  async getActivePolls(): Promise<ApiResponse<Array<{
    id: string;
    title: string;
    description: string;
    proposed_composition: Record<string, number>;
    current_votes_for: number;
    current_votes_against: number;
    starts_at: string;
    ends_at: string;
    minimum_participation: number;
    minimum_approval: number;
  }>>> {
    const response = await this.client.get('/gcu/governance/active-polls');
    return response.data;
  }

  /**
   * Get supported banks for GCU
   */
  async getSupportedBanks(): Promise<ApiResponse<Array<{
    code: string;
    name: string;
    country: string;
    supported_currencies: string[];
    features: string[];
  }>>> {
    const response = await this.client.get('/gcu/supported-banks');
    return response.data;
  }
}