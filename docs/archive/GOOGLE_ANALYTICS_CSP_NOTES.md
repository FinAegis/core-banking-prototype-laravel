# Google Analytics CSP Configuration

## Overview

Google Analytics uses regional endpoints for data collection, which requires proper CSP configuration to avoid blocking legitimate requests.

## Regional Endpoints

Google Analytics may use different regional endpoints based on the user's location:
- `https://www.google-analytics.com` - Main endpoint
- `https://region1.google-analytics.com` - Regional endpoint
- `https://region2.google-analytics.com` - Regional endpoint
- etc.

## CSP Configuration

### Connect Sources Required

The following domains must be allowed in the `connect-src` CSP directive:

```
connect-src 'self' 
  https://www.google-analytics.com 
  https://*.google-analytics.com 
  https://stats.g.doubleclick.net 
  https://*.doubleclick.net 
  https://www.googletagmanager.com
```

### Script Sources Required

```
script-src 'self' 'unsafe-inline' 'unsafe-eval' 
  https://cdn.jsdelivr.net 
  https://www.googletagmanager.com
```

## Why Wildcards?

Google Analytics dynamically selects regional endpoints based on:
- User's geographic location
- Load balancing requirements
- Network optimization

Using wildcards (`*.google-analytics.com`) ensures all regional endpoints are allowed.

## Security Considerations

While wildcards reduce specificity, they are limited to Google's domains only:
- `*.google-analytics.com` only matches Google Analytics subdomains
- `*.doubleclick.net` only matches DoubleClick (Google's ad serving) subdomains

These are all legitimate Google-owned domains used for analytics.

## Testing

To verify CSP is working correctly:

1. Open browser developer console
2. Navigate to the site
3. Check for CSP violation errors
4. Verify Google Analytics is tracking properly in the GA dashboard

## Common Issues

If you see CSP errors like:
```
Refused to connect to 'https://region1.google-analytics.com/...' because it violates the following Content Security Policy directive
```

This means the regional endpoint wildcard is not properly configured.