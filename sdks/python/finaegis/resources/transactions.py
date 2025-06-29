"""
Transactions resource for the FinAegis SDK
"""

from ..types import Transaction, PaginatedResponse
from .base import BaseResource


class TransactionsResource(BaseResource):
    """Manage transactions in the FinAegis platform."""
    
    def list(self, page: int = 1, per_page: int = 20) -> PaginatedResponse:
        """
        List all transactions.
        
        Args:
            page: Page number
            per_page: Items per page
            
        Returns:
            PaginatedResponse containing Transaction objects
        """
        response = self._get('/transactions', params={'page': page, 'per_page': per_page})
        return PaginatedResponse.from_dict(response, Transaction)
    
    def get(self, transaction_id: str) -> Transaction:
        """
        Get transaction details.
        
        Args:
            transaction_id: Transaction ID
            
        Returns:
            Transaction object
        """
        response = self._get(f'/transactions/{transaction_id}')
        return Transaction.from_dict(response['data'])