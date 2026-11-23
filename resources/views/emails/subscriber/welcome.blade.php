<x-mail::message>
# Welcome to {{ company_name() }}

Thank you for confirming your subscription.

We will email you when we launch something new or change something important. We do not send these emails often.

<x-mail::button :url="config('app.url')">
Visit {{ company_name() }}
</x-mail::button>

Best regards,<br>
{{ team_signature() }}

<x-mail::subcopy>
If you no longer want these emails, you can <a href="{{ $unsubscribeUrl }}">unsubscribe here</a>.
</x-mail::subcopy>
</x-mail::message>
