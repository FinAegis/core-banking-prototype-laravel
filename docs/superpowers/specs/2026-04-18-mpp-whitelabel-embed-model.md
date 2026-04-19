# MPP White-Label Embed Model — Design Spec

**Status:** Future / Unscheduled
**Date:** 2026-04-18
**Related:** `docs/superpowers/specs/2026-04-18-mpp-wholesale-aggregator-model.md` (Option 2)
**Context:** VertexSMS partnership discussion — captured while context was fresh.

---

## Problem

The account-less MPP flow (Option 1, shipped) works great for machine-to-machine calls. The wholesale model (Option 2, designed) works for pure B2B resale. Neither serves a partner who wants their **end users** to get the benefit of multi-rail payments — USDC, Stripe, Lightning — but without sending them to a Zelta-branded checkout.

Example: VertexSMS wants to offer "pay per SMS with USDC" as a feature to their dashboard users. The end user would see VertexSMS's dashboard, click "send SMS", see a payment modal styled like VertexSMS, pay via their wallet, and receive delivery confirmation in VertexSMS's UI. Zelta does the payment gating and the SMS delivery, but the user never sees the word "Zelta".

## Goal

Let partners embed Zelta's 402 payment flow as a white-label widget in their own frontend, with partner-controlled branding, webhook callbacks, and no Zelta redirects.

## Design

### Embeddable checkout widget

Ship a JS widget hosted at a stable CDN URL:

```html
<script src="https://cdn.finaegis.org/mpp-checkout.js"></script>
<script>
  const checkout = new ZeltaCheckout({
    apiKey: 'pk_partner_...',           // publishable partner key
    resource: '/api/v1/sms/send',
    payload: { to: '+37069912345', message: 'Hello' },
    rails: ['x402', 'stripe', 'lightning'],
    theme: { brand: '#ff6600', logo: 'https://vertexsms.com/logo.png' },
    onSuccess: (result) => { /* partner handles */ },
    onError: (err) => { /* partner handles */ }
  });
  checkout.open();
</script>
```

The widget:
- Loads in an iframe or modal
- Handles the 402 handshake, payment rail selection, payment submission, and retry with proof
- Returns the successful response to the partner's JS via postMessage
- Shows partner branding, not Zelta's

### Publishable keys

Introduce a two-key model like Stripe:
- **Secret key** (`sk_partner_...`) — server-side only, full API access
- **Publishable key** (`pk_partner_...`) — browser-safe, scoped to specific resources, rate-limited by origin

Publishable keys are restricted to:
- Allowed origins (CORS enforcement)
- Allowed resources (only endpoints the partner explicitly whitelisted)
- Read-only on resource metadata; write only via the 402 payment flow

### Theme configuration

Partners configure branding via `PUT /partner/v1/branding` (endpoint exists today, extend scope):

```json
{
  "brand_color": "#ff6600",
  "logo_url": "https://vertexsms.com/logo.png",
  "support_email": "support@vertexsms.com",
  "terms_url": "https://vertexsms.com/terms",
  "privacy_url": "https://vertexsms.com/privacy",
  "font": "Inter"
}
```

The widget reads this config via the publishable key and renders accordingly.

### Webhook passthrough

Partners register a webhook URL. On payment success/failure/delivery events, Zelta POSTs to the partner's endpoint with an HMAC signature. Partner's backend updates their own records.

This overlaps with existing webhook infrastructure; reuse `AlchemyWebhookManager`-style per-partner webhook registration.

### Custom domain (optional, Enterprise tier)

For partners who want the widget served from their own domain (`checkout.vertexsms.com`), support CNAME + ACM certificate provisioning. Existing `FinancialInstitutionPartner` has `custom_domain` field already.

## Work Required

| Area | Effort |
|------|--------|
| Publishable key type + CORS/origin enforcement | M |
| Checkout widget (JS) — build, style, test across frameworks | L |
| CDN hosting + versioning for widget | M |
| Theme config storage + serving | S |
| Widget renders theme dynamically | M |
| Webhook passthrough (reuse existing infra) | S |
| Custom domain provisioning | M |
| SDK update: `@finaegis/checkout-js` npm package | M |
| Documentation: integration guide, code samples | M |
| Partner onboarding: publishable key generation UI | S |

**Total estimate:** 4-6 weeks of engineering. Most of the cost is in the widget (UX, cross-browser testing, framework-agnostic packaging) and in the operational work around CDN + custom domains.

## Tradeoffs

**Pros:**
- Partner keeps their brand front and center; no "powered by Zelta" dilution
- End users get multi-rail payments without leaving the partner's UI
- Higher partnership stickiness — partners won't easily rip-and-replace a deeply embedded widget
- Creates a defensible B2B product (competitors would need to replicate the widget + CDN + theming infra)

**Cons:**
- Widget is real product engineering, not a weekend project
- CDN + version management adds operational overhead
- Each widget update must maintain backward compatibility for embedded instances
- Cross-browser / cross-framework bugs will be a long tail
- Custom domain provisioning needs ops runbooks
- Fraud risk: publishable keys are browser-exposed, so rate limits + origin enforcement must be tight

## Open Questions

1. Widget packaging: React component, vanilla JS, or both? (Recommendation: vanilla JS core + thin React wrapper.)
2. iframe vs inline? Iframes are safer (CSP isolation) but harder to theme. (Recommendation: iframe with a tight theming API.)
3. Do we build our own widget or adopt an existing payment widget framework (Stripe Elements-style)? (Probably build — none exist for MPP multi-rail.)
4. Support fallback for non-JS environments (noscript)? (Probably defer.)
5. How do we version the widget? (Recommendation: `/v1/`, `/v2/` URL paths, keep v1 indefinitely.)

## When to Build

**Trigger:** Either
- Multiple partners ask for branded end-user checkouts (not just wholesale), OR
- A strategic partnership (e.g. a platform with 10M+ users) requires white-label as table stakes.

VertexSMS alone isn't a strong enough signal. Consider bundling with a platform partnership negotiation.

## References

- Current MPP implementation: `app/Domain/MachinePay/`
- Partner branding config: `app/Domain/FinancialInstitution/Models/FinancialInstitutionPartner.php` (`custom_domain`, `branding` fields)
- Widget patterns to study: Stripe Elements, Plaid Link, Coinbase Commerce
