import { AxiosInstance } from 'axios';
import { Transfer, CreateTransferParams, ApiResponse, RequestOptions } from '../types';
import { idempotencyConfig } from '../idempotency';

export class Transfers {
  constructor(private client: AxiosInstance) {}

  /**
   * Create a new transfer.
   *
   * The request carries an Idempotency-Key, so a retry does not create a
   * second transfer. Pass `options.idempotencyKey` to choose the key.
   */
  async create(data: CreateTransferParams, options?: RequestOptions): Promise<ApiResponse<Transfer>> {
    const response = await this.client.post('/transfers', data, idempotencyConfig(options));
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