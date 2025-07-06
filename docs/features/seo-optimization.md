# SEO Optimization Guide

## Overview

FinAegis implements comprehensive SEO optimization across all public-facing pages to ensure maximum visibility in search engines and proper social media sharing.

## SEO Components

### 1. Meta Tags
Every public page includes:
- **Title**: Descriptive, keyword-rich titles (50-60 characters)
- **Description**: Compelling descriptions (150-160 characters)
- **Keywords**: Relevant keywords for the page content
- **Robots**: Instructions for search engine crawlers
- **Canonical URL**: Prevents duplicate content issues

### 2. Open Graph Protocol
For optimal social media sharing:
- `og:title` - Page title for social shares
- `og:description` - Description for social previews
- `og:url` - Canonical URL
- `og:type` - Content type (website)
- `og:image` - Preview image (1200x630px)
- `og:site_name` - FinAegis
- `og:locale` - en_US

### 3. Twitter Cards
Enhanced Twitter sharing with:
- `twitter:card` - summary_large_image
- `twitter:title` - Page title
- `twitter:description` - Description
- `twitter:image` - Preview image

### 4. Additional SEO Features
- **Favicon**: Multiple sizes for all devices
- **Sitemap.xml**: Auto-generated with all public URLs
- **Robots.txt**: Proper crawling instructions
- **Schema.org**: Structured data support (ready for implementation)

## Implementation

### SEO Partial Component
All SEO meta tags are centralized in `resources/views/partials/seo.blade.php`:

```blade
@include('partials.seo', [
    'title' => 'Page Title',
    'description' => 'Page description for search engines',
    'keywords' => 'relevant, keywords, here',
    'canonical' => 'https://example.com/page', // Optional
    'ogImage' => '/images/custom-og.png', // Optional
    'schema' => $schemaMarkup // Optional
])
```

### Default Values
If parameters are not provided, sensible defaults are used:
- **Description**: Main FinAegis platform description
- **Keywords**: Core FinAegis keywords
- **OG Image**: Default Open Graph image at `/images/og-default.png`

## Page-Specific Optimizations

### Homepage (`/`)
- **Title**: FinAegis - The Enterprise Financial Platform
- **Focus**: Brand awareness, main features
- **Keywords**: Banking platform, GCU, democratic banking

### About Page (`/about`)
- **Title**: About FinAegis - Our Mission & Team
- **Focus**: Company story, mission, team
- **Keywords**: Mission, team, financial revolution

### Platform Page (`/platform`)
- **Title**: FinAegis Platform - Open Banking for Developers
- **Focus**: Developer features, open source
- **Keywords**: Banking infrastructure, API, MIT license

### GCU Page (`/gcu`)
- **Title**: Global Currency Unit (GCU) - FinAegis
- **Focus**: GCU features, democratic governance
- **Keywords**: GCU, basket currency, community governance

### Pricing Page (`/pricing`)
- **Title**: Pricing - Flexible Plans for Every Scale | FinAegis
- **Focus**: Pricing tiers, value proposition
- **Keywords**: Pricing, open source, enterprise support

### Security Page (`/security`)
- **Title**: Security - Bank-Grade Protection | FinAegis
- **Focus**: Security features, compliance
- **Keywords**: Security, encryption, compliance, GDPR

## Best Practices

### Title Tags
- Keep under 60 characters
- Include brand name
- Use pipe (|) or dash (-) as separator
- Front-load important keywords

### Meta Descriptions
- Keep between 150-160 characters
- Include call-to-action when appropriate
- Use active voice
- Include primary keywords naturally

### Keywords
- Use 5-10 relevant keywords
- Separate with commas
- Include variations and long-tail keywords
- Update based on search trends

### Open Graph Images
- Recommended size: 1200x630 pixels
- Include brand elements
- Use text overlays for context
- Compress for fast loading

## Adding SEO to New Pages

1. Include the SEO partial in the page head:
```blade
@include('partials.seo', [
    'title' => 'Your Page Title',
    'description' => 'Compelling description here',
    'keywords' => 'relevant, keywords',
])
```

2. For custom Open Graph images:
```blade
@include('partials.seo', [
    'title' => 'Your Page Title',
    'description' => 'Description',
    'keywords' => 'keywords',
    'ogImage' => asset('images/custom-og-image.png'),
])
```

3. For pages with structured data:
```blade
@php
$schema = '<script type="application/ld+json">
{
    "@context": "https://schema.org",
    "@type": "Organization",
    "name": "FinAegis",
    "url": "https://finaegis.com"
}
</script>';
@endphp

@include('partials.seo', [
    'title' => 'Title',
    'description' => 'Description',
    'keywords' => 'keywords',
    'schema' => $schema,
])
```

## Testing SEO

### Manual Testing
1. View page source to verify meta tags
2. Use browser extensions like "SEO Meta in 1 Click"
3. Test social sharing with Facebook Debugger and Twitter Card Validator

### Automated Testing
Run the SEO test suite:
```bash
./vendor/bin/pest tests/Feature/SeoMetaTagsTest.php
```

The tests verify:
- Presence of all required meta tags
- Proper Open Graph implementation
- Twitter Card tags
- Canonical URLs
- Meta description length

## Monitoring and Maintenance

### Regular Reviews
- Monthly review of meta descriptions
- Quarterly keyword research updates
- Annual comprehensive SEO audit

### Performance Metrics
- Track organic search traffic
- Monitor search engine rankings
- Analyze click-through rates
- Review social media engagement

### Tools
- Google Search Console
- Google Analytics
- Social media analytics
- SEO monitoring tools

## Future Enhancements

1. **Schema.org Implementation**
   - Organization schema
   - Product schema for services
   - FAQ schema for support pages

2. **International SEO**
   - hreflang tags for multiple languages
   - Localized meta descriptions

3. **Dynamic SEO**
   - Blog post SEO automation
   - User-generated content optimization

4. **Advanced Features**
   - AMP pages for mobile
   - Rich snippets
   - Knowledge graph optimization