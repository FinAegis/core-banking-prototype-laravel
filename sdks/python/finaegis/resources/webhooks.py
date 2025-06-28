"""
Webhooks resource for the FinAegis SDK
"""

from typing import List, Optional, Dict, Any
from ..types import Webhook, PaginatedResponse
from .base import BaseResource


class WebhooksResource(BaseResource):
    """Manage webhooks in the FinAegis platform."""
    
    def list(self, page: int = 1, per_page: int = 20) -> PaginatedResponse:
        """
        List all webhooks.
        
        Args:
            page: Page number
            per_page: Items per page
            
        Returns:
            PaginatedResponse containing Webhook objects
        """
        response = self._get('/webhooks', params={'page': page, 'per_page': per_page})
        return PaginatedResponse.from_dict(response, Webhook)
    
    def create(
        self,
        name: str,
        url: str,
        events: List[str],
        headers: Optional[Dict[str, str]] = None,
        secret: Optional[str] = None
    ) -> Webhook:
        """
        Create a new webhook.
        
        Args:
            name: Webhook name
            url: Webhook URL
            events: List of events to subscribe to
            headers: Optional custom headers
            secret: Optional secret for signature verification
            
        Returns:
            Created Webhook object
        """
        data = {
            'name': name,
            'url': url,
            'events': events
        }
        if headers:
            data['headers'] = headers
        if secret:
            data['secret'] = secret
            
        response = self._post('/webhooks', data)
        return Webhook.from_dict(response['data'])
    
    def get(self, webhook_id: str) -> Webhook:
        """
        Get webhook details.
        
        Args:
            webhook_id: Webhook ID
            
        Returns:
            Webhook object
        """
        response = self._get(f'/webhooks/{webhook_id}')
        return Webhook.from_dict(response['data'])
    
    def update(
        self,
        webhook_id: str,
        name: Optional[str] = None,
        url: Optional[str] = None,
        events: Optional[List[str]] = None,
        headers: Optional[Dict[str, str]] = None,
        is_active: Optional[bool] = None
    ) -> Webhook:
        """
        Update a webhook.
        
        Args:
            webhook_id: Webhook ID
            name: New webhook name
            url: New webhook URL
            events: New list of events
            headers: New custom headers
            is_active: Whether the webhook is active
            
        Returns:
            Updated Webhook object
        """
        data: Dict[str, Any] = {}
        if name is not None:
            data['name'] = name
        if url is not None:
            data['url'] = url
        if events is not None:
            data['events'] = events
        if headers is not None:
            data['headers'] = headers
        if is_active is not None:
            data['is_active'] = is_active
            
        response = self._put(f'/webhooks/{webhook_id}', data)
        return Webhook.from_dict(response['data'])
    
    def delete(self, webhook_id: str) -> Dict[str, str]:
        """
        Delete a webhook.
        
        Args:
            webhook_id: Webhook ID
            
        Returns:
            Success message
        """
        return self._delete(f'/webhooks/{webhook_id}')
    
    def get_deliveries(self, webhook_id: str, page: int = 1, per_page: int = 20) -> Dict[str, Any]:
        """
        Get webhook delivery history.
        
        Args:
            webhook_id: Webhook ID
            page: Page number
            per_page: Items per page
            
        Returns:
            Paginated delivery history
        """
        response = self._get(
            f'/webhooks/{webhook_id}/deliveries',
            params={'page': page, 'per_page': per_page}
        )
        return response
    
    def get_events(self) -> Dict[str, List[Dict[str, str]]]:
        """
        Get available webhook events.
        
        Returns:
            Dictionary of events grouped by category
        """
        response = self._get('/webhooks/events')
        return response['data']