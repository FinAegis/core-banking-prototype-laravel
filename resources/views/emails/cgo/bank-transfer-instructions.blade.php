@php
    $bank = config('cgo.bank_details', []);
    $reference = $investment->bank_transfer_reference ?: 'CGO-' . $investment->uuid;
@endphp
<x-mail::message>
# Bank Transfer Instructions

Thank you for your investment of ${{ $amount }} USD in the {{ company_name() }} Continuous Growth Offering.

To complete your investment, please send a bank transfer to the account below.

<x-mail::panel>
@if (! empty($bank['bank_name']))
**Bank**: {{ $bank['bank_name'] }}<br>
@endif
@if (! empty($bank['account_name']))
**Account name**: {{ $bank['account_name'] }}<br>
@endif
@if (! empty($bank['account_number']))
**Account number**: {{ $bank['account_number'] }}<br>
@endif
@if (! empty($bank['iban']))
**IBAN**: {{ $bank['iban'] }}<br>
@endif
@if (! empty($bank['swift_code']))
**SWIFT code**: {{ $bank['swift_code'] }}<br>
@endif
@if (! empty($bank['routing_number']))
**Routing number**: {{ $bank['routing_number'] }}<br>
@endif
**Amount**: ${{ $amount }} USD<br>
**Payment reference**: {{ $reference }}
</x-mail::panel>

Please add the payment reference to your transfer. We use it to match your payment to your investment.

We will email you again when we receive your payment.

Best regards,<br>
{{ team_signature() }}
</x-mail::message>
