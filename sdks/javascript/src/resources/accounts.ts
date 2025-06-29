import { AxiosInstance } from 'axios';
import { 
  Account, 
  CreateAccountParams, 
  ApiResponse, 
  PaginatedResponse,
  ListParams 
} from '../types';

export class Accounts {
  constructor(private client: AxiosInstance) {}

  /**
   * List all accounts
   */
  async list(params?: ListParams): Promise<PaginatedResponse<Account>> {
    const response = await this.client.get('/accounts', { params });
    return response.data;
  }

  /**
   * Create a new account
   */
  async create(data: CreateAccountParams): Promise<ApiResponse<Account>> {
    const response = await this.client.post('/accounts', data);
    return response.data;
  }

  /**
   * Get account details
   */
  async get(uuid: string): Promise<ApiResponse<Account>> {
    const response = await this.client.get(`/accounts/${uuid}`);
    return response.data;
  }

  /**
   * Delete an account
   */
  async delete(uuid: string): Promise<ApiResponse<{ message: string }>> {
    const response = await this.client.delete(`/accounts/${uuid}`);
    return response.data;
  }

  /**
   * Freeze an account
   */
  async freeze(uuid: string, reason: string, authorizedBy?: string): Promise<ApiResponse<{ message: string }>> {
    const response = await this.client.post(`/accounts/${uuid}/freeze`, {
      reason,
      authorized_by: authorizedBy
    });
    return response.data;
  }

  /**
   * Unfreeze an account
   */
  async unfreeze(uuid: string, reason: string, authorizedBy?: string): Promise<ApiResponse<{ message: string }>> {
    const response = await this.client.post(`/accounts/${uuid}/unfreeze`, {
      reason,
      authorized_by: authorizedBy
    });
    return response.data;
  }

  /**
   * Get account balances
   */
  async getBalances(uuid: string): Promise<ApiResponse<{
    account_uuid: string;
    balances: Array<{
      asset_code: string;
      available_balance: string;
      reserved_balance: string;
      total_balance: string;
    }>;
    summary: {
      total_assets: number;
      total_usd_equivalent: string;
    };
  }>> {
    const response = await this.client.get(`/accounts/${uuid}/balances`);
    return response.data;
  }

  /**
   * Deposit funds to an account
   */
  async deposit(uuid: string, amount: number, assetCode: string = 'USD'): Promise<ApiResponse<Transaction>> {
    const response = await this.client.post(`/accounts/${uuid}/deposit`, {
      amount,
      asset_code: assetCode
    });
    return response.data;
  }

  /**
   * Withdraw funds from an account
   */
  async withdraw(uuid: string, amount: number, assetCode: string = 'USD'): Promise<ApiResponse<Transaction>> {
    const response = await this.client.post(`/accounts/${uuid}/withdraw`, {
      amount,
      asset_code: assetCode
    });
    return response.data;
  }

  /**
   * Get account transaction history
   */
  async getTransactions(uuid: string, params?: ListParams): Promise<PaginatedResponse<Transaction>> {
    const response = await this.client.get(`/accounts/${uuid}/transactions`, { params });
    return response.data;
  }

  /**
   * Get account transfer history
   */
  async getTransfers(uuid: string, params?: ListParams): Promise<PaginatedResponse<Transfer>> {
    const response = await this.client.get(`/accounts/${uuid}/transfers`, { params });
    return response.data;
  }
}

// Import types used in this file
import { Transaction, Transfer } from '../types';