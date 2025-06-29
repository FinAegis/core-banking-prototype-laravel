"""
Assets resource for the FinAegis SDK
"""

from typing import Optional, Dict, Any
from ..types import Asset, PaginatedResponse
from .base import BaseResource


class AssetsResource(BaseResource):
    """Manage assets in the FinAegis platform."""
    
    def list(self, page: int = 1, per_page: int = 20) -> PaginatedResponse:
        """
        List all assets.
        
        Args:
            page: Page number
            per_page: Items per page
            
        Returns:
            PaginatedResponse containing Asset objects
        """
        response = self._get('/assets', params={'page': page, 'per_page': per_page})
        return PaginatedResponse.from_dict(response, Asset)
    
    def get(self, code: str) -> Asset:
        """
        Get asset details.
        
        Args:
            code: Asset code
            
        Returns:
            Asset object
        """
        response = self._get(f'/assets/{code}')
        return Asset.from_dict(response['data'])
    
    def create(
        self,
        code: str,
        name: str,
        asset_type: str,
        decimals: int = 2
    ) -> Asset:
        """
        Create a new asset.
        
        Args:
            code: Asset code (e.g., 'USD', 'EUR')
            name: Asset name
            asset_type: Type of asset ('fiat', 'crypto', 'commodity')
            decimals: Number of decimal places
            
        Returns:
            Created Asset object
        """
        response = self._post('/assets', {
            'code': code,
            'name': name,
            'type': asset_type,
            'decimals': decimals
        })
        return Asset.from_dict(response['data'])
    
    def update(
        self,
        code: str,
        name: Optional[str] = None,
        asset_type: Optional[str] = None,
        decimals: Optional[int] = None,
        is_active: Optional[bool] = None
    ) -> Asset:
        """
        Update an asset.
        
        Args:
            code: Asset code
            name: New asset name
            asset_type: New asset type
            decimals: New decimal places
            is_active: Whether the asset is active
            
        Returns:
            Updated Asset object
        """
        data: Dict[str, Any] = {}
        if name is not None:
            data['name'] = name
        if asset_type is not None:
            data['type'] = asset_type
        if decimals is not None:
            data['decimals'] = decimals
        if is_active is not None:
            data['is_active'] = is_active
            
        response = self._put(f'/assets/{code}', data)
        return Asset.from_dict(response['data'])
    
    def delete(self, code: str) -> Dict[str, str]:
        """
        Delete an asset.
        
        Args:
            code: Asset code
            
        Returns:
            Success message
        """
        return self._delete(f'/assets/{code}')