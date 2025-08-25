<?php

$configFile = 'config/event-sourcing.php';
$config = file_get_contents($configFile);

// Find the position to insert new events (before the closing bracket of event_class_map)
$insertPosition = strpos($config, "        'trace_completed'        => App\Domain\Monitoring\Events\TraceCompleted::class,");
if ($insertPosition === false) {
    die("Could not find the insertion point\n");
}

// Move to the end of that line
$insertPosition = strpos($config, "\n", $insertPosition) + 1;

$newEvents = "
        // User Domain events
        'user_profile_created'              => App\Domain\User\Events\UserProfileCreated::class,
        'user_profile_updated'              => App\Domain\User\Events\UserProfileUpdated::class,
        'user_profile_verified'             => App\Domain\User\Events\UserProfileVerified::class,
        'user_profile_suspended'            => App\Domain\User\Events\UserProfileSuspended::class,
        'user_profile_deleted'              => App\Domain\User\Events\UserProfileDeleted::class,
        'user_preferences_updated'          => App\Domain\User\Events\UserPreferencesUpdated::class,
        'notification_preferences_updated'  => App\Domain\User\Events\NotificationPreferencesUpdated::class,
        'privacy_settings_updated'          => App\Domain\User\Events\PrivacySettingsUpdated::class,
        'user_activity_tracked'             => App\Domain\User\Events\UserActivityTracked::class,

        // Performance Domain events
        'performance_metric_recorded'       => App\Domain\Performance\Events\MetricRecorded::class,
        'performance_threshold_exceeded'    => App\Domain\Performance\Events\ThresholdExceeded::class,
        'performance_alert_triggered'       => App\Domain\Performance\Events\PerformanceAlertTriggered::class,
        'performance_report_generated'      => App\Domain\Performance\Events\PerformanceReportGenerated::class,

        // Product Domain events
        'product_created'                   => App\Domain\Product\Events\ProductCreated::class,
        'product_updated'                   => App\Domain\Product\Events\ProductUpdated::class,
        'product_activated'                 => App\Domain\Product\Events\ProductActivated::class,
        'product_deactivated'               => App\Domain\Product\Events\ProductDeactivated::class,
        'product_feature_added'             => App\Domain\Product\Events\FeatureAdded::class,
        'product_feature_removed'           => App\Domain\Product\Events\FeatureRemoved::class,
        'product_price_updated'             => App\Domain\Product\Events\PriceUpdated::class,
";

// Insert the new events
$newConfig = substr($config, 0, $insertPosition) . $newEvents . substr($config, $insertPosition);

// Write back to file
file_put_contents($configFile, $newConfig);

echo "Events added successfully!\n";
