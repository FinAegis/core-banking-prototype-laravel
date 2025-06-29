"""
Base resource class for all API resources
"""

from typing import TYPE_CHECKING, Dict, Any, Optional

if TYPE_CHECKING:
    from ..client import FinAegis


class BaseResource:
    """Base class for all API resources."""
    
    def __init__(self, client: 'FinAegis'):
        self.client = client
    
    def _get(self, path: str, params: Optional[Dict[str, Any]] = None) -> Dict[str, Any]:
        """Make a GET request."""
        return self.client.get(path, params=params)
    
    def _post(self, path: str, data: Optional[Dict[str, Any]] = None) -> Dict[str, Any]:
        """Make a POST request."""
        return self.client.post(path, json=data)
    
    def _put(self, path: str, data: Optional[Dict[str, Any]] = None) -> Dict[str, Any]:
        """Make a PUT request."""
        return self.client.put(path, json=data)
    
    def _delete(self, path: str) -> Dict[str, Any]:
        """Make a DELETE request."""
        return self.client.delete(path)