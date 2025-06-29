"""
Baskets resource for the FinAegis SDK
"""

from typing import Dict, Any, Optional, List
from ..types import Basket, PaginatedResponse
from .base import BaseResource


class BasketsResource(BaseResource):
    """Manage basket assets in the FinAegis platform."""
    
    def list(self, page: int = 1, per_page: int = 20) -> PaginatedResponse:
        """
        List all baskets.
        
        Args:
            page: Page number
            per_page: Items per page
            
        Returns:
            PaginatedResponse containing Basket objects
        """
        response = self._get('/baskets', params={'page': page, 'per_page': per_page})
        return PaginatedResponse.from_dict(response, Basket)
    
    def get(self, code: str) -> Basket:
        """
        Get basket details.
        
        Args:
            code: Basket code
            
        Returns:
            Basket object
        """
        response = self._get(f'/baskets/{code}')
        return Basket.from_dict(response['data'])
    
    def get_value(self, code: str) -> Dict[str, Any]:
        """
        Get current basket value.
        
        Args:
            code: Basket code
            
        Returns:
            Dictionary with value information
        """
        response = self._get(f'/baskets/{code}/value')
        return response['data']
    
    def get_history(
        self,
        code: str,
        period: str = '30d',
        interval: str = 'daily'
    ) -> List[Dict[str, Any]]:
        """
        Get basket value history.
        
        Args:
            code: Basket code
            period: Time period ('24h', '7d', '30d', '90d', '1y', 'all')
            interval: Data interval ('hourly', 'daily', 'weekly', 'monthly')
            
        Returns:
            List of historical value data points
        """
        response = self._get(
            f'/baskets/{code}/history',
            params={'period': period, 'interval': interval}
        )
        return response['data']
    
    def get_performance(self, code: str) -> Dict[str, Any]:
        """
        Get basket performance metrics.
        
        Args:
            code: Basket code
            
        Returns:
            Dictionary with performance metrics
        """
        response = self._get(f'/baskets/{code}/performance')
        return response['data']
    
    def create(
        self,
        code: str,
        name: str,
        composition: Dict[str, float],
        description: Optional[str] = None
    ) -> Basket:
        """
        Create a new basket.
        
        Args:
            code: Basket code
            name: Basket name
            composition: Dictionary mapping asset codes to weights
            description: Optional description
            
        Returns:
            Created Basket object
        """
        data = {
            'code': code,
            'name': name,
            'composition': composition
        }
        if description:
            data['description'] = description
            
        response = self._post('/baskets', data)
        return Basket.from_dict(response['data'])
    
    def rebalance(self, code: str, new_composition: Dict[str, float]) -> Dict[str, Any]:
        """
        Rebalance a basket with new composition.
        
        Args:
            code: Basket code
            new_composition: New composition weights
            
        Returns:
            Response with updated basket information
        """
        response = self._post(f'/baskets/{code}/rebalance', {
            'composition': new_composition
        })
        return response['data']
    
    def compose(self, account_uuid: str, basket_code: str, amount: int) -> Dict[str, Any]:
        """
        Compose basket tokens from underlying assets.
        
        Args:
            account_uuid: Account UUID
            basket_code: Basket code
            amount: Amount of basket tokens to create
            
        Returns:
            Transaction information
        """
        response = self._post(f'/accounts/{account_uuid}/baskets/compose', {
            'basket_code': basket_code,
            'amount': amount
        })
        return response['data']
    
    def decompose(self, account_uuid: str, basket_code: str, amount: int) -> Dict[str, Any]:
        """
        Decompose basket tokens into underlying assets.
        
        Args:
            account_uuid: Account UUID
            basket_code: Basket code
            amount: Amount of basket tokens to decompose
            
        Returns:
            Transaction information
        """
        response = self._post(f'/accounts/{account_uuid}/baskets/decompose', {
            'basket_code': basket_code,
            'amount': amount
        })
        return response['data']