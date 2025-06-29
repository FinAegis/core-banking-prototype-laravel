"""
FinAegis Python SDK

Official Python SDK for the FinAegis API.
"""

__version__ = "1.0.0"

from .client import FinAegis
from .exceptions import (
    FinAegisError,
    AuthenticationError,
    NotFoundError,
    ValidationError,
    RateLimitError,
    ServerError,
)
from .types import (
    Account,
    Transaction,
    Transfer,
    Asset,
    Basket,
    ExchangeRate,
    Webhook,
    GCUInfo,
)

__all__ = [
    "FinAegis",
    "FinAegisError",
    "AuthenticationError",
    "NotFoundError",
    "ValidationError",
    "RateLimitError",
    "ServerError",
    "Account",
    "Transaction",
    "Transfer",
    "Asset",
    "Basket",
    "ExchangeRate",
    "Webhook",
    "GCUInfo",
]