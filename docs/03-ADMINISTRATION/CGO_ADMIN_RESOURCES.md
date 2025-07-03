# CGO Admin Resources

This document describes the Filament admin resources for managing CGO (Continuous Growth Offering) investments and pricing rounds.

## Overview

The CGO Admin Resources provide a comprehensive interface for managing:
- **CGO Investments**: Track and manage all investor contributions
- **Pricing Rounds**: Control share pricing and availability across different funding rounds

## CGO Investment Management

### Features

1. **Investment Listing**
   - View all investments with filtering and sorting capabilities
   - Real-time status indicators (pending, confirmed, cancelled, refunded)
   - Quick stats widget showing total raised, active investors, and pending investments
   - Export functionality for reporting

2. **Investment Details**
   - Investor information and contact details
   - Investment amount and tier (Bronze/Silver/Gold)
   - Share allocation and ownership percentage
   - Payment method and transaction details
   - KYC/AML verification status
   - Agreement and certificate management

3. **Actions**
   - **Verify Payment**: Manually trigger payment verification for pending transactions
   - **Download Agreement**: Access investment agreement PDFs
   - **Download Certificate**: Generate and download investment certificates
   - **Edit Status**: Update investment and payment status

### Filters

- **Status**: Filter by investment status (pending, confirmed, cancelled, refunded)
- **Payment Status**: Filter by payment processing status
- **Tier**: Filter by investment tier (Bronze, Silver, Gold)
- **Payment Method**: Filter by payment type (Card, Bank, Crypto)
- **KYC Verified**: Filter by KYC verification status
- **Date Range**: Filter by creation date

### Navigation Badge

The CGO Investments menu item displays a badge showing the count of pending investments that require attention.

## Pricing Round Management

### Features

1. **Round Configuration**
   - Set round number and share price
   - Define maximum shares available
   - Control round activation status
   - Set start and end dates

2. **Progress Tracking**
   - Monitor shares sold vs. available
   - Track total funds raised per round
   - View investment count per round
   - Progress percentage indicator

3. **Round Actions**
   - **Activate**: Make a round active for new investments
   - **Close Round**: End an active round and prevent new investments
   - **Edit**: Modify round parameters (with restrictions for active rounds)

### Business Rules

- Only one pricing round can be active at a time
- Activating a new round automatically deactivates the current active round
- Round numbers must be unique
- Share price and availability cannot be modified for rounds with existing investments

## Stats Widget

The investment listing page includes a stats overview widget displaying:
- **Total Raised**: All-time CGO investment total
- **Active Investors**: Count of unique confirmed investors
- **Pending Investments**: Investments awaiting payment confirmation
- **Current Round**: Active round information and progress

## Security Considerations

### Access Control
- Admin resources are only accessible to authenticated admin users
- All actions are logged for audit purposes
- Sensitive financial data is encrypted in the database

### Payment Verification
- Automated verification for Stripe and Coinbase Commerce payments
- Manual verification option for bank transfers
- Double-confirmation required for status changes

## Best Practices

1. **Daily Operations**
   - Review pending investments daily
   - Process payment verifications promptly
   - Monitor failed payments and contact investors if needed

2. **Round Management**
   - Plan round transitions in advance
   - Ensure adequate share allocation for expected demand
   - Close rounds promptly when targets are met

3. **Data Management**
   - Export investment data regularly for backup
   - Maintain accurate KYC/AML records
   - Archive completed round data

## Integration Points

The admin resources integrate with:
- **Payment Processors**: Stripe and Coinbase Commerce for payment verification
- **KYC Service**: Automated KYC/AML verification system
- **Document Generation**: PDF generation for agreements and certificates
- **Email Service**: Automated notifications to investors
- **Queue System**: Asynchronous processing of verifications

## Troubleshooting

### Common Issues

1. **Payment Verification Failures**
   - Check payment processor API keys
   - Verify webhook endpoints are accessible
   - Review job queue for processing errors

2. **Missing Investments**
   - Ensure pricing round is active
   - Check user KYC verification status
   - Verify payment method configuration

3. **Export Issues**
   - Check storage permissions
   - Verify adequate disk space
   - Review export job logs

## Future Enhancements

Planned improvements include:
- Bulk investment processing
- Advanced analytics dashboard
- Automated round transitions
- Integration with accounting systems
- Mobile app for investor management