export class BridgeError extends Error {
  public readonly statusCode: number;
  public readonly code: string;

  constructor(message: string, statusCode: number = 500, code: string = 'BRIDGE_ERROR') {
    super(message);
    this.name = 'BridgeError';
    this.statusCode = statusCode;
    this.code = code;
  }
}

export class ValidationError extends BridgeError {
  constructor(message: string) {
    super(message, 422, 'VALIDATION_ERROR');
    this.name = 'ValidationError';
  }
}

export class EngineNotReadyError extends BridgeError {
  constructor() {
    super('RAILGUN Engine is not initialized', 503, 'ENGINE_NOT_READY');
    this.name = 'EngineNotReadyError';
  }
}

export class NetworkNotSupportedError extends BridgeError {
  constructor(network: string) {
    super(`Network "${network}" is not supported by RAILGUN`, 400, 'NETWORK_NOT_SUPPORTED');
    this.name = 'NetworkNotSupportedError';
  }
}

export class WalletNotFoundError extends BridgeError {
  constructor(identifier: string) {
    super(`Wallet "${identifier}" not found`, 404, 'WALLET_NOT_FOUND');
    this.name = 'WalletNotFoundError';
  }
}

export function errorResponse(error: unknown) {
  if (error instanceof BridgeError) {
    return {
      statusCode: error.statusCode,
      body: {
        success: false,
        error: {
          code: error.code,
          message: error.message,
        },
      },
    };
  }

  const message = error instanceof Error ? error.message : 'Unknown error';
  return {
    statusCode: 500,
    body: {
      success: false,
      error: {
        code: 'INTERNAL_ERROR',
        message,
      },
    },
  };
}
