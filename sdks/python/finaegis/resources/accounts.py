"""
Accounts resource for the FinAegis SDK
"""

from typing import Dict, Any, Optional, List
from ..types import Account, Transaction, Transfer, PaginatedResponse
from .base import BaseResource


class AccountsResource(BaseResource):
    """Manage accounts in the FinAegis platform."""
    
    def list(self, page: int = 1, per_page: int = 20) -> PaginatedResponse:
        """
        List all accounts.
        
        Args:
            page: Page number
            per_page: Items per page
            
        Returns:
            PaginatedResponse containing Account objects
        """
        response = self._get('/accounts', params={'page': page, 'per_page': per_page})
        return PaginatedResponse.from_dict(response, Account)
    
    def create(
        self,
        user_uuid: str,
        name: str,
        initial_balance: Optional[int] = None
    ) -> Account:
        """
        Create a new account.
        
        Args:
            user_uuid: UUID of the user
            name: Account name
            initial_balance: Initial balance in cents
            
        Returns:
            Created Account object
        """
        data = {
            'user_uuid': user_uuid,
            'name': name,
        }
        if initial_balance is not None:
            data['initial_balance'] = initial_balance
            
        response = self._post('/accounts', data)
        return Account.from_dict(response['data'])
    
    def get(self, uuid: str) -> Account:
        """
        Get account details.
        
        Args:
            uuid: Account UUID
            
        Returns:
            Account object
        """
        response = self._get(f'/accounts/{uuid}')
        return Account.from_dict(response['data'])
    
    def delete(self, uuid: str) -> Dict[str, str]:
        """
        Delete an account.
        
        Args:
            uuid: Account UUID
            
        Returns:
            Success message
        """
        return self._delete(f'/accounts/{uuid}')
    
    def freeze(self, uuid: str, reason: str, authorized_by: Optional[str] = None) -> Dict[str, str]:
        """
        Freeze an account.
        
        Args:
            uuid: Account UUID
            reason: Reason for freezing
            authorized_by: Who authorized the freeze
            
        Returns:
            Success message
        """
        data = {'reason': reason}
        if authorized_by:
            data['authorized_by'] = authorized_by
            
        return self._post(f'/accounts/{uuid}/freeze', data)
    
    def unfreeze(self, uuid: str, reason: str, authorized_by: Optional[str] = None) -> Dict[str, str]:
        """
        Unfreeze an account.
        
        Args:
            uuid: Account UUID
            reason: Reason for unfreezing
            authorized_by: Who authorized the unfreeze
            
        Returns:
            Success message
        """
        data = {'reason': reason}
        if authorized_by:
            data['authorized_by'] = authorized_by
            
        return self._post(f'/accounts/{uuid}/unfreeze', data)
    
    def get_balances(self, uuid: str) -> Dict[str, Any]:
        """
        Get account balances for all assets.
        
        Args:
            uuid: Account UUID
            
        Returns:
            Dictionary containing balance information
        """
        response = self._get(f'/accounts/{uuid}/balances')
        return response['data']
    
    def deposit(self, uuid: str, amount: int, asset_code: str = 'USD') -> Transaction:
        """
        Deposit funds to an account.
        
        Args:
            uuid: Account UUID
            amount: Amount in cents
            asset_code: Asset code (default: USD)
            
        Returns:
            Transaction object
        """
        response = self._post(f'/accounts/{uuid}/deposit', {
            'amount': amount,
            'asset_code': asset_code
        })
        return Transaction.from_dict(response['data'])
    
    def withdraw(self, uuid: str, amount: int, asset_code: str = 'USD') -> Transaction:
        """
        Withdraw funds from an account.
        
        Args:
            uuid: Account UUID
            amount: Amount in cents
            asset_code: Asset code (default: USD)
            
        Returns:
            Transaction object
        """
        response = self._post(f'/accounts/{uuid}/withdraw', {
            'amount': amount,
            'asset_code': asset_code
        })
        return Transaction.from_dict(response['data'])
    
    def get_transactions(self, uuid: str, page: int = 1, per_page: int = 20) -> PaginatedResponse:
        """
        Get account transaction history.
        
        Args:
            uuid: Account UUID
            page: Page number
            per_page: Items per page
            
        Returns:
            PaginatedResponse containing Transaction objects
        """
        response = self._get(
            f'/accounts/{uuid}/transactions',
            params={'page': page, 'per_page': per_page}
        )
        return PaginatedResponse.from_dict(response, Transaction)
    
    def get_transfers(self, uuid: str, page: int = 1, per_page: int = 20) -> PaginatedResponse:
        """
        Get account transfer history.
        
        Args:
            uuid: Account UUID
            page: Page number
            per_page: Items per page
            
        Returns:
            PaginatedResponse containing Transfer objects
        """
        response = self._get(
            f'/accounts/{uuid}/transfers',
            params={'page': page, 'per_page': per_page}
        )
        return PaginatedResponse.from_dict(response, Transfer)