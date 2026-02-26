<x-mail::message>
# Thank You for Your Interest in {{ company_name() }} CGO

You've been added to our notification list for updates about the {{ company_name() }} Continuous Growth Offering (CGO).

## What happens next?

- We'll keep you informed as the **CGO concept develops**
- You'll receive updates on platform milestones and releases
- You'll be among the first to know when the CGO programme opens

## Why {{ company_name() }}?

- **Democratic Banking**: Community-driven governance model
- **Real Assets**: Backed by actual bank accounts and global currencies
- **Open Source**: Transparent, auditable codebase you can explore today
- **Continuous Growth**: A funding model designed for long-term alignment

<x-mail::panel>
**Note**: The CGO is currently a conceptual model. No investment is being solicited at this time. We'll notify you when there are meaningful updates.
</x-mail::panel>

## Stay Connected

Follow our progress and get the latest updates:

<x-mail::button :url="config('app.url') . '/cgo'">
Visit CGO Page
</x-mail::button>

If you have any questions, feel free to reach out at {{ support_email() }}.

Best regards,<br>
{{ team_signature() }}

<x-mail::subcopy>
You're receiving this email because you signed up for CGO notifications at {{ $email }}.
If you didn't sign up, please ignore this email.
</x-mail::subcopy>
</x-mail::message>
