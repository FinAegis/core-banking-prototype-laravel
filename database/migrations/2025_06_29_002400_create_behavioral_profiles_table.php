<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('behavioral_profiles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->unique()->constrained();
            
            // Transaction Patterns
            $table->json('typical_transaction_times'); // Hour distribution
            $table->json('typical_transaction_days'); // Day of week distribution
            $table->decimal('avg_transaction_amount', 15, 2)->nullable();
            $table->decimal('median_transaction_amount', 15, 2)->nullable();
            $table->decimal('max_transaction_amount', 15, 2)->nullable();
            $table->decimal('transaction_amount_std_dev', 15, 2)->nullable();
            $table->integer('avg_daily_transaction_count')->default(0);
            $table->integer('avg_monthly_transaction_count')->default(0);
            
            // Location Patterns
            $table->json('common_locations'); // Frequently used locations
            $table->json('location_history'); // Historical location data
            $table->string('primary_country')->nullable();
            $table->string('primary_city')->nullable();
            $table->boolean('travels_frequently')->default(false);
            $table->json('travel_patterns')->nullable();
            
            // Device Patterns
            $table->json('trusted_devices'); // Device fingerprint IDs
            $table->integer('device_count')->default(0);
            $table->boolean('uses_multiple_devices')->default(false);
            $table->json('device_switching_pattern')->nullable();
            
            // Merchant/Recipient Patterns
            $table->json('frequent_merchants')->nullable();
            $table->json('frequent_recipients')->nullable();
            $table->json('merchant_categories')->nullable(); // MCC codes
            $table->boolean('has_recurring_payments')->default(false);
            $table->json('recurring_payment_patterns')->nullable();
            
            // Session Behavior
            $table->decimal('avg_session_duration', 8, 2)->nullable(); // minutes
            $table->json('typical_login_times')->nullable();
            $table->integer('avg_actions_per_session')->default(0);
            $table->json('common_features_used')->nullable();
            
            // Risk Indicators
            $table->integer('profile_change_frequency')->default(0);
            $table->integer('password_change_frequency')->default(0);
            $table->boolean('uses_2fa')->default(false);
            $table->integer('failed_login_attempts')->default(0);
            $table->timestamp('last_suspicious_activity')->nullable();
            
            // Velocity Metrics
            $table->decimal('max_daily_volume', 15, 2)->nullable();
            $table->decimal('max_weekly_volume', 15, 2)->nullable();
            $table->decimal('max_monthly_volume', 15, 2)->nullable();
            $table->integer('max_daily_transactions')->nullable();
            
            // Profile Maturity
            $table->integer('days_since_first_transaction')->default(0);
            $table->integer('total_transaction_count')->default(0);
            $table->decimal('total_transaction_volume', 15, 2)->default(0);
            $table->timestamp('profile_established_at')->nullable();
            $table->boolean('is_established')->default(false); // Enough data for reliable profiling
            
            // ML Features
            $table->json('ml_feature_vector')->nullable(); // Computed features for ML
            $table->timestamp('ml_features_updated_at')->nullable();
            
            $table->timestamps();
            
            $table->index('is_established');
            $table->index('last_suspicious_activity');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('behavioral_profiles');
    }
};