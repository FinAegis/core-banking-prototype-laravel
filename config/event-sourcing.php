<?php

return [

    /*
     * These directories will be scanned for projectors and reactors. They
     * will be registered to Projectionist automatically.
     */
    'auto_discover_projectors_and_reactors' => [
        app()->path(),
    ],

    /*
     * This directory will be used as the base path when scanning
     * for projectors and reactors.
     */
    'auto_discover_base_path' => base_path(),

    /*
     * Projectors are classes that build up projections. You can create them by performing
     * `php artisan event-sourcing:create-projector`. When not using auto-discovery,
     * Projectors can be registered in this array or a service provider.
     */
    'projectors' => [
        // App\Projectors\YourProjector::class
    ],

    /*
     * Reactors are classes that handle side-effects. You can create them by performing
     * `php artisan event-sourcing:create-reactor`. When not using auto-discovery
     * Reactors can be registered in this array or a service provider.
     */
    'reactors' => [
        // App\Reactors\YourReactor::class
    ],

    /*
     * A queue is used to guarantee that all events get passed to the projectors in
     * the right order. Here you can set of the name of the queue.
     */
    'queue' => env('EVENT_PROJECTOR_QUEUE_NAME', App\Values\EventQueues::default()->value),

    /*
     * When a Projector or Reactor throws an exception the event Projectionist can catch it
     * so all other projectors and reactors can still do their work. The exception will
     * be passed to the `handleException` method on that Projector or Reactor.
     */
    'catch_exceptions' => env('EVENT_PROJECTOR_CATCH_EXCEPTIONS', false),

    /*
     * This class is responsible for storing events in the EloquentStoredEventRepository.
     * To add extra behaviour you can change this to a class of your own. It should
     * extend the \Spatie\EventSourcing\StoredEvents\Models\EloquentStoredEvent model.
     */
    'stored_event_model' => Spatie\EventSourcing\StoredEvents\Models\EloquentStoredEvent::class,

    /*
     * This class is responsible for storing events. To add extra behaviour you
     * can change this to a class of your own. The only restriction is that
     * it should implement \Spatie\EventSourcing\StoredEvents\Repositories\EloquentStoredEventRepository.
     */
    'stored_event_repository' => Spatie\EventSourcing\StoredEvents\Repositories\EloquentStoredEventRepository::class,

    /*
     * This class is responsible for storing snapshots. To add extra behaviour you
     * can change this to a class of your own. The only restriction is that
     * it should implement \Spatie\EventSourcing\Snapshots\EloquentSnapshotRepository.
     */
    'snapshot_repository' => Spatie\EventSourcing\Snapshots\EloquentSnapshotRepository::class,

    /*
     * This class is responsible for storing events in the EloquentSnapshotRepository.
     * To add extra behaviour you can change this to a class of your own. It should
     * extend the \Spatie\EventSourcing\Snapshots\EloquentSnapshot model.
     */
    'snapshot_model' => Spatie\EventSourcing\Snapshots\EloquentSnapshot::class,

    /*
     * This class is responsible for handling stored events. To add extra behaviour you
     * can change this to a class of your own. The only restriction is that
     * it should implement \Spatie\EventSourcing\StoredEvents\HandleDomainEventJob.
     */
    'stored_event_job' => Spatie\EventSourcing\StoredEvents\HandleStoredEventJob::class,

    /*
     * Similar to Relation::enforceMorphMap() this option will make sure that every event has a
     * corresponding alias defined. Otherwise, an exception is thrown
     * if you try to persist an event without alias.
     */
    'enforce_event_class_map' => true,

    /*
     * Similar to Relation::morphMap() you can define which alias responds to which
     * event class. This allows you to change the namespace or class names
     * of your events but still handle older events correctly.
     */
    'event_class_map' => [
        'account_created'               => App\Domain\Account\Events\AccountCreated::class,
        'account_deleted'               => App\Domain\Account\Events\AccountDeleted::class,
        'account_frozen'                => App\Domain\Account\Events\AccountFrozen::class,
        'account_unfrozen'              => App\Domain\Account\Events\AccountUnfrozen::class,
        'account_limit_hit'             => App\Domain\Account\Events\AccountLimitHit::class,
        'money_added'                   => App\Domain\Account\Events\MoneyAdded::class,
        'money_subtracted'              => App\Domain\Account\Events\MoneySubtracted::class,
        'money_transferred'             => App\Domain\Account\Events\MoneyTransferred::class,
        'transaction_threshold_reached' => App\Domain\Account\Events\TransactionThresholdReached::class,
        'transfer_threshold_reached'    => App\Domain\Account\Events\TransferThresholdReached::class,
        'asset_balance_added'           => App\Domain\Account\Events\AssetBalanceAdded::class,
        'asset_balance_subtracted'      => App\Domain\Account\Events\AssetBalanceSubtracted::class,
        'asset_transferred'             => App\Domain\Account\Events\AssetTransferred::class,
        'asset_transaction_created'     => App\Domain\Asset\Events\AssetTransactionCreated::class,
        'asset_transfer_initiated'      => App\Domain\Asset\Events\AssetTransferInitiated::class,
        'asset_transfer_completed'      => App\Domain\Asset\Events\AssetTransferCompleted::class,
        'asset_transfer_failed'         => App\Domain\Asset\Events\AssetTransferFailed::class,
        // Treasury events
        'treasury_account_created'     => App\Domain\Treasury\Events\TreasuryAccountCreated::class,
        'cash_allocated'               => App\Domain\Treasury\Events\CashAllocated::class,
        'liquidity_forecast_generated' => App\Domain\Treasury\Events\LiquidityForecastGenerated::class,
        'regulatory_report_generated'  => App\Domain\Treasury\Events\RegulatoryReportGenerated::class,
        'risk_assessment_completed'    => App\Domain\Treasury\Events\RiskAssessmentCompleted::class,
        'yield_optimization_started'   => App\Domain\Treasury\Events\YieldOptimizationStarted::class,
        'exchange_rate_updated'        => App\Domain\Asset\Events\ExchangeRateUpdated::class,
        'basket_created'               => App\Domain\Basket\Events\BasketCreated::class,
        'basket_decomposed'            => App\Domain\Basket\Events\BasketDecomposed::class,
        // 'basket_composed'                      => App\Domain\Basket\Events\BasketComposed::class,
        'basket_rebalanced'                    => App\Domain\Basket\Events\BasketRebalanced::class,
        'stablecoin_minted'                    => App\Domain\Stablecoin\Events\StablecoinMinted::class,
        'stablecoin_burned'                    => App\Domain\Stablecoin\Events\StablecoinBurned::class,
        'collateral_locked'                    => App\Domain\Stablecoin\Events\CollateralLocked::class,
        'collateral_released'                  => App\Domain\Stablecoin\Events\CollateralReleased::class,
        'collateral_position_created'          => App\Domain\Stablecoin\Events\CollateralPositionCreated::class,
        'collateral_position_updated'          => App\Domain\Stablecoin\Events\CollateralPositionUpdated::class,
        'collateral_position_closed'           => App\Domain\Stablecoin\Events\CollateralPositionClosed::class,
        'collateral_position_liquidated'       => App\Domain\Stablecoin\Events\CollateralPositionLiquidated::class,
        'collateral_added'                     => App\Domain\Stablecoin\Events\CollateralAdded::class,
        'collateral_withdrawn'                 => App\Domain\Stablecoin\Events\CollateralWithdrawn::class,
        'collateral_price_updated'             => App\Domain\Stablecoin\Events\CollateralPriceUpdated::class,
        'collateral_health_checked'            => App\Domain\Stablecoin\Events\CollateralHealthChecked::class,
        'margin_call_issued'                   => App\Domain\Stablecoin\Events\MarginCallIssued::class,
        'collateral_liquidation_started'       => App\Domain\Stablecoin\Events\CollateralLiquidationStarted::class,
        'collateral_liquidation_completed'     => App\Domain\Stablecoin\Events\CollateralLiquidationCompleted::class,
        'collateral_rebalanced'                => App\Domain\Stablecoin\Events\CollateralRebalanced::class,
        'enhanced_collateral_position_created' => App\Domain\Stablecoin\Events\EnhancedCollateralPositionCreated::class,
        'enhanced_collateral_position_closed'  => App\Domain\Stablecoin\Events\EnhancedCollateralPositionClosed::class,
        'deposit_initiated'                    => App\Domain\Payment\Events\DepositInitiated::class,
        'deposit_completed'                    => App\Domain\Payment\Events\DepositCompleted::class,
        'deposit_failed'                       => App\Domain\Payment\Events\DepositFailed::class,
        'withdrawal_initiated'                 => App\Domain\Payment\Events\WithdrawalInitiated::class,
        'withdrawal_completed'                 => App\Domain\Payment\Events\WithdrawalCompleted::class,
        'withdrawal_failed'                    => App\Domain\Payment\Events\WithdrawalFailed::class,
        'batch_job_created'                    => App\Domain\Batch\Events\BatchJobCreated::class,
        'batch_job_started'                    => App\Domain\Batch\Events\BatchJobStarted::class,
        'batch_job_completed'                  => App\Domain\Batch\Events\BatchJobCompleted::class,
        'batch_job_cancelled'                  => App\Domain\Batch\Events\BatchJobCancelled::class,
        'batch_item_processed'                 => App\Domain\Batch\Events\BatchItemProcessed::class,
        'cgo_refund_requested'                 => App\Domain\Cgo\Events\RefundRequested::class,
        'cgo_refund_approved'                  => App\Domain\Cgo\Events\RefundApproved::class,
        'cgo_refund_rejected'                  => App\Domain\Cgo\Events\RefundRejected::class,
        'cgo_refund_processed'                 => App\Domain\Cgo\Events\RefundProcessed::class,
        'cgo_refund_completed'                 => App\Domain\Cgo\Events\RefundCompleted::class,
        'cgo_refund_failed'                    => App\Domain\Cgo\Events\RefundFailed::class,
        'cgo_refund_cancelled'                 => App\Domain\Cgo\Events\RefundCancelled::class,

        // Exchange events
        // 'order_book_created'      => App\Domain\Exchange\Events\OrderBookCreated::class,
        'order_book_initialized'  => App\Domain\Exchange\Events\OrderBookInitialized::class,
        'order_placed'            => App\Domain\Exchange\Events\OrderPlaced::class,
        'order_cancelled'         => App\Domain\Exchange\Events\OrderCancelled::class,
        'order_filled'            => App\Domain\Exchange\Events\OrderFilled::class,
        'order_partially_filled'  => App\Domain\Exchange\Events\OrderPartiallyFilled::class,
        'order_matched'           => App\Domain\Exchange\Events\OrderMatched::class,
        'order_removed_from_book' => App\Domain\Exchange\Events\OrderRemovedFromBook::class,
        // 'market_depth_updated'    => App\Domain\Exchange\Events\MarketDepthUpdated::class,

        // Liquidity pool events
        'liquidity_pool_created' => App\Domain\Exchange\Events\LiquidityPoolCreated::class,
        'liquidity_added'        => App\Domain\Exchange\Events\LiquidityAdded::class,
        'liquidity_removed'      => App\Domain\Exchange\Events\LiquidityRemoved::class,
        // 'swap_executed'                 => App\Domain\Exchange\Events\SwapExecuted::class,
        // 'fee_collected'                 => App\Domain\Exchange\Events\FeeCollected::class,
        // 'pool_ratio_updated'            => App\Domain\Exchange\Events\PoolRatioUpdated::class,
        'pool_fee_collected'            => App\Domain\Exchange\Events\PoolFeeCollected::class,
        'liquidity_rewards_distributed' => App\Domain\Exchange\Events\LiquidityRewardsDistributed::class,
        'liquidity_rewards_claimed'     => App\Domain\Exchange\Events\LiquidityRewardsClaimed::class,
        'pool_parameters_updated'       => App\Domain\Exchange\Events\PoolParametersUpdated::class,
        'liquidity_pool_rebalanced'     => App\Domain\Exchange\Events\LiquidityPoolRebalanced::class,

        // Spread Management & Market Making events
        'spread_adjusted'              => App\Domain\Exchange\Events\SpreadAdjusted::class,
        'inventory_imbalance_detected' => App\Domain\Exchange\Events\InventoryImbalanceDetected::class,
        'market_volatility_changed'    => App\Domain\Exchange\Events\MarketVolatilityChanged::class,
        'market_maker_started'         => App\Domain\Exchange\Events\MarketMakerStarted::class,
        'market_maker_stopped'         => App\Domain\Exchange\Events\MarketMakerStopped::class,
        'quotes_updated'               => App\Domain\Exchange\Events\QuotesUpdated::class,

        // Order Routing & Fee Tier events
        'order_routed'           => App\Domain\Exchange\Events\OrderRouted::class,
        'order_split'            => App\Domain\Exchange\Events\OrderSplit::class,
        'routing_failed'         => App\Domain\Exchange\Events\RoutingFailed::class,
        'fee_tier_updated'       => App\Domain\Exchange\Events\FeeTierUpdated::class,
        'user_fee_tier_assigned' => App\Domain\Exchange\Events\UserFeeTierAssigned::class,

        // Stablecoin framework events
        'oracle_deviation_detected'       => App\Domain\Stablecoin\Events\OracleDeviationDetected::class,
        'reserve_pool_created'            => App\Domain\Stablecoin\Events\ReservePoolCreated::class,
        'reserve_deposited'               => App\Domain\Stablecoin\Events\ReserveDeposited::class,
        'reserve_withdrawn'               => App\Domain\Stablecoin\Events\ReserveWithdrawn::class,
        'reserve_rebalanced'              => App\Domain\Stablecoin\Events\ReserveRebalanced::class,
        'custodian_added'                 => App\Domain\Stablecoin\Events\CustodianAdded::class,
        'custodian_removed'               => App\Domain\Stablecoin\Events\CustodianRemoved::class,
        'collateralization_ratio_updated' => App\Domain\Stablecoin\Events\CollateralizationRatioUpdated::class,
        'proposal_created'                => App\Domain\Stablecoin\Events\ProposalCreated::class,
        'proposal_vote_cast'              => App\Domain\Stablecoin\Events\ProposalVoteCast::class,
        'proposal_executed'               => App\Domain\Stablecoin\Events\ProposalExecuted::class,
        'proposal_cancelled'              => App\Domain\Stablecoin\Events\ProposalCancelled::class,
        'proposal_finalized'              => App\Domain\Stablecoin\Events\ProposalFinalized::class,

        // Blockchain wallet events
        'blockchain_wallet_created' => App\Domain\Wallet\Events\BlockchainWalletCreated::class,
        'wallet_address_generated'  => App\Domain\Wallet\Events\WalletAddressGenerated::class,
        'wallet_settings_updated'   => App\Domain\Wallet\Events\WalletSettingsUpdated::class,
        'wallet_frozen'             => App\Domain\Wallet\Events\WalletFrozen::class,
        'wallet_unfrozen'           => App\Domain\Wallet\Events\WalletUnfrozen::class,
        'wallet_key_rotated'        => App\Domain\Wallet\Events\WalletKeyRotated::class,
        'wallet_backup_created'     => App\Domain\Wallet\Events\WalletBackupCreated::class,
        'key_stored'                => App\Domain\Wallet\Events\KeyStored::class,
        'key_accessed'              => App\Domain\Wallet\Events\KeyAccessed::class,

        // Lending events
        'loan_application_submitted'                 => App\Domain\Lending\Events\LoanApplicationSubmitted::class,
        'loan_application_credit_check_completed'    => App\Domain\Lending\Events\LoanApplicationCreditCheckCompleted::class,
        'loan_application_risk_assessment_completed' => App\Domain\Lending\Events\LoanApplicationRiskAssessmentCompleted::class,
        'loan_application_approved'                  => App\Domain\Lending\Events\LoanApplicationApproved::class,
        'loan_application_rejected'                  => App\Domain\Lending\Events\LoanApplicationRejected::class,
        'loan_application_withdrawn'                 => App\Domain\Lending\Events\LoanApplicationWithdrawn::class,
        'loan_created'                               => App\Domain\Lending\Events\LoanCreated::class,
        'loan_funded'                                => App\Domain\Lending\Events\LoanFunded::class,
        'loan_disbursed'                             => App\Domain\Lending\Events\LoanDisbursed::class,
        'loan_repayment_made'                        => App\Domain\Lending\Events\LoanRepaymentMade::class,
        'loan_payment_missed'                        => App\Domain\Lending\Events\LoanPaymentMissed::class,
        'loan_defaulted'                             => App\Domain\Lending\Events\LoanDefaulted::class,
        'loan_completed'                             => App\Domain\Lending\Events\LoanCompleted::class,
        'loan_settled_early'                         => App\Domain\Lending\Events\LoanSettledEarly::class,

        // AML Screening events
        'aml_screening_started'              => App\Domain\Compliance\Events\AmlScreeningStarted::class,
        'aml_screening_results_recorded'     => App\Domain\Compliance\Events\AmlScreeningResultsRecorded::class,
        'aml_screening_match_status_updated' => App\Domain\Compliance\Events\AmlScreeningMatchStatusUpdated::class,
        'aml_screening_completed'            => App\Domain\Compliance\Events\AmlScreeningCompleted::class,
        'aml_screening_reviewed'             => App\Domain\Compliance\Events\AmlScreeningReviewed::class,

        // Compliance Alert events
        'alert_created'           => App\Domain\Compliance\Events\AlertCreated::class,
        'alert_assigned'          => App\Domain\Compliance\Events\AlertAssigned::class,
        'alert_status_changed'    => App\Domain\Compliance\Events\AlertStatusChanged::class,
        'alert_note_added'        => App\Domain\Compliance\Events\AlertNoteAdded::class,
        'alert_resolved'          => App\Domain\Compliance\Events\AlertResolved::class,
        'alert_escalated'         => App\Domain\Compliance\Events\AlertEscalated::class,
        'alert_escalated_to_case' => App\Domain\Compliance\Events\AlertEscalatedToCase::class,
        'alert_linked'            => App\Domain\Compliance\Events\AlertLinked::class,

        // Compliance Transaction Monitoring events
        'risk_score_calculated'         => App\Domain\Compliance\Events\RiskScoreCalculated::class,
        'risk_level_changed'            => App\Domain\Compliance\Events\RiskLevelChanged::class,
        'pattern_detected'              => App\Domain\Compliance\Events\PatternDetected::class,
        'compliance_threshold_exceeded' => App\Domain\Compliance\Events\ThresholdExceeded::class,
        'transaction_flagged'           => App\Domain\Compliance\Events\TransactionFlagged::class,
        'transaction_cleared'           => App\Domain\Compliance\Events\TransactionCleared::class,
        'transaction_analyzed'          => App\Domain\Compliance\Events\TransactionAnalyzed::class,
        'transaction_blocked'           => App\Domain\Compliance\Events\TransactionBlocked::class,
        'transaction_pattern_detected'  => App\Domain\Compliance\Events\TransactionPatternDetected::class,
        'monitoring_rule_triggered'     => App\Domain\Compliance\Events\MonitoringRuleTriggered::class,
        'suspicious_activity_detected'  => App\Domain\Compliance\Events\SuspiciousActivityDetected::class,
        'real_time_alert_generated'     => App\Domain\Compliance\Events\RealTimeAlertGenerated::class,

        // Compliance KYC events
        'kyc_verification_started'        => App\Domain\Compliance\Events\KycVerificationStarted::class,
        'kyc_document_uploaded'           => App\Domain\Compliance\Events\KycDocumentUploaded::class,
        'kyc_submission_received'         => App\Domain\Compliance\Events\KycSubmissionReceived::class,
        'kyc_verification_completed'      => App\Domain\Compliance\Events\KycVerificationCompleted::class,
        'kyc_verification_failed'         => App\Domain\Compliance\Events\KycVerificationFailed::class,
        'kyc_verification_rejected'       => App\Domain\Compliance\Events\KycVerificationRejected::class,
        'enhanced_due_diligence_required' => App\Domain\Compliance\Events\EnhancedDueDiligenceRequired::class,

        // Compliance SAR events
        'sar_created'   => App\Domain\Compliance\Events\SARCreated::class,
        'sar_submitted' => App\Domain\Compliance\Events\SARSubmitted::class,

        // Compliance Screening events
        'screening_completed'   => App\Domain\Compliance\Events\ScreeningCompleted::class,
        'screening_match_found' => App\Domain\Compliance\Events\ScreeningMatchFound::class,

        // Compliance GDPR events
        'gdpr_request_received' => App\Domain\Compliance\Events\GdprRequestReceived::class,
        'gdpr_data_exported'    => App\Domain\Compliance\Events\GdprDataExported::class,
        'gdpr_data_deleted'     => App\Domain\Compliance\Events\GdprDataDeleted::class,

        // AI Agent Framework events
        'ai_conversation_started'         => App\Domain\AI\Events\ConversationStartedEvent::class,
        'ai_conversation_ended'           => App\Domain\AI\Events\ConversationEndedEvent::class,
        'ai_agent_created'                => App\Domain\AI\Events\AgentCreatedEvent::class,
        'ai_decision_made'                => App\Domain\AI\Events\AIDecisionMadeEvent::class,
        'ai_intent_classified'            => App\Domain\AI\Events\IntentClassifiedEvent::class,
        'ai_tool_executed'                => App\Domain\AI\Events\ToolExecutedEvent::class,
        'ai_human_intervention_requested' => App\Domain\AI\Events\HumanInterventionRequestedEvent::class,

        // Monitoring & Observability events
        'metric_recorded'               => App\Domain\Monitoring\Events\MetricRecorded::class,
        'health_check_failed'           => App\Domain\Monitoring\Events\HealthCheckFailed::class,
        'health_check_passed'           => App\Domain\Monitoring\Events\HealthCheckPassed::class,
        'monitoring_threshold_exceeded' => App\Domain\Monitoring\Events\ThresholdExceeded::class,
        'monitoring_alert_triggered'    => App\Domain\Monitoring\Events\AlertTriggered::class,
        'monitoring_alert_resolved'     => App\Domain\Monitoring\Events\AlertResolved::class,

        // Portfolio Management events
        'portfolio_created'         => App\Domain\Treasury\Events\Portfolio\PortfolioCreated::class,
        'assets_allocated'          => App\Domain\Treasury\Events\Portfolio\AssetsAllocated::class,
        'portfolio_rebalanced'      => App\Domain\Treasury\Events\Portfolio\PortfolioRebalanced::class,
        'strategy_updated'          => App\Domain\Treasury\Events\Portfolio\StrategyUpdated::class,
        'performance_recorded'      => App\Domain\Treasury\Events\Portfolio\PerformanceRecorded::class,
        'rebalancing_triggered'     => App\Domain\Treasury\Events\Portfolio\RebalancingTriggered::class,
        'allocation_drift_detected' => App\Domain\Treasury\Events\Portfolio\AllocationDriftDetected::class,
        // Tracing events
        'span_started'           => App\Domain\Monitoring\Events\SpanStarted::class,
        'span_ended'             => App\Domain\Monitoring\Events\SpanEnded::class,
        'span_error_occurred'    => App\Domain\Monitoring\Events\SpanErrorOccurred::class,
        'span_event_recorded'    => App\Domain\Monitoring\Events\SpanEventRecorded::class,
        'span_attribute_updated' => App\Domain\Monitoring\Events\SpanAttributeUpdated::class,
        'trace_completed'        => App\Domain\Monitoring\Events\TraceCompleted::class,

        // User Domain events
        'user_profile_created'             => App\Domain\User\Events\UserProfileCreated::class,
        'user_profile_updated'             => App\Domain\User\Events\UserProfileUpdated::class,
        'user_profile_verified'            => App\Domain\User\Events\UserProfileVerified::class,
        'user_profile_suspended'           => App\Domain\User\Events\UserProfileSuspended::class,
        'user_profile_deleted'             => App\Domain\User\Events\UserProfileDeleted::class,
        'user_preferences_updated'         => App\Domain\User\Events\UserPreferencesUpdated::class,
        'notification_preferences_updated' => App\Domain\User\Events\NotificationPreferencesUpdated::class,
        'privacy_settings_updated'         => App\Domain\User\Events\PrivacySettingsUpdated::class,
        'user_activity_tracked'            => App\Domain\User\Events\UserActivityTracked::class,

        // Performance Domain events
        'performance_metric_recorded'    => App\Domain\Performance\Events\MetricRecorded::class,
        'performance_threshold_exceeded' => App\Domain\Performance\Events\ThresholdExceeded::class,
        'performance_alert_triggered'    => App\Domain\Performance\Events\PerformanceAlertTriggered::class,
        'performance_report_generated'   => App\Domain\Performance\Events\PerformanceReportGenerated::class,

        // Product Domain events
        'product_created'         => App\Domain\Product\Events\ProductCreated::class,
        'product_updated'         => App\Domain\Product\Events\ProductUpdated::class,
        'product_activated'       => App\Domain\Product\Events\ProductActivated::class,
        'product_deactivated'     => App\Domain\Product\Events\ProductDeactivated::class,
        'product_feature_added'   => App\Domain\Product\Events\FeatureAdded::class,
        'product_feature_removed' => App\Domain\Product\Events\FeatureRemoved::class,
        'product_price_updated'   => App\Domain\Product\Events\PriceUpdated::class,

        // Agent Protocol Events - Phase 1
        'agent_registered'            => App\Domain\AgentProtocol\Events\AgentRegistered::class,
        'capability_advertised'       => App\Domain\AgentProtocol\Events\CapabilityAdvertised::class,
        'agent_wallet_created'        => App\Domain\AgentProtocol\Events\AgentWalletCreated::class,
        'agent_transaction_initiated' => App\Domain\AgentProtocol\Events\AgentTransactionInitiated::class,
        'payment_sent'                => App\Domain\AgentProtocol\Events\PaymentSent::class,
        'payment_received'            => App\Domain\AgentProtocol\Events\PaymentReceived::class,
        'wallet_balance_updated'      => App\Domain\AgentProtocol\Events\WalletBalanceUpdated::class,

        // Agent Protocol Events - Phase 2 (Transactions & Escrow)
        'transaction_initiated'   => App\Domain\AgentProtocol\Events\TransactionInitiated::class,
        'transaction_validated'   => App\Domain\AgentProtocol\Events\TransactionValidated::class,
        'transaction_completed'   => App\Domain\AgentProtocol\Events\TransactionCompleted::class,
        'transaction_failed'      => App\Domain\AgentProtocol\Events\TransactionFailed::class,
        'fee_calculated'          => App\Domain\AgentProtocol\Events\FeeCalculated::class,
        'escrow_created'          => App\Domain\AgentProtocol\Events\EscrowCreated::class,
        'escrow_funds_deposited'  => App\Domain\AgentProtocol\Events\EscrowFundsDeposited::class,
        'escrow_funds_released'   => App\Domain\AgentProtocol\Events\EscrowFundsReleased::class,
        'escrow_held'             => App\Domain\AgentProtocol\Events\EscrowHeld::class,
        'escrow_released'         => App\Domain\AgentProtocol\Events\EscrowReleased::class,
        'escrow_disputed'         => App\Domain\AgentProtocol\Events\EscrowDisputed::class,
        'escrow_dispute_resolved' => App\Domain\AgentProtocol\Events\EscrowDisputeResolved::class,
        'escrow_expired'          => App\Domain\AgentProtocol\Events\EscrowExpired::class,
        'escrow_cancelled'        => App\Domain\AgentProtocol\Events\EscrowCancelled::class,

        // Agent Protocol Events - Reputation System
        'reputation_initialized'     => App\Domain\AgentProtocol\Events\ReputationInitialized::class,
        'reputation_updated'         => App\Domain\AgentProtocol\Events\ReputationUpdated::class,
        'reputation_boosted'         => App\Domain\AgentProtocol\Events\ReputationBoosted::class,
        'reputation_penalty_applied' => App\Domain\AgentProtocol\Events\ReputationPenaltyApplied::class,
        'reputation_decayed'         => App\Domain\AgentProtocol\Events\ReputationDecayed::class,
        'trust_level_changed'        => App\Domain\AgentProtocol\Events\TrustLevelChanged::class,
    ],

    /*
     * This class is responsible for serializing events. By default an event will be serialized
     * and stored as json. You can customize the class name. A valid serializer
     * should implement Spatie\EventSourcing\EventSerializers\EventSerializer.
     */
    'event_serializer' => Spatie\EventSourcing\EventSerializers\JsonEventSerializer::class,

    /*
     * These classes normalize and restore your events when they're serialized. They allow
     * you to efficiently store PHP objects like Carbon instances, Eloquent models, and
     * Collections. If you need to store other complex data, you can add your own normalizers
     * to the chain. See https://symfony.com/doc/current/components/serializer.html#normalizers
     */
    'event_normalizers' => [
        Spatie\EventSourcing\Support\CarbonNormalizer::class,
        Spatie\EventSourcing\Support\ModelIdentifierNormalizer::class,
        Symfony\Component\Serializer\Normalizer\DateTimeNormalizer::class,
        Symfony\Component\Serializer\Normalizer\ArrayDenormalizer::class,
        Spatie\EventSourcing\Support\ObjectNormalizer::class,
    ],

    /*
     * In production, you likely don't want the package to auto-discover the event handlers
     * on every request. The package can cache all registered event handlers.
     * More info:
     * https://spatie.be/docs/laravel-event-sourcing/v7/advanced-usage/discovering-projectors-and-reactors#content-caching-discovered-projectors-and-reactors
     *
     * Here you can specify where the cache should be stored.
     */
    'cache_path' => base_path('bootstrap/cache'),

    /*
     * When storable events are fired from aggregates roots, the package can fire off these
     * events as regular events as well.
     */

    'dispatch_events_from_aggregate_roots' => true,
];
