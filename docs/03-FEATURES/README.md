# Features Documentation

This directory contains documentation about the platform's features and capabilities.

## Contents

- **[FEATURES.md](FEATURES.md)** - Comprehensive list of current features and capabilities
- **[RELEASE_NOTES.md](RELEASE_NOTES.md)** - Version history and release notes
- **[BUSINESS_TEAM_MANAGEMENT.md](BUSINESS_TEAM_MANAGEMENT.md)** - Business team management features
- **[GCU_TRADING.md](GCU_TRADING.md)** - GCU demo trading module (simulated; GCU is not issued or offered)
- **[OPENBANKING_WITHDRAWAL.md](OPENBANKING_WITHDRAWAL.md)** - Open banking withdrawal implementation
- **[TRANSACTION_STATUS_TRACKING.md](TRANSACTION_STATUS_TRACKING.md)** - Transaction status tracking features

## Purpose

These documents help users and developers understand:
- Available features and their usage
- System capabilities and limitations
- Version history and changes
- Feature-specific implementation details
- Business value of each feature

## Current Feature Status (February 2026)

*Named third-party providers: Integration adapters for third-party APIs; no partnership or endorsement implied.*

### v2.4.0 Features (February 1, 2026) - In Progress
- 🚧 **Key Management**: Shamir's Secret Sharing (2-of-3 threshold)
- 🚧 **Privacy Layer**: Zero-Knowledge KYC, Proof of Innocence
- 🚧 **Commerce Domain**: Soulbound Tokens, Merchant Onboarding
- 🚧 **TrustCert**: W3C Verifiable Credentials, Certificate Authority

### v2.3.0 Features (February 1, 2026)
- ✅ **AI-Powered Banking**: Natural language queries, ML anomaly detection
- ✅ **RegTech modules**: reporting and data-exchange modules modelled on MiFID II and MiCA requirements
- ✅ **Embedded Finance (BaaS)**: White-label APIs, partner marketplace

### v2.2.0 Features (January 31, 2026)
- ✅ **Mobile Backend**: Device management, biometric auth
- ✅ **Push Notifications**: FCM/APNs integration
- ✅ **WebSocket Broadcasting**: Real-time mobile updates

### v2.1.0 Features (January 30, 2026)
- ✅ **Hardware Wallet Integration**: Ledger Nano S/X, Trezor One/Model T
- ✅ **Multi-Signature Wallets**: M-of-N threshold signatures
- ✅ **Real-time WebSocket Streaming**: Order book, NAV, notifications
- ✅ **Kubernetes Native Deployment**: Helm charts, HPA, Istio
- ✅ **Security Hardening**: ECDSA, PBKDF2, EIP-2 compliance

### v2.0.0 Features (January 28, 2026)
- ✅ **Multi-Tenancy**: Team-based tenant isolation
- ✅ **Tenant-scoped Event Sourcing**: Isolated event streams
- ✅ **WebSocket Channel Authorization**: Tenant-aware broadcasting

### Core Platform Features
- ✅ **GCU demo voting**: simulated basket votes
- ✅ **GCU demo conversions**: simulated GCU conversions (software demonstration; GCU is not issued or offered)
- ✅ **Two-Factor Authentication**: Enhanced security for user accounts
- ✅ **OAuth2 Integration**: Social login capabilities
- ✅ **KYC/AML Verification**: Tiered verification tooling
- ✅ **Payment Integration**: Stripe and Coinbase Commerce
- ✅ **Refund Processing**: Event-sourced refund workflows
- ✅ **Browser Testing**: Critical path test coverage
- ✅ **Subscriber Management**: Newsletter and marketing system

### Core Platform Features
- ✅ **Multi-Asset Support**: Fiat and cryptocurrency management
- ✅ **Event Sourcing**: Complete audit trail
- ✅ **Bank Integration**: Bank connector adapters (multi-custodian framework)
- ✅ **Governance System**: Polls and voting mechanisms
- ✅ **Admin Dashboard**: Comprehensive Filament interface
- ✅ **API Platform**: RESTful APIs for all operations
- ✅ **Webhook System**: Real-time event notifications