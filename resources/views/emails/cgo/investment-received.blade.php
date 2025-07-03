<x-mail::message>
# Investment Confirmation

Thank you for your investment in FinAegis Continuous Growth Offering!

We have received your {{ $tier }} tier investment of ${{ $amount }}.

## Investment Details:
- **Amount**: ${{ $amount }} USD
- **Tier**: {{ $tier }}
- **Shares**: {{ $shares }}
- **Investment ID**: {{ $investment->uuid }}
- **Date**: {{ $investment->created_at->format('F j, Y') }}

Your investment is currently being processed. Once confirmed, you will receive another email with your investment certificate and additional details.

<x-mail::button :url="route('cgo.payment.success', ['investment' => $investment->uuid])">
View Investment Status
</x-mail::button>

If you have any questions about your investment, please don't hesitate to contact our support team.

Thank you for believing in FinAegis!

Best regards,<br>
The FinAegis Team
</x-mail::message>