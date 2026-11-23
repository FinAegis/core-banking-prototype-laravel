<x-mail::message>
# Confirm your subscription

Someone asked to receive updates from {{ company_name() }} at this address. Confirm the address and we will start sending you news.

<x-mail::button :url="$confirmationUrl">
Confirm subscription
</x-mail::button>

The link expires in {{ $expiresInHours }} hours. If you did not ask for this, you can ignore this email. You will not be subscribed.

Best regards,<br>
{{ team_signature() }}
</x-mail::message>
