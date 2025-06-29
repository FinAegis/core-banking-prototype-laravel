export class FinAegisError extends Error {
  public readonly statusCode?: number;
  public readonly data?: any;

  constructor(message: string, statusCode?: number, data?: any) {
    super(message);
    this.name = 'FinAegisError';
    this.statusCode = statusCode;
    this.data = data;
    
    // Maintains proper stack trace for where our error was thrown
    if (Error.captureStackTrace) {
      Error.captureStackTrace(this, FinAegisError);
    }
  }

  public isAuthError(): boolean {
    return this.statusCode === 401 || this.statusCode === 403;
  }

  public isNotFoundError(): boolean {
    return this.statusCode === 404;
  }

  public isValidationError(): boolean {
    return this.statusCode === 422;
  }

  public isRateLimitError(): boolean {
    return this.statusCode === 429;
  }

  public isServerError(): boolean {
    return (this.statusCode ?? 0) >= 500;
  }
}