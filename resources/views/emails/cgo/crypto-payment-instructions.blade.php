<x-mail::message>
# Crypto Payment Instructions

Thank you for your investment of ${{ $amount }} USD in the {{ company_name() }} Continuous Growth Offering.

@if (! empty($investment->crypto_address))
To complete your investment, please send your payment to this address:

<x-mail::panel>
{{ $investment->crypto_address }}
</x-mail::panel>

Please send only the cryptocurrency you chose at checkout. A payment in a different currency or on a different network may be lost.

We will email you again when we receive your payment.
@else
We could not find a payment address for this investment. Please contact our support team before you send any payment.
@endif

Best regards,<br>
{{ team_signature() }}
</x-mail::message>
