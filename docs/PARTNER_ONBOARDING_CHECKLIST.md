# Partner Onboarding Checklist

This checklist covers the end-to-end process for onboarding a new partner to the Zelta payment platform. Target timeline: 14 days from kickoff to production traffic.

For technical details, see [Partner Integration Guide](PARTNER_INTEGRATION_GUIDE.md).

---

## Phase 1: Technical Setup (Day 1-3)

- [ ] Partner receives API credentials (test + production keys)
- [ ] Partner installs Zelta SDK v1.0.0 (`composer require finaegis/payment-sdk`)
- [ ] Partner configures `PaymentConfig` with sandbox base URL and test API key
- [ ] Partner configures webhook endpoint (HTTPS, publicly accessible)
- [ ] Partner completes first sandbox test transaction (GET request with 402 auto-pay)
- [ ] HMAC-SHA256 webhook signature verification confirmed working
- [ ] Partner can decode and log all webhook event types

## Phase 2: Integration (Day 3-7)

- [ ] Partner implements end-to-end payment flow in their product
- [ ] Partner selects payment protocol(s): x402, MPP, or AutoDetect (both)
- [ ] Partner handles all webhook event types:
  - [ ] `payment.completed`
  - [ ] `payment.failed`
  - [ ] `payment.refunded`
  - [ ] `subscription.created`
  - [ ] `subscription.cancelled`
- [ ] Partner implements idempotency keys for all payment-initiating requests
- [ ] Error handling tested for:
  - [ ] Network timeouts
  - [ ] Invalid API key (401)
  - [ ] Rate limiting (429 with `Retry-After` backoff)
  - [ ] Payment failures (`PaymentFailedException`)
  - [ ] Missing payment handler (`PaymentRequiredException`)
- [ ] Rate limit handling implemented with exponential backoff
- [ ] Retry logic tested (including webhook delivery failures)

## Phase 3: Compliance (Day 7-10)

- [ ] KYC/AML requirements reviewed and acknowledged
- [ ] Data processing agreement (DPA) signed
- [ ] Partner privacy policy updated to disclose Zelta as a payment processor
- [ ] Partner terms of service updated to cover payment protocol usage
- [ ] PCI DSS scope confirmed (MPP card rail only; x402 is out of scope)
- [ ] Geographic restrictions reviewed (sanctioned jurisdictions)

## Phase 4: Go-Live (Day 10-14)

- [ ] Production API credentials issued (`zk_live_` prefix)
- [ ] Production base URL configured (`https://api.zelta.app`)
- [ ] DNS configured for production endpoints (if using custom subdomain)
- [ ] Monitoring and alerting configured:
  - [ ] Payment failure rate alerts (threshold: > 1%)
  - [ ] Webhook delivery failure alerts
  - [ ] API response time degradation alerts
- [ ] Runbook for payment incident handling documented
- [ ] Production test transaction completed end-to-end
- [ ] Traffic ramp-up plan agreed and scheduled:
  - [ ] Day 1: 10% of traffic
  - [ ] Day 3: 50% of traffic
  - [ ] Day 5: 100% of traffic

## Phase 5: Post-Launch (Day 14+)

- [ ] First week metrics review completed:
  - [ ] Transaction volume
  - [ ] Success rate
  - [ ] Average settlement time
  - [ ] Webhook delivery rate
- [ ] Error rate confirmed below 1% threshold
- [ ] Settlement reconciliation verified (amounts match between partner and Zelta)
- [ ] Partner success contact assigned for ongoing support
- [ ] Monthly business review cadence established
- [ ] SDK update plan agreed (minor/patch auto-update, major manual review)

---

## Internal FinAegis Tasks

These are completed by the FinAegis partnerships team (not the partner):

- [ ] Partner record created in CRM
- [ ] API key pair generated and securely delivered
- [ ] Webhook secret generated and securely delivered
- [ ] Partner added to status page notification list
- [ ] Production rate limits configured per agreement
- [ ] Billing/revenue share terms finalized
- [ ] Partner logo and listing approved for integrations page
