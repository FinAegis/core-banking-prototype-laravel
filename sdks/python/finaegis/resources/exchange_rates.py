"""
Exchange rates resource for the FinAegis SDK
"""

from typing import Dict, Any
from ..types import ExchangeRate, PaginatedResponse
from .base import BaseResource


class ExchangeRatesResource(BaseResource):
    """Manage exchange rates in the FinAegis platform."""
    
    def list(self, page: int = 1, per_page: int = 20) -> PaginatedResponse:
        """
        List all exchange rates.
        
        Args:
            page: Page number
            per_page: Items per page
            
        Returns:
            PaginatedResponse containing ExchangeRate objects
        """
        response = self._get('/exchange-rates', params={'page': page, 'per_page': per_page})
        return PaginatedResponse.from_dict(response, ExchangeRate)
    
    def get(self, from_asset: str, to_asset: str) -> ExchangeRate:
        """
        Get exchange rate between two assets.
        
        Args:
            from_asset: Source asset code
            to_asset: Target asset code
            
        Returns:
            ExchangeRate object
        """
        response = self._get(f'/exchange-rates/{from_asset}/{to_asset}')
        return ExchangeRate.from_dict(response['data'])
    
    def convert(self, from_asset: str, to_asset: str, amount: float) -> Dict[str, Any]:
        """
        Convert amount between two assets.
        
        Args:
            from_asset: Source asset code
            to_asset: Target asset code
            amount: Amount to convert
            
        Returns:
            Dictionary with conversion details
        """
        response = self._get(
            f'/exchange-rates/{from_asset}/{to_asset}/convert',
            params={'amount': amount}
        )
        return response['data']
    
    def refresh(self) -> Dict[str, Any]:
        """
        Refresh all exchange rates.
        
        Returns:
            Dictionary with refresh status
        """
        response = self._post('/exchange-rates/refresh')
        return response['data']