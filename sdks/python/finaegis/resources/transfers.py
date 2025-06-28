"""
Transfers resource for the FinAegis SDK
"""

from typing import Optional
from ..types import Transfer
from .base import BaseResource


class TransfersResource(BaseResource):
    """Manage transfers in the FinAegis platform."""
    
    def create(
        self,
        from_account: str,
        to_account: str,
        amount: int,
        asset_code: str = 'USD',
        reference: Optional[str] = None,
        workflow_enabled: bool = True
    ) -> Transfer:
        """
        Create a new transfer.
        
        Args:
            from_account: Source account UUID
            to_account: Destination account UUID
            amount: Amount in cents
            asset_code: Asset code (default: USD)
            reference: Optional reference for the transfer
            workflow_enabled: Whether to enable workflow processing
            
        Returns:
            Transfer object
        """
        data = {
            'from_account': from_account,
            'to_account': to_account,
            'amount': amount,
            'asset_code': asset_code,
            'workflow_enabled': workflow_enabled
        }
        if reference:
            data['reference'] = reference
            
        response = self._post('/transfers', data)
        return Transfer.from_dict(response['data'])
    
    def get(self, uuid: str) -> Transfer:
        """
        Get transfer details.
        
        Args:
            uuid: Transfer UUID
            
        Returns:
            Transfer object
        """
        response = self._get(f'/transfers/{uuid}')
        return Transfer.from_dict(response['data'])