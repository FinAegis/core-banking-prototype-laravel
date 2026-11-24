# Backend Production Handover — Mobile App Launch

**Date**: March 20, 2026
**Mobile Version**: 1.2.0
**Backend Required**: core-banking-prototype-laravel v6.1.0+

> **Historical record (reviewed for v7.18.1, 2026-11-24).** This handover describes the mobile launch as it stood in March 2026. Some of it has changed since:
> - `POST /api/v1/rewards/quests/{id}/complete` was removed in v7.13.0. Quests now complete automatically when the underlying action happens.
> - The rewards data in section 1 is now seeded. `php artisan db:seed` runs `RewardsSeeder`, and `php artisan demo:populate` and `php artisan sms:setup-demo` run `MobileRewardsDemoSeeder`. `RewardsService::getProfile()` creates the `RewardProfile` with `firstOrCreate`.
> - The recovery shard backup endpoints counted under "Other" in section 4 were removed in v7.12.0, when the wallet became non-custodial.
> - Virtual cards are no longer a launch item. Since v7.18.0 they are built through Lepo Finance for a closed pilot. In production only the users on the pilot list can get a card, and adding money to a card stays off.
>
> [MOBILE_API_COMPATIBILITY.md](MOBILE_API_COMPATIBILITY.md) lists the current state of the mobile endpoints.

---

## TL;DR

The mobile app is production-ready. All 21 API services have real implementations wired to backend endpoints. The only items requiring backend action are **data seeding** and **env configuration**.

---

## 1. REQUIRED: Seed Rewards Data

The mobile rewards screen is now live (Profile → Rewards). It calls these endpoints:

| Endpoint | Purpose |
|----------|---------|
| `GET /api/v1/rewards/profile` | User XP, level, streak, points |
| `GET /api/v1/rewards/quests` | Active quests with completion status |
| `GET /api/v1/rewards/shop` | Redeemable shop items |
| `POST /api/v1/rewards/quests/{id}/complete` | Complete a quest (removed in v7.13.0) |
| `POST /api/v1/rewards/shop/{id}/redeem` | Redeem a shop item |

### Action needed:

**a) Seed quests** — The `reward_quests` table needs initial quest data. Recommended for launch:

```
| slug              | title                      | description                           | xp_reward | points_reward | category   | icon     | is_repeatable |
|-------------------|----------------------------|---------------------------------------|-----------|---------------|------------|----------|---------------|
| first-payment     | Make Your First Payment    | Send tokens to any address            | 50        | 100           | onboarding | flash    | false         |
| first-shield      | Shield a Transaction       | Shield tokens for privacy             | 75        | 150           | onboarding | shield   | false         |
| complete-profile  | Complete Your Profile      | Fill in all profile fields             | 100       | 200           | onboarding | person   | false         |
| first-card        | Create a Virtual Card      | Issue your first virtual card          | 50        | 100           | onboarding | card     | false         |
| daily-login       | Daily Login                | Log in to Zelta                        | 5         | 10            | daily      | calendar | true          |
| daily-transaction | Daily Transaction          | Make any transaction today             | 10        | 20            | daily      | flash    | true          |
```

**b) Seed shop items** — The `reward_shop_items` table needs items:

```
| slug                 | title               | description                  | points_cost | category | icon   | stock |
|----------------------|---------------------|------------------------------|-------------|----------|--------|-------|
| fee-waiver           | Fee-Free Transfer   | Waive one transaction fee    | 500         | perks    | flash  | null  |
| priority-processing  | Priority Processing | Faster confirmation times    | 750         | perks    | rocket | null  |
| custom-badge         | Custom Badge        | Exclusive profile badge      | 1000        | badges   | star   | 50    |
```

**c) Auto-create RewardProfile** — Verify that `RewardsService::getProfile()` auto-creates a `RewardProfile` record when a user first accesses the rewards page. If not, add a `firstOrCreate` call.

---

## 2. REQUIRED: Production Environment Config

The mobile `.env` for production must have:

```env
EXPO_PUBLIC_API_URL=https://zelta.app
EXPO_PUBLIC_USE_MOCK=false
EXPO_PUBLIC_WS_URL=wss://ws.zelta.app
EXPO_PUBLIC_PUSHER_APP_KEY=<production-key>
EXPO_PUBLIC_PUSHER_CLUSTER=eu
EXPO_PUBLIC_ANALYTICS_ENABLED=true
EXPO_PUBLIC_DEBUG=false
```

Backend must have:
- HTTPS with valid SSL certificate (mobile has SSL pinning via `GET /api/v1/mobile/ssl-pins`)
- CORS configured for the mobile app origin
- Pusher/Soketi WebSocket server running
- Firebase credentials configured for push notifications

---

## 3. OPTIONAL: Items That Show "Coming Soon" (OK for launch)

These are cosmetic — they don't block the demo or production launch:

| Feature | Location | Why | Fix |
|---------|----------|-----|-----|
| **Base & Arbitrum chains** | Settings → Network | Networks disabled until multi-chain relayer is ready | Enable when relayer supports Base/Arbitrum |
| **POI History** | Settings → Privacy | Backend privacy tx history endpoint exists but no historical POI viewer | Low priority — POI generation works |
| **Native Passkey** | Onboarding (native only) | Requires `react-native-passkey` package installation | Users skip past it; web passkey works |
| **Apple/Google Wallet** | Card → Add to Wallet | Requires native SDK packages (`react-native-passkit-wallet`, TapAndPay) | Card creation/freeze/use works; provisioning is cosmetic |

---

## 4. Endpoint Verification Checklist

All 100+ endpoints verified implemented in backend. Key groups:

| Group | Endpoints | Status |
|-------|-----------|--------|
| Auth + Passkey | 7 | ✅ Verified |
| Wallet + Transactions | 14 | ✅ Verified |
| Privacy (ZK) | 20 | ✅ Verified |
| Relayer (ERC-4337) | 12 | ✅ Verified |
| Commerce | 9 | ✅ Verified |
| TrustCert + KYC | 12 | ✅ Verified |
| Cards | 8 | ✅ Verified |
| Rewards | 5 | ✅ Verified (needs seed data) |
| Notifications | 4 | ✅ Verified |
| x402 Micropayments | 11 | ✅ Verified |
| Referrals | 4 | ✅ Verified |
| Banners | 2 | ✅ Verified |
| Ramp (On/Off) | 5 | ✅ Verified |
| Other (SSL, prefs, devices, compliance, recovery, sponsorship) | 12 | ✅ Verified |

---

## 5. Pre-Demo Checklist

- [ ] Backend deployed with production DB
- [ ] Rewards quests and shop items seeded
- [ ] SSL certificate valid and pins updated
- [ ] WebSocket server (Pusher/Soketi) running
- [ ] At least one test user registered with:
  - Passkey configured (web)
  - TrustCert verified (level 2+)
  - Some USDC balance on Polygon
  - At least one virtual card created
- [ ] Mobile app built with `EXPO_PUBLIC_USE_MOCK=false`
- [ ] Verify: login → home → send → cards → rewards → profile flow works end-to-end
