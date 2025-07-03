<x-mail::message>
# Welcome to FinAegis!

Thank you for subscribing to our updates. We're excited to have you as part of our community!

## What to expect:

- **Latest Updates**: Be the first to know about new features and announcements
- **Exclusive Content**: Access to insights and educational materials
- **Community Events**: Invitations to webinars and special events
- **Product News**: Early access to new products and features

## Stay Connected

Follow us on our social channels to stay up to date with the latest news:

<x-mail::button :url="config('app.url')">
Visit FinAegis
</x-mail::button>

Best regards,<br>
The FinAegis Team

<x-mail::subcopy>
If you no longer wish to receive these emails, you can <a href="{{ $unsubscribeUrl }}">unsubscribe here</a>.
</x-mail::subcopy>
</x-mail::message>