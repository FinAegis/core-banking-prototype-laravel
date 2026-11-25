import os
import time
import uuid
from typing import Optional, Dict, Any, Union
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

#: The HTTP header the API reads to recognise a repeated request.
IDEMPOTENCY_HEADER = 'Idempotency-Key'

#: Answers after which a request is sent again.
RETRY_STATUSES = (429, 500, 502, 503, 504)

#: Methods that can move money or create something, so they are only sent
#: again when they carry an Idempotency-Key.
_UNSAFE_METHODS = frozenset(['POST', 'PATCH'])


class _IdempotentMethodsRetry(Retry):
    """
    Transport-level retries that never resend a POST or a PATCH.

    urllib3 retries a failed connection for every method. The client retries
    POST and PATCH itself in FinAegis.request(), with the same Idempotency-Key,
    so the transport gives up on them at once instead of retrying them too.
    """

    def increment(self, method=None, url=None, *args, **kwargs):
        if method is not None and method.upper() in _UNSAFE_METHODS:
            return Retry.increment(self.new(total=0), method, url, *args, **kwargs)
        return super().increment(method, url, *args, **kwargs)


def _header(headers: Dict[str, Any], name: str) -> Optional[Any]:
    """Read a header value regardless of the case of its name."""
    for key, value in headers.items():
        if key.lower() == name.lower():
            return value
    return None


class FinAegis:
    """
    FinAegis API Client
    
    Example:
        >>> from finaegis import FinAegis
        >>> client = FinAegis(api_key='your-api-key', environment='sandbox')
        >>> accounts = client.accounts.list()
    """
    
    ENVIRONMENTS = {
        'production': 'https://api.zelta.app/v2',
        'sandbox': 'https://api-sandbox.zelta.app/v2',
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
            max_retries: How many times a failed request is sent again after a
                connection error, a timeout or a 429/5xx answer. 0 turns retries
                off. A POST is only sent again with the same Idempotency-Key.
            verify_ssl: Whether to verify SSL certificates
        """
        self.api_key = api_key or os.environ.get('FINAEGIS_API_KEY')
        if not self.api_key:
            raise ValueError("API key is required. Pass it as a parameter or set FINAEGIS_API_KEY environment variable.")
        
        self.base_url = base_url or self.ENVIRONMENTS.get(environment, self.ENVIRONMENTS['production'])
        self.timeout = timeout
        self.max_retries = max_retries
        self.verify_ssl = verify_ssl
        
        # Setup session with retry logic
        self.session = requests.Session()
        self.session.headers.update({
            'Authorization': f'Bearer {self.api_key}',
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'User-Agent': f'FinAegis-Python-SDK/{__version__}',
        })
        
        # Configure retries for idempotent methods. POST and PATCH are
        # retried in request(), and only with an Idempotency-Key.
        retry_strategy = _IdempotentMethodsRetry(
            total=max_retries,
            backoff_factor=1,
            status_forcelist=list(RETRY_STATUSES),
            allowed_methods=["HEAD", "GET", "PUT", "DELETE", "OPTIONS", "TRACE"]
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
        idempotency_key: Union[str, bool, None] = None,
        **kwargs
    ) -> Dict[str, Any]:
        """
        Make a request to the FinAegis API.
        
        Every POST carries an Idempotency-Key header. The client makes a new
        key for each call and sends the same key when it retries the call, so
        the API runs it at most once. A POST without a key is never retried.
        
        Args:
            method: HTTP method (GET, POST, PUT, DELETE, etc.)
            path: API endpoint path
            params: Query parameters
            json: JSON body data
            idempotency_key: Your own Idempotency-Key for a POST, or False to
                send none. The client makes one when it is left out.
            **kwargs: Additional arguments to pass to requests
            
        Returns:
            Response data as a dictionary
            
        Raises:
            FinAegisError: If the request fails
        """
        url = urljoin(self.base_url, path.lstrip('/'))
        method = method.upper()
        headers = dict(kwargs.pop('headers', None) or {})
        
        if method == 'POST' and idempotency_key is not False and not _header(headers, IDEMPOTENCY_HEADER):
            headers[IDEMPOTENCY_HEADER] = idempotency_key or str(uuid.uuid4())
        
        # The transport retries the other methods. POST and PATCH are retried
        # here, and only when they carry an Idempotency-Key.
        retries = 0
        if method in _UNSAFE_METHODS and _header(headers, IDEMPOTENCY_HEADER):
            retries = self.max_retries
        
        attempt = 0
        while True:
            try:
                response = self.session.request(
                    method=method,
                    url=url,
                    params=params,
                    json=json,
                    headers=headers,
                    timeout=self.timeout,
                    verify=self.verify_ssl,
                    **kwargs
                )
            except (requests.ConnectionError, requests.Timeout):
                if attempt >= retries:
                    raise
                retry_after = None
            else:
                if response.status_code not in RETRY_STATUSES or attempt >= retries:
                    break
                retry_after = response.headers.get('Retry-After')
            
            delay = float(2 ** attempt)
            if retry_after is not None and str(retry_after).isdigit():
                delay = max(delay, float(retry_after))
            time.sleep(delay)
            attempt += 1
        
        # Handle errors
        if not response.ok:
            handle_response_error(response)
        
        # Return JSON response
        return response.json() if response.content else {}
    
    def get(self, path: str, params: Optional[Dict[str, Any]] = None, **kwargs) -> Dict[str, Any]:
        """Make a GET request."""
        return self.request('GET', path, params=params, **kwargs)
    
    def post(
        self,
        path: str,
        json: Optional[Dict[str, Any]] = None,
        idempotency_key: Union[str, bool, None] = None,
        **kwargs
    ) -> Dict[str, Any]:
        """Make a POST request. It carries an Idempotency-Key; see request()."""
        return self.request('POST', path, json=json, idempotency_key=idempotency_key, **kwargs)
    
    def put(self, path: str, json: Optional[Dict[str, Any]] = None, **kwargs) -> Dict[str, Any]:
        """Make a PUT request."""
        return self.request('PUT', path, json=json, **kwargs)
    
    def delete(self, path: str, **kwargs) -> Dict[str, Any]:
        """Make a DELETE request."""
        return self.request('DELETE', path, **kwargs)