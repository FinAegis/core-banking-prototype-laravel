<x-mail::message>
# Investment Confirmed!

Congratulations! Your investment in FinAegis Continuous Growth Offering has been successfully confirmed.

## Investment Details:

<x-mail::panel>
**Certificate Number**: {{ $certificateNumber }}  
**Investment Amount**: ${{ $amount }} USD  
**Tier**: {{ $tier }}  
**Shares Purchased**: {{ $shares }}  
**Ownership Percentage**: {{ $ownershipPercentage }}%  
**Investment Date**: {{ $investment->created_at->format('F j, Y') }}
</x-mail::panel>

Your investment certificate is now available for download. You can access it anytime from your dashboard.

<x-mail::button :url="$certificateUrl">
Download Investment Certificate
</x-mail::button>

## What's Next?

- Your shares are now officially registered in our system
- You will receive quarterly updates on company performance
- You have full voting rights proportional to your ownership
- Dividends (when declared) will be distributed to your registered account

## Important Information:

- Keep your certificate number safe for future reference
- Your investment is subject to the terms and conditions outlined in the investment agreement
- For any questions, please contact our investor relations team

Thank you for investing in FinAegis! We're excited to have you as part of our journey.

Best regards,<br>
The FinAegis Team

<x-mail::subcopy>
This email confirms your investment in FinAegis Ltd. If you did not make this investment, please contact us immediately at invest@finaegis.com.
</x-mail::subcopy>
</x-mail::message>