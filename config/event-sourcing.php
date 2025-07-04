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
    'queue' => env('EVENT_PROJECTOR_QUEUE_NAME', \App\Values\EventQueues::default()),

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
        'exchange_rate_updated'         => App\Domain\Asset\Events\ExchangeRateUpdated::class,
        'basket_created'                => App\Domain\Basket\Events\BasketCreated::class,
        'basket_decomposed'             => App\Domain\Basket\Events\BasketDecomposed::class,
        'basket_composed'               => App\Domain\Basket\Events\BasketComposed::class,
        'basket_rebalanced'             => App\Domain\Basket\Events\BasketRebalanced::class,
        'stablecoin_minted'             => App\Domain\Stablecoin\Events\StablecoinMinted::class,
        'stablecoin_burned'             => App\Domain\Stablecoin\Events\StablecoinBurned::class,
        'collateral_locked'             => App\Domain\Stablecoin\Events\CollateralLocked::class,
        'collateral_released'           => App\Domain\Stablecoin\Events\CollateralReleased::class,
        'collateral_position_created'   => App\Domain\Stablecoin\Events\CollateralPositionCreated::class,
        'collateral_position_updated'   => App\Domain\Stablecoin\Events\CollateralPositionUpdated::class,
        'collateral_position_closed'    => App\Domain\Stablecoin\Events\CollateralPositionClosed::class,
        'collateral_position_liquidated'=> App\Domain\Stablecoin\Events\CollateralPositionLiquidated::class,
        'deposit_initiated'             => App\Domain\Payment\Events\DepositInitiated::class,
        'deposit_completed'             => App\Domain\Payment\Events\DepositCompleted::class,
        'deposit_failed'                => App\Domain\Payment\Events\DepositFailed::class,
        'withdrawal_initiated'          => App\Domain\Payment\Events\WithdrawalInitiated::class,
        'withdrawal_completed'          => App\Domain\Payment\Events\WithdrawalCompleted::class,
        'withdrawal_failed'             => App\Domain\Payment\Events\WithdrawalFailed::class,
        'batch_job_created'             => App\Domain\Batch\Events\BatchJobCreated::class,
        'batch_job_started'             => App\Domain\Batch\Events\BatchJobStarted::class,
        'batch_job_completed'           => App\Domain\Batch\Events\BatchJobCompleted::class,
        'batch_job_cancelled'           => App\Domain\Batch\Events\BatchJobCancelled::class,
        'batch_item_processed'          => App\Domain\Batch\Events\BatchItemProcessed::class,
        'cgo_refund_requested'          => App\Domain\Cgo\Events\RefundRequested::class,
        'cgo_refund_approved'           => App\Domain\Cgo\Events\RefundApproved::class,
        'cgo_refund_rejected'           => App\Domain\Cgo\Events\RefundRejected::class,
        'cgo_refund_processed'          => App\Domain\Cgo\Events\RefundProcessed::class,
        'cgo_refund_completed'          => App\Domain\Cgo\Events\RefundCompleted::class,
        'cgo_refund_failed'             => App\Domain\Cgo\Events\RefundFailed::class,
        'cgo_refund_cancelled'          => App\Domain\Cgo\Events\RefundCancelled::class,
        
        // Exchange events
        'order_book_created'            => App\Domain\Exchange\Events\OrderBookCreated::class,
        'order_placed'                  => App\Domain\Exchange\Events\OrderPlaced::class,
        'order_cancelled'               => App\Domain\Exchange\Events\OrderCancelled::class,
        'order_filled'                  => App\Domain\Exchange\Events\OrderFilled::class,
        'order_partially_filled'        => App\Domain\Exchange\Events\OrderPartiallyFilled::class,
        'order_matched'                 => App\Domain\Exchange\Events\OrderMatched::class,
        'market_depth_updated'          => App\Domain\Exchange\Events\MarketDepthUpdated::class,
        
        // Liquidity pool events
        'liquidity_pool_created'        => App\Domain\Exchange\Events\LiquidityPoolCreated::class,
        'liquidity_added'               => App\Domain\Exchange\Events\LiquidityAdded::class,
        'liquidity_removed'             => App\Domain\Exchange\Events\LiquidityRemoved::class,
        'swap_executed'                 => App\Domain\Exchange\Events\SwapExecuted::class,
        'fee_collected'                 => App\Domain\Exchange\Events\FeeCollected::class,
        'pool_ratio_updated'            => App\Domain\Exchange\Events\PoolRatioUpdated::class,
        'pool_fee_collected'            => App\Domain\Exchange\Events\PoolFeeCollected::class,
        'liquidity_rewards_distributed' => App\Domain\Exchange\Events\LiquidityRewardsDistributed::class,
        'liquidity_rewards_claimed'     => App\Domain\Exchange\Events\LiquidityRewardsClaimed::class,
        'pool_parameters_updated'       => App\Domain\Exchange\Events\PoolParametersUpdated::class,
        'liquidity_pool_rebalanced'     => App\Domain\Exchange\Events\LiquidityPoolRebalanced::class,
        
        // Stablecoin framework events
        'oracle_deviation_detected'     => App\Domain\Stablecoin\Events\OracleDeviationDetected::class,
        'reserve_pool_created'          => App\Domain\Stablecoin\Events\ReservePoolCreated::class,
        'reserve_deposited'             => App\Domain\Stablecoin\Events\ReserveDeposited::class,
        'reserve_withdrawn'             => App\Domain\Stablecoin\Events\ReserveWithdrawn::class,
        'reserve_rebalanced'            => App\Domain\Stablecoin\Events\ReserveRebalanced::class,
        'custodian_added'               => App\Domain\Stablecoin\Events\CustodianAdded::class,
        'custodian_removed'             => App\Domain\Stablecoin\Events\CustodianRemoved::class,
        'collateralization_ratio_updated' => App\Domain\Stablecoin\Events\CollateralizationRatioUpdated::class,
        'proposal_created'              => App\Domain\Stablecoin\Events\ProposalCreated::class,
        'proposal_vote_cast'            => App\Domain\Stablecoin\Events\ProposalVoteCast::class,
        'proposal_executed'             => App\Domain\Stablecoin\Events\ProposalExecuted::class,
        'proposal_cancelled'            => App\Domain\Stablecoin\Events\ProposalCancelled::class,
        'proposal_finalized'            => App\Domain\Stablecoin\Events\ProposalFinalized::class,
        
        // Blockchain wallet events
        'blockchain_wallet_created'     => App\Domain\Wallet\Events\BlockchainWalletCreated::class,
        'wallet_address_generated'      => App\Domain\Wallet\Events\WalletAddressGenerated::class,
        'wallet_settings_updated'       => App\Domain\Wallet\Events\WalletSettingsUpdated::class,
        'wallet_frozen'                 => App\Domain\Wallet\Events\WalletFrozen::class,
        'wallet_unfrozen'              => App\Domain\Wallet\Events\WalletUnfrozen::class,
        'wallet_key_rotated'           => App\Domain\Wallet\Events\WalletKeyRotated::class,
        'wallet_backup_created'        => App\Domain\Wallet\Events\WalletBackupCreated::class,
        
        // Lending events
        'loan_application_submitted'    => App\Domain\Lending\Events\LoanApplicationSubmitted::class,
        'loan_application_credit_check_completed' => App\Domain\Lending\Events\LoanApplicationCreditCheckCompleted::class,
        'loan_application_risk_assessment_completed' => App\Domain\Lending\Events\LoanApplicationRiskAssessmentCompleted::class,
        'loan_application_approved'     => App\Domain\Lending\Events\LoanApplicationApproved::class,
        'loan_application_rejected'     => App\Domain\Lending\Events\LoanApplicationRejected::class,
        'loan_application_withdrawn'    => App\Domain\Lending\Events\LoanApplicationWithdrawn::class,
        'loan_created'                  => App\Domain\Lending\Events\LoanCreated::class,
        'loan_funded'                   => App\Domain\Lending\Events\LoanFunded::class,
        'loan_disbursed'               => App\Domain\Lending\Events\LoanDisbursed::class,
        'loan_repayment_made'          => App\Domain\Lending\Events\LoanRepaymentMade::class,
        'loan_payment_missed'          => App\Domain\Lending\Events\LoanPaymentMissed::class,
        'loan_defaulted'               => App\Domain\Lending\Events\LoanDefaulted::class,
        'loan_completed'               => App\Domain\Lending\Events\LoanCompleted::class,
        'loan_settled_early'           => App\Domain\Lending\Events\LoanSettledEarly::class,
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
