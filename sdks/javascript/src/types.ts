export type FinAegisEnvironment = 'production' | 'sandbox' | 'local';

export interface FinAegisConfig {
  apiKey: string;
  environment?: FinAegisEnvironment;
  timeout?: number;
  maxRetries?: number;
}

export interface ApiResponse<T> {
  data: T;
  meta?: Record<string, any>;
  links?: Record<string, string>;
}

export interface PaginatedResponse<T> extends ApiResponse<T[]> {
  meta: {
    current_page: number;
    from: number;
    last_page: number;
    per_page: number;
    to: number;
    total: number;
  };
  links: {
    first: string;
    last: string;
    prev: string | null;
    next: string | null;
  };
}

export interface Account {
  uuid: string;
  user_uuid: string;
  name: string;
  balance: number;
  frozen: boolean;
  created_at: string;
  updated_at: string;
}

export interface CreateAccountParams {
  user_uuid: string;
  name: string;
  initial_balance?: number;
}

export interface Transaction {
  id: string;
  account_uuid: string;
  type: 'deposit' | 'withdrawal';
  amount: number;
  asset_code: string;
  status: 'pending' | 'completed' | 'failed';
  reference?: string;
  created_at: string;
  completed_at?: string;
}

export interface Transfer {
  uuid: string;
  from_account: string;
  to_account: string;
  amount: number;
  asset_code: string;
  reference?: string;
  status: 'pending' | 'completed' | 'failed';
  created_at: string;
  completed_at?: string;
}

export interface CreateTransferParams {
  from_account: string;
  to_account: string;
  amount: number;
  asset_code: string;
  reference?: string;
  workflow_enabled?: boolean;
}

export interface Asset {
  code: string;
  name: string;
  type: 'fiat' | 'crypto' | 'commodity';
  decimals: number;
  is_active: boolean;
  created_at: string;
  updated_at: string;
}

export interface Basket {
  code: string;
  name: string;
  description?: string;
  composition: Record<string, number>;
  value_usd: number;
  is_active: boolean;
  created_at: string;
  updated_at: string;
}

export interface ExchangeRate {
  from_asset: string;
  to_asset: string;
  rate: number;
  last_updated: string;
}

export interface Webhook {
  uuid: string;
  name: string;
  url: string;
  events: string[];
  headers?: Record<string, string>;
  is_active: boolean;
  created_at: string;
  updated_at: string;
}

export interface CreateWebhookParams {
  name: string;
  url: string;
  events: string[];
  headers?: Record<string, string>;
  secret?: string;
}

export interface WebhookDelivery {
  uuid: string;
  webhook_uuid: string;
  event_type: string;
  status: 'pending' | 'success' | 'failed';
  attempts: number;
  response_code?: number;
  created_at: string;
  delivered_at?: string;
}

export interface GCUInfo {
  basket_code: string;
  name: string;
  total_value_usd: number;
  composition: GCUComposition[];
  performance: GCUPerformance;
  last_updated: string;
}

export interface GCUComposition {
  asset_code: string;
  asset_name: string;
  asset_type: string;
  weight: number;
  current_price_usd: number;
  value_contribution_usd: number;
  percentage_of_basket: number;
  '24h_change': number;
  '7d_change': number;
}

export interface GCUPerformance {
  '24h_change_usd': number;
  '24h_change_percent': number;
  '7d_change_usd': number;
  '7d_change_percent': number;
  '30d_change_usd': number;
  '30d_change_percent': number;
}

export interface ListParams {
  page?: number;
  per_page?: number;
  sort?: string;
  order?: 'asc' | 'desc';
}