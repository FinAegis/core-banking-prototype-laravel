<x-mail::message>
# Daily Reconciliation Report - {{ $summary['date'] }}

@if($hasDiscrepancies)
<x-mail::panel>
**⚠️ ACTION REQUIRED: {{ $summary['discrepancies_found'] }} discrepancies found**
</x-mail::panel>
@else
<x-mail::panel>
**✅ All balances reconciled successfully**
</x-mail::panel>
@endif

## Summary

- **Accounts Checked:** {{ $summary['accounts_checked'] }}
- **Discrepancies Found:** {{ $summary['discrepancies_found'] }}
@if($summary['discrepancies_found'] > 0)
- **Total Discrepancy Amount:** ${{ number_format($summary['total_discrepancy_amount'] / 100, 2) }}
@endif
- **Duration:** {{ $summary['duration_minutes'] ?? 0 }} minutes

@if($hasDiscrepancies)
## Discrepancies Details

@foreach($discrepancies as $discrepancy)
@if($discrepancy['type'] === 'balance_mismatch')
### Balance Mismatch
- **Account:** {{ $discrepancy['account_uuid'] }}
- **Asset:** {{ $discrepancy['asset_code'] }}
- **Internal Balance:** ${{ number_format($discrepancy['internal_balance'] / 100, 2) }}
- **External Balance:** ${{ number_format($discrepancy['external_balance'] / 100, 2) }}
- **Difference:** ${{ number_format($discrepancy['difference'] / 100, 2) }}

@elseif($discrepancy['type'] === 'stale_data')
### Stale Data Warning
- **Account:** {{ $discrepancy['account_uuid'] }}
- **Custodian:** {{ $discrepancy['custodian_id'] }}
- **Last Synced:** {{ $discrepancy['last_synced_at'] }}

@elseif($discrepancy['type'] === 'orphaned_balance')
### Orphaned Balance
- **Account:** {{ $discrepancy['account_uuid'] }}
- **Issue:** {{ $discrepancy['message'] }}
@endif

---
@endforeach

<x-mail::button :url="config('app.url') . '/admin'">
View Admin Dashboard
</x-mail::button>

@endif

## Next Steps

@if($hasDiscrepancies)
1. Review each discrepancy in the admin dashboard
2. Contact custodians for balance verification
3. Update internal records as necessary
4. Document resolution actions
@else
No action required. All systems operating normally.
@endif

Thanks,<br>
{{ config('app.name') }} Reconciliation System
</x-mail::message>