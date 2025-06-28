import { AxiosInstance } from 'axios';
import { 
  Webhook, 
  CreateWebhookParams, 
  WebhookDelivery,
  ApiResponse, 
  PaginatedResponse, 
  ListParams 
} from '../types';

export class Webhooks {
  constructor(private client: AxiosInstance) {}

  /**
   * List all webhooks
   */
  async list(params?: ListParams): Promise<PaginatedResponse<Webhook>> {
    const response = await this.client.get('/webhooks', { params });
    return response.data;
  }

  /**
   * Create a new webhook
   */
  async create(data: CreateWebhookParams): Promise<ApiResponse<Webhook>> {
    const response = await this.client.post('/webhooks', data);
    return response.data;
  }

  /**
   * Get webhook details
   */
  async get(id: string): Promise<ApiResponse<Webhook>> {
    const response = await this.client.get(`/webhooks/${id}`);
    return response.data;
  }

  /**
   * Update a webhook
   */
  async update(id: string, data: Partial<CreateWebhookParams>): Promise<ApiResponse<Webhook>> {
    const response = await this.client.put(`/webhooks/${id}`, data);
    return response.data;
  }

  /**
   * Delete a webhook
   */
  async delete(id: string): Promise<ApiResponse<{ message: string }>> {
    const response = await this.client.delete(`/webhooks/${id}`);
    return response.data;
  }

  /**
   * Get webhook deliveries
   */
  async getDeliveries(id: string, params?: ListParams): Promise<PaginatedResponse<WebhookDelivery>> {
    const response = await this.client.get(`/webhooks/${id}/deliveries`, { params });
    return response.data;
  }

  /**
   * Get available webhook events
   */
  async getEvents(): Promise<ApiResponse<{
    events: Array<{
      name: string;
      description: string;
      category: string;
    }>;
  }>> {
    const response = await this.client.get('/webhooks/events');
    return response.data;
  }
}