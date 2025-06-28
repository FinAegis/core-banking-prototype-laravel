"""
FinAegis SDK Exceptions
"""

from typing import Optional, Dict, Any
import requests


class FinAegisError(Exception):
    """Base exception for all FinAegis SDK errors."""
    
    def __init__(
        self,
        message: str,
        status_code: Optional[int] = None,
        response_data: Optional[Dict[str, Any]] = None
    ):
        super().__init__(message)
        self.status_code = status_code
        self.response_data = response_data or {}


class AuthenticationError(FinAegisError):
    """Raised when authentication fails (401/403)."""
    pass


class NotFoundError(FinAegisError):
    """Raised when a resource is not found (404)."""
    pass


class ValidationError(FinAegisError):
    """Raised when request validation fails (422)."""
    
    @property
    def errors(self) -> Dict[str, Any]:
        """Get validation errors from the response."""
        return self.response_data.get('errors', {})


class RateLimitError(FinAegisError):
    """Raised when rate limit is exceeded (429)."""
    
    @property
    def retry_after(self) -> Optional[int]:
        """Get the number of seconds to wait before retrying."""
        return self.response_data.get('retry_after')


class ServerError(FinAegisError):
    """Raised when a server error occurs (5xx)."""
    pass


def handle_response_error(response: requests.Response) -> None:
    """
    Handle API response errors and raise appropriate exceptions.
    
    Args:
        response: The requests Response object
        
    Raises:
        FinAegisError: Appropriate error based on status code
    """
    try:
        error_data = response.json()
        message = error_data.get('message', response.reason)
    except ValueError:
        error_data = {}
        message = response.reason or f"HTTP {response.status_code} error"
    
    status_code = response.status_code
    
    if status_code == 401:
        raise AuthenticationError(
            message="Authentication failed. Check your API key.",
            status_code=status_code,
            response_data=error_data
        )
    elif status_code == 403:
        raise AuthenticationError(
            message=message or "Permission denied.",
            status_code=status_code,
            response_data=error_data
        )
    elif status_code == 404:
        raise NotFoundError(
            message=message or "Resource not found.",
            status_code=status_code,
            response_data=error_data
        )
    elif status_code == 422:
        raise ValidationError(
            message=message or "Validation failed.",
            status_code=status_code,
            response_data=error_data
        )
    elif status_code == 429:
        raise RateLimitError(
            message=message or "Rate limit exceeded.",
            status_code=status_code,
            response_data=error_data
        )
    elif status_code >= 500:
        raise ServerError(
            message=message or "Server error occurred.",
            status_code=status_code,
            response_data=error_data
        )
    else:
        raise FinAegisError(
            message=message,
            status_code=status_code,
            response_data=error_data
        )