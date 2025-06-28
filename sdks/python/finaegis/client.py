import os
from typing import Optional, Dict, Any
from urllib.parse import urljoin

import requests
from requests.adapters import HTTPAdapter
from urllib3.util.retry import Retry

from . import __version__
from .exceptions import handle_response_error
from .resources import (
    AccountsResource,
    TransactionsResource,
    TransfersResource,
    AssetsResource,
    BasketsResource,
    WebhooksResource,
    ExchangeRatesResource,
    GCUResource,
)


class FinAegis:
    """
    FinAegis API Client
    
    Example:
        >>> from finaegis import FinAegis
        >>> client = FinAegis(api_key='your-api-key', environment='sandbox')
        >>> accounts = client.accounts.list()
    """
    
    ENVIRONMENTS = {
        'production': 'https://api.finaegis.org/v2',
        'sandbox': 'https://api-sandbox.finaegis.org/v2',
        'local': 'http://localhost:8000/api/v2',
    }
    
    def __init__(
        self,
        api_key: Optional[str] = None,
        environment: str = 'production',
        base_url: Optional[str] = None,
        timeout: int = 30,
        max_retries: int = 3,
        verify_ssl: bool = True,
    ):
        """
        Initialize the FinAegis client.
        
        Args:
            api_key: Your FinAegis API key. Can also be set via FINAEGIS_API_KEY env var.
            environment: The API environment to use ('production', 'sandbox', 'local')
            base_url: Override the base URL (optional)
            timeout: Request timeout in seconds
            max_retries: Maximum number of retries for failed requests
            verify_ssl: Whether to verify SSL certificates
        """
        self.api_key = api_key or os.environ.get('FINAEGIS_API_KEY')
        if not self.api_key:
            raise ValueError("API key is required. Pass it as a parameter or set FINAEGIS_API_KEY environment variable.")
        
        self.base_url = base_url or self.ENVIRONMENTS.get(environment, self.ENVIRONMENTS['production'])
        self.timeout = timeout
        self.verify_ssl = verify_ssl
        
        # Setup session with retry logic
        self.session = requests.Session()
        self.session.headers.update({
            'Authorization': f'Bearer {self.api_key}',
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'User-Agent': f'FinAegis-Python-SDK/{__version__}',
        })
        
        # Configure retries
        retry_strategy = Retry(
            total=max_retries,
            backoff_factor=1,
            status_forcelist=[429, 500, 502, 503, 504],
            allowed_methods=["HEAD", "GET", "POST", "PUT", "DELETE", "OPTIONS", "TRACE"]
        )
        adapter = HTTPAdapter(max_retries=retry_strategy)
        self.session.mount("http://", adapter)
        self.session.mount("https://", adapter)
        
        # Initialize resources
        self.accounts = AccountsResource(self)
        self.transactions = TransactionsResource(self)
        self.transfers = TransfersResource(self)
        self.assets = AssetsResource(self)
        self.baskets = BasketsResource(self)
        self.webhooks = WebhooksResource(self)
        self.exchange_rates = ExchangeRatesResource(self)
        self.gcu = GCUResource(self)
    
    def request(
        self,
        method: str,
        path: str,
        params: Optional[Dict[str, Any]] = None,
        json: Optional[Dict[str, Any]] = None,
        **kwargs
    ) -> Dict[str, Any]:
        """
        Make a request to the FinAegis API.
        
        Args:
            method: HTTP method (GET, POST, PUT, DELETE, etc.)
            path: API endpoint path
            params: Query parameters
            json: JSON body data
            **kwargs: Additional arguments to pass to requests
            
        Returns:
            Response data as a dictionary
            
        Raises:
            FinAegisError: If the request fails
        """
        url = urljoin(self.base_url, path.lstrip('/'))
        
        response = self.session.request(
            method=method,
            url=url,
            params=params,
            json=json,
            timeout=self.timeout,
            verify=self.verify_ssl,
            **kwargs
        )
        
        # Handle errors
        if not response.ok:
            handle_response_error(response)
        
        # Return JSON response
        return response.json() if response.content else {}
    
    def get(self, path: str, params: Optional[Dict[str, Any]] = None, **kwargs) -> Dict[str, Any]:
        """Make a GET request."""
        return self.request('GET', path, params=params, **kwargs)
    
    def post(self, path: str, json: Optional[Dict[str, Any]] = None, **kwargs) -> Dict[str, Any]:
        """Make a POST request."""
        return self.request('POST', path, json=json, **kwargs)
    
    def put(self, path: str, json: Optional[Dict[str, Any]] = None, **kwargs) -> Dict[str, Any]:
        """Make a PUT request."""
        return self.request('PUT', path, json=json, **kwargs)
    
    def delete(self, path: str, **kwargs) -> Dict[str, Any]:
        """Make a DELETE request."""
        return self.request('DELETE', path, **kwargs)