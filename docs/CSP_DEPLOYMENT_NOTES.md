# CSP Configuration Deployment Notes

## Important: After Deploying CSP Changes

When updating Content Security Policy (CSP) settings, you must:

1. **Update the .env file on production** to include Google Tag Manager:
   ```
   CSP_SCRIPT_SOURCES=https://cdn.jsdelivr.net,https://www.googletagmanager.com
   ```

2. **Clear the configuration cache**:
   ```bash
   php artisan config:clear
   php artisan config:cache
   ```

3. **Clear any CDN/proxy caches** if you're using services like Cloudflare

4. **Verify the headers** are being sent correctly:
   ```bash
   curl -I https://finaegis.org | grep -i content-security-policy
   ```

## Environment Variable Priority

The CSP configuration uses this priority:
1. Environment variables (if set) - **HIGHEST PRIORITY**
2. Default values in `config/security.php`

If `CSP_SCRIPT_SOURCES` is set in your .env file, it will override the default value in the config file.

## Current Required CSP Sources

### Script Sources
- `https://cdn.jsdelivr.net` - For CDN scripts
- `https://www.googletagmanager.com` - For Google Analytics

### Connect Sources  
- `self` - Same origin
- API endpoint (configured)
- WebSocket endpoint (configured)
- `https://www.google-analytics.com` - Google Analytics data
- `https://stats.g.doubleclick.net` - Google Analytics data

## Troubleshooting

If CSP errors persist after deployment:

1. Check if the environment variable is set correctly:
   ```bash
   php artisan tinker
   >>> env('CSP_SCRIPT_SOURCES')
   ```

2. Check the actual CSP header being sent:
   ```bash
   curl -s -I https://finaegis.org | grep -A5 -i content-security-policy
   ```

3. Ensure no web server (nginx/apache) is adding its own CSP headers

4. Check for any caching layers (Cloudflare, Varnish, etc.) that might be caching old headers