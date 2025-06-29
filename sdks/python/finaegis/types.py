"""
Type definitions for the FinAegis SDK
"""

from dataclasses import dataclass
from datetime import datetime
from typing import Dict, List, Optional, Any, Union


@dataclass
class Account:
    """Represents a FinAegis account."""
    uuid: str
    user_uuid: str
    name: str
    balance: float
    frozen: bool
    created_at: datetime
    updated_at: datetime
    
    @classmethod
    def from_dict(cls, data: Dict[str, Any]) -> 'Account':
        return cls(
            uuid=data['uuid'],
            user_uuid=data['user_uuid'],
            name=data['name'],
            balance=float(data['balance']),
            frozen=data.get('frozen', False),
            created_at=datetime.fromisoformat(data['created_at'].replace('Z', '+00:00')),
            updated_at=datetime.fromisoformat(data['updated_at'].replace('Z', '+00:00'))
        )


@dataclass
class Transaction:
    """Represents a transaction."""
    id: str
    account_uuid: str
    type: str  # 'deposit' or 'withdrawal'
    amount: float
    asset_code: str
    status: str  # 'pending', 'completed', 'failed'
    reference: Optional[str]
    created_at: datetime
    completed_at: Optional[datetime]
    
    @classmethod
    def from_dict(cls, data: Dict[str, Any]) -> 'Transaction':
        return cls(
            id=data['id'],
            account_uuid=data['account_uuid'],
            type=data['type'],
            amount=float(data['amount']),
            asset_code=data['asset_code'],
            status=data['status'],
            reference=data.get('reference'),
            created_at=datetime.fromisoformat(data['created_at'].replace('Z', '+00:00')),
            completed_at=datetime.fromisoformat(data['completed_at'].replace('Z', '+00:00')) if data.get('completed_at') else None
        )


@dataclass
class Transfer:
    """Represents a transfer between accounts."""
    uuid: str
    from_account: str
    to_account: str
    amount: float
    asset_code: str
    reference: Optional[str]
    status: str  # 'pending', 'completed', 'failed'
    created_at: datetime
    completed_at: Optional[datetime]
    
    @classmethod
    def from_dict(cls, data: Dict[str, Any]) -> 'Transfer':
        return cls(
            uuid=data['uuid'],
            from_account=data['from_account'],
            to_account=data['to_account'],
            amount=float(data['amount']),
            asset_code=data['asset_code'],
            reference=data.get('reference'),
            status=data['status'],
            created_at=datetime.fromisoformat(data['created_at'].replace('Z', '+00:00')),
            completed_at=datetime.fromisoformat(data['completed_at'].replace('Z', '+00:00')) if data.get('completed_at') else None
        )


@dataclass
class Asset:
    """Represents an asset."""
    code: str
    name: str
    type: str  # 'fiat', 'crypto', 'commodity'
    decimals: int
    is_active: bool
    created_at: datetime
    updated_at: datetime
    
    @classmethod
    def from_dict(cls, data: Dict[str, Any]) -> 'Asset':
        return cls(
            code=data['code'],
            name=data['name'],
            type=data['type'],
            decimals=data['decimals'],
            is_active=data['is_active'],
            created_at=datetime.fromisoformat(data['created_at'].replace('Z', '+00:00')),
            updated_at=datetime.fromisoformat(data['updated_at'].replace('Z', '+00:00'))
        )


@dataclass
class Basket:
    """Represents a basket asset."""
    code: str
    name: str
    description: Optional[str]
    composition: Dict[str, float]
    value_usd: float
    is_active: bool
    created_at: datetime
    updated_at: datetime
    
    @classmethod
    def from_dict(cls, data: Dict[str, Any]) -> 'Basket':
        return cls(
            code=data['code'],
            name=data['name'],
            description=data.get('description'),
            composition=data['composition'],
            value_usd=float(data['value_usd']),
            is_active=data['is_active'],
            created_at=datetime.fromisoformat(data['created_at'].replace('Z', '+00:00')),
            updated_at=datetime.fromisoformat(data['updated_at'].replace('Z', '+00:00'))
        )


@dataclass
class ExchangeRate:
    """Represents an exchange rate."""
    from_asset: str
    to_asset: str
    rate: float
    last_updated: datetime
    
    @classmethod
    def from_dict(cls, data: Dict[str, Any]) -> 'ExchangeRate':
        return cls(
            from_asset=data['from_asset'],
            to_asset=data['to_asset'],
            rate=float(data['rate']),
            last_updated=datetime.fromisoformat(data['last_updated'].replace('Z', '+00:00'))
        )


@dataclass
class Webhook:
    """Represents a webhook configuration."""
    uuid: str
    name: str
    url: str
    events: List[str]
    headers: Optional[Dict[str, str]]
    is_active: bool
    created_at: datetime
    updated_at: datetime
    
    @classmethod
    def from_dict(cls, data: Dict[str, Any]) -> 'Webhook':
        return cls(
            uuid=data['uuid'],
            name=data['name'],
            url=data['url'],
            events=data['events'],
            headers=data.get('headers'),
            is_active=data['is_active'],
            created_at=datetime.fromisoformat(data['created_at'].replace('Z', '+00:00')),
            updated_at=datetime.fromisoformat(data['updated_at'].replace('Z', '+00:00'))
        )


@dataclass
class GCUComposition:
    """Represents a GCU composition component."""
    asset_code: str
    asset_name: str
    asset_type: str
    weight: float
    current_price_usd: float
    value_contribution_usd: float
    percentage_of_basket: float
    change_24h: float
    change_7d: float
    
    @classmethod
    def from_dict(cls, data: Dict[str, Any]) -> 'GCUComposition':
        return cls(
            asset_code=data['asset_code'],
            asset_name=data['asset_name'],
            asset_type=data['asset_type'],
            weight=float(data['weight']),
            current_price_usd=float(data['current_price_usd']),
            value_contribution_usd=float(data['value_contribution_usd']),
            percentage_of_basket=float(data['percentage_of_basket']),
            change_24h=float(data['24h_change']),
            change_7d=float(data['7d_change'])
        )


@dataclass
class GCUInfo:
    """Represents GCU information."""
    basket_code: str
    name: str
    total_value_usd: float
    composition: List[GCUComposition]
    last_updated: datetime
    
    @classmethod
    def from_dict(cls, data: Dict[str, Any]) -> 'GCUInfo':
        return cls(
            basket_code=data['basket_code'],
            name=data.get('name', 'Global Currency Unit'),
            total_value_usd=float(data['total_value_usd']),
            composition=[GCUComposition.from_dict(c) for c in data['composition']],
            last_updated=datetime.fromisoformat(data['last_updated'].replace('Z', '+00:00'))
        )


@dataclass
class PaginatedResponse:
    """Represents a paginated API response."""
    data: List[Any]
    current_page: int
    per_page: int
    total: int
    last_page: int
    
    @classmethod
    def from_dict(cls, response: Dict[str, Any], item_class: type) -> 'PaginatedResponse':
        meta = response.get('meta', {})
        return cls(
            data=[item_class.from_dict(item) for item in response.get('data', [])],
            current_page=meta.get('current_page', 1),
            per_page=meta.get('per_page', 20),
            total=meta.get('total', 0),
            last_page=meta.get('last_page', 1)
        )