# Email Setup Guide

This guide explains how to configure email sending for the FinAegis platform.

## Quick Start with Resend (Recommended)

Resend is the simplest email service to set up for FinAegis. It requires only an API key and works immediately.

### 1. Create a Resend Account

1. Go to [https://resend.com](https://resend.com)
2. Sign up for a free account (no credit card required)
3. Verify your email address

### 2. Get Your API Key

1. Once logged in, go to [API Keys](https://resend.com/api-keys)
2. Create a new API key
3. Copy the key (it starts with `re_`)

### 3. Install Resend Package

```bash
composer require resend/resend-php
```

### 4. Configure Your Environment

Update your `.env` file:

```env
# Change from 'log' to 'resend'
MAIL_MAILER=resend

# Add your Resend API key
RESEND_KEY=re_your_api_key_here

# Update the from address (must be verified in Resend)
MAIL_FROM_ADDRESS=noreply@yourdomain.com
MAIL_FROM_NAME="FinAegis"
```

### 5. Verify Your Domain (Optional but Recommended)

For better deliverability:
1. Go to [Domains](https://resend.com/domains) in Resend
2. Add your domain
3. Follow the DNS verification steps

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

1. Log out of your account (or use incognito mode)
2. Visit `/cgo`
3. Enter your email in the notification form
4. Submit and check your inbox

## Alternative Email Services

### Local Development Options

#### Option 1: Log Driver (Default)
```env
MAIL_MAILER=log
```
- Emails are written to `storage/logs/laravel.log`
- No actual emails sent
- Perfect for development

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
- View emails at http://localhost:8025
- No emails leave your machine

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

1. Check your mail driver:
   ```bash
   php artisan config:cache
   php artisan queue:restart
   ```

2. Check logs:
   ```bash
   tail -f storage/logs/laravel.log
   ```

3. Verify configuration:
   ```bash
   php artisan tinker
   >>> config('mail.default')
   >>> config('services.resend.key')
   ```

### Common Issues

- **"From address not verified"**: Add and verify your domain in Resend
- **"API key invalid"**: Double-check your RESEND_KEY in .env
- **Emails in spam**: Verify your domain and set up SPF/DKIM records

## Email Features in FinAegis

### CGO Notifications
- Welcome email when users sign up for early access
- Investment confirmation emails
- Payment status updates

### Newsletter System
- Subscriber welcome emails
- Newsletter broadcasts
- Unsubscribe confirmations

### Account Emails
- Email verification
- Password reset
- Security notifications

## Best Practices

1. **Always use queues for bulk emails**:
   ```php
   Mail::to($user)->queue(new WelcomeEmail());
   ```

2. **Set appropriate rate limits** to avoid hitting service limits

3. **Monitor your email metrics** in your service dashboard

4. **Keep email templates simple** for better deliverability

5. **Test emails thoroughly** before production deployment

## Free Tier Limits

- **Resend**: 100 emails/day (3,000/month)
- **Mailgun**: 1,000 emails/month
- **Postmark**: 100 emails/month
- **Amazon SES**: Pay as you go ($0.10 per 1,000 emails)

For FinAegis development, Resend's free tier is more than sufficient.