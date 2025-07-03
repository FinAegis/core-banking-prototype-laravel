<x-mail::message>
{!! Str::markdown($content) !!}

<x-mail::button :url="config('app.url')">
Visit FinAegis
</x-mail::button>

Best regards,<br>
The FinAegis Team

<x-mail::subcopy>
You're receiving this email because you subscribed to updates from FinAegis. If you no longer wish to receive these emails, you can <a href="{{ $unsubscribeUrl }}">unsubscribe here</a>.
</x-mail::subcopy>
</x-mail::message>