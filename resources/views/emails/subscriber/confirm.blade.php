<x-mail::message>
# Confirm your subscription

Someone, hopefully you, asked to receive updates from {{ company_name() }} at this address. Confirm it and we'll start sending you news.

<x-mail::button :url="$confirmationUrl">
Confirm subscription
</x-mail::button>

The link expires in {{ $expiresInHours }} hours. If you didn't ask for this, you can ignore this email — you won't be subscribed.

Best regards,<br>
{{ team_signature() }}
</x-mail::message>
