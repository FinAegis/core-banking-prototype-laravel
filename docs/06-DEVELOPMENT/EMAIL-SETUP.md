# Email Setup Guide

This guide explains how to configure email sending for the FinAegis platform.

## Quick Start with Resend (Recommended)

Resend is the simplest email service to set up for FinAegis. It needs only an API key. The production example files (`.env.production.example` and `.env.zelta.example`) already use it.

### 1. Create a Resend Account

1. Go to [https://resend.com](https://resend.com).
2. Sign up for a free account.
3. Verify your email address.

### 2. Get Your API Key

1. After you log in, go to [API Keys](https://resend.com/api-keys).
2. Create a new API key.
3. Copy the key. It starts with `re_`.

### 3. Resend package

The `resend/resend-php` package is already a project dependency in `composer.json`. You do not need to install it separately.

### 4. Configure Your Environment

Update your `.env` file:

```env
# Change from 'log' to 'resend'
MAIL_MAILER=resend

# Add your Resend API key
RESEND_KEY=re_your_api_key_here

# Update the from address (must be verified in Resend)
MAIL_FROM_ADDRESS=noreply@yourdomain.com
MAIL_FROM_NAME="${APP_NAME}"
```

### 5. Verify Your Domain (Optional but Recommended)

A verified domain improves deliverability.
1. Go to [Domains](https://resend.com/domains) in Resend.
2. Add your domain.
3. Follow the DNS verification steps.

## Testing Email Configuration

### Test in Tinker

```bash
php artisan tinker
```

```php
// Send a test email
Mail::raw('Test email from FinAegis', function ($message) {
    $message->to('your-email@example.com')
            ->subject('Test Email');
});
```

### Test CGO Notification

The CGO module is disabled on the production site, so run this test in a local or demo environment.

1. Log out of your account, or use a private browser window.
2. Visit `/cgo`.
3. Enter your email in the notification form.
4. Submit the form and check your inbox.

## Alternative Email Services

### Local Development Options

#### Option 1: Log Driver (Default)
```env
MAIL_MAILER=log
```
This is the default in `.env.example`. Emails are written to `storage/logs/laravel.log` and nothing is sent. It suits local development.

#### Option 2: Mailpit
```bash
# Install Mailpit
brew install mailpit  # macOS
# or download from https://github.com/axllent/mailpit

# Run Mailpit
mailpit

# Configure .env
MAIL_MAILER=smtp
MAIL_HOST=127.0.0.1
MAIL_PORT=1025
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null
```
You can view the emails at http://localhost:8025. No email leaves your machine.

### Production Options

#### Postmark
```env
MAIL_MAILER=postmark
POSTMARK_TOKEN=your-server-token
```

#### Mailgun
```env
MAIL_MAILER=mailgun
MAILGUN_DOMAIN=mg.yourdomain.com
MAILGUN_SECRET=your-api-key
MAILGUN_ENDPOINT=api.mailgun.net
```

#### Amazon SES
```env
MAIL_MAILER=ses
AWS_ACCESS_KEY_ID=your-key
AWS_SECRET_ACCESS_KEY=your-secret
AWS_DEFAULT_REGION=us-east-1
```

## Troubleshooting

### Emails Not Sending

1. Clear the cached configuration and restart the queue workers:
   ```bash
   php artisan config:cache
   php artisan queue:restart
   ```

2. Check the logs:
   ```bash
   tail -f storage/logs/laravel.log
   ```

3. Check the configuration values:
   ```bash
   php artisan tinker
   >>> config('mail.default')
   >>> config('services.resend.key')
   ```

### Common Issues

- "From address not verified" means the domain is not verified yet. Add and verify your domain in Resend.
- "API key invalid" means the key is wrong. Check `RESEND_KEY` in `.env`.
- If emails land in spam, verify your domain and set up SPF and DKIM records.

## Email Features in FinAegis

### CGO notifications
The CGO module sends a welcome email when someone signs up for early access. It also sends investment confirmations and payment status updates. The module is disabled on the production site.

### Newsletter
The Newsletter domain (`app/Domain/Newsletter/`) sends subscriber welcome emails, newsletter broadcasts and unsubscribe confirmations.

### Account emails
The platform sends email verification, password reset and security notifications.

## Best Practices

1. Always use queues for bulk emails:
   ```php
   Mail::to($user)->queue(new WelcomeEmail());
   ```

2. Set rate limits so that you stay within the service's limits.

3. Watch your email metrics in the service dashboard.

4. Keep email templates simple. Simple templates are delivered more reliably.

5. Test emails thoroughly before you deploy to production.

## Free Tier Limits

These limits were noted when this guide was written. Check each provider's current pricing.

- **Resend**: 100 emails/day (3,000/month)
- **Mailgun**: 1,000 emails/month
- **Postmark**: 100 emails/month
- **Amazon SES**: Pay as you go ($0.10 per 1,000 emails)

Resend's free tier is enough for FinAegis development.
