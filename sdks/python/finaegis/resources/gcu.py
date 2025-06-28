"""
GCU (Global Currency Unit) resource for the FinAegis SDK
"""

from typing import Dict, Any, List, Optional
from ..types import GCUInfo
from .base import BaseResource


class GCUResource(BaseResource):
    """Manage GCU operations in the FinAegis platform."""
    
    def get_info(self) -> GCUInfo:
        """
        Get GCU information.
        
        Returns:
            GCUInfo object
        """
        response = self._get('/gcu')
        return GCUInfo.from_dict(response['data'])
    
    def get_composition(self) -> GCUInfo:
        """
        Get real-time GCU composition.
        
        Returns:
            GCUInfo object with current composition
        """
        response = self._get('/gcu/composition')
        return GCUInfo.from_dict(response['data'])
    
    def get_value_history(
        self,
        period: str = '30d',
        interval: str = 'daily'
    ) -> List[Dict[str, Any]]:
        """
        Get GCU value history.
        
        Args:
            period: Time period ('24h', '7d', '30d', '90d', '1y', 'all')
            interval: Data interval ('hourly', 'daily', 'weekly', 'monthly')
            
        Returns:
            List of historical value data points
        """
        response = self._get(
            '/gcu/value-history',
            params={'period': period, 'interval': interval}
        )
        return response['data']
    
    def get_active_polls(self) -> List[Dict[str, Any]]:
        """
        Get active governance polls.
        
        Returns:
            List of active poll information
        """
        response = self._get('/gcu/governance/active-polls')
        return response['data']
    
    def get_supported_banks(self) -> List[Dict[str, Any]]:
        """
        Get supported banks for GCU operations.
        
        Returns:
            List of supported bank information
        """
        response = self._get('/gcu/supported-banks')
        return response['data']