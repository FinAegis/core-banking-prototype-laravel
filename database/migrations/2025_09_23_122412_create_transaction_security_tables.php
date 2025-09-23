<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Transaction security records table
        Schema::create('transaction_security', function (Blueprint $table) {
            $table->id();
            $table->string('security_id')->unique();
            $table->string('transaction_id')->index();
            $table->string('agent_id')->index();
            $table->enum('security_level', ['standard', 'enhanced', 'maximum'])->default('standard');
            $table->enum('status', [
                'pending',
                'signed',
                'encrypted',
                'verified',
                'suspicious',
                'failed',
                'approved',
                'rejected',
                'review_required',
            ])->default('pending');
            $table->json('signatures')->nullable();
            $table->json('encryption_keys')->nullable();
            $table->json('verification_history')->nullable();
            $table->json('fraud_checks')->nullable();
            $table->decimal('latest_risk_score', 5, 2)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('signed_at')->nullable();
            $table->timestamp('encrypted_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('fraud_checked_at')->nullable();
            $table->timestamps();

            $table->index(['transaction_id', 'status']);
            $table->index(['agent_id', 'status']);
            $table->index('security_level');
            $table->index('latest_risk_score');
        });

        // Security audit log table
        Schema::create('security_audit_log', function (Blueprint $table) {
            $table->id();
            $table->string('security_id')->nullable()->index();
            $table->string('transaction_id')->nullable()->index();
            $table->string('agent_id')->index();
            $table->enum('event_type', [
                'signature_created',
                'signature_verified',
                'signature_failed',
                'encryption_applied',
                'decryption_performed',
                'fraud_check_passed',
                'fraud_check_failed',
                'security_alert',
                'manual_review',
                'key_rotation',
                'audit_performed',
            ]);
            $table->string('event_status');
            $table->text('event_description')->nullable();
            $table->json('event_data')->nullable();
            $table->string('performed_by')->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['agent_id', 'occurred_at']);
            $table->index(['event_type', 'occurred_at']);
            $table->index('occurred_at');
        });

        // Fraud detection patterns table
        Schema::create('fraud_patterns', function (Blueprint $table) {
            $table->id();
            $table->string('pattern_id')->unique();
            $table->string('pattern_name');
            $table->enum('pattern_type', [
                'velocity',
                'amount_anomaly',
                'geographic',
                'time_based',
                'behavioral',
                'network',
                'device',
            ]);
            $table->json('pattern_rules');
            $table->decimal('weight', 3, 2)->default(1.00);
            $table->decimal('threshold', 5, 2)->default(50.00);
            $table->boolean('is_active')->default(true);
            $table->integer('matches_count')->default(0);
            $table->integer('false_positive_count')->default(0);
            $table->decimal('accuracy_rate', 5, 2)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('pattern_type');
            $table->index('is_active');
        });

        // Agent security keys table
        Schema::create('agent_security_keys', function (Blueprint $table) {
            $table->id();
            $table->string('agent_id')->index();
            $table->string('key_id')->unique();
            $table->enum('key_type', ['RSA', 'ECDSA', 'EdDSA', 'AES', 'ChaCha20']);
            $table->enum('key_purpose', ['signing', 'encryption', 'both'])->default('signing');
            $table->text('public_key')->nullable();
            $table->text('encrypted_private_key')->nullable(); // Encrypted storage
            $table->string('key_fingerprint')->unique();
            $table->integer('key_size')->nullable();
            $table->string('algorithm')->nullable();
            $table->enum('status', ['active', 'rotating', 'archived', 'revoked'])->default('active');
            $table->timestamp('activated_at');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('rotated_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->string('revoked_reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['agent_id', 'status']);
            $table->index(['key_type', 'status']);
            $table->index('expires_at');
        });

        // Transaction fraud analysis table
        Schema::create('transaction_fraud_analysis', function (Blueprint $table) {
            $table->id();
            $table->string('analysis_id')->unique();
            $table->string('transaction_id')->index();
            $table->string('agent_id')->index();
            $table->decimal('risk_score', 5, 2);
            $table->enum('risk_level', ['low', 'medium', 'high', 'critical']);
            $table->enum('decision', ['approve', 'review', 'reject']);
            $table->json('risk_factors');
            $table->json('velocity_analysis')->nullable();
            $table->json('amount_analysis')->nullable();
            $table->json('pattern_analysis')->nullable();
            $table->json('geographic_analysis')->nullable();
            $table->json('time_analysis')->nullable();
            $table->boolean('manual_review_required')->default(false);
            $table->string('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('review_decision')->nullable();
            $table->text('review_notes')->nullable();
            $table->timestamp('analyzed_at');
            $table->timestamps();

            $table->index(['transaction_id', 'decision']);
            $table->index(['agent_id', 'analyzed_at']);
            $table->index(['risk_score', 'decision']);
            $table->index('manual_review_required');
        });

        // Security alerts table
        Schema::create('security_alerts', function (Blueprint $table) {
            $table->id();
            $table->string('alert_id')->unique();
            $table->string('transaction_id')->nullable()->index();
            $table->string('agent_id')->nullable()->index();
            $table->enum('alert_type', [
                'fraud_detected',
                'signature_failure',
                'encryption_failure',
                'suspicious_activity',
                'brute_force_attempt',
                'key_compromise',
                'policy_violation',
                'threshold_exceeded',
            ]);
            $table->enum('severity', ['low', 'medium', 'high', 'critical']);
            $table->string('alert_title');
            $table->text('alert_description');
            $table->json('alert_data')->nullable();
            $table->enum('status', ['open', 'acknowledged', 'investigating', 'resolved', 'false_positive'])->default('open');
            $table->string('assigned_to')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->text('resolution_notes')->nullable();
            $table->json('actions_taken')->nullable();
            $table->timestamps();

            $table->index(['alert_type', 'status']);
            $table->index(['severity', 'status']);
            $table->index(['status', 'created_at']);
        });

        // Insert default fraud patterns
        DB::table('fraud_patterns')->insert([
            [
                'pattern_id'    => 'velocity_check',
                'pattern_name'  => 'High Transaction Velocity',
                'pattern_type'  => 'velocity',
                'pattern_rules' => json_encode([
                    'hourly_limit' => 10,
                    'daily_limit'  => 50,
                    'window_size'  => 60,
                ]),
                'weight'     => 0.25,
                'threshold'  => 60.00,
                'is_active'  => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'pattern_id'    => 'amount_anomaly',
                'pattern_name'  => 'Unusual Transaction Amount',
                'pattern_type'  => 'amount_anomaly',
                'pattern_rules' => json_encode([
                    'z_score_threshold' => 3,
                    'min_history'       => 10,
                ]),
                'weight'     => 0.20,
                'threshold'  => 70.00,
                'is_active'  => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'pattern_id'    => 'night_activity',
                'pattern_name'  => 'Night Time Activity',
                'pattern_type'  => 'time_based',
                'pattern_rules' => json_encode([
                    'night_hours'     => [2, 3, 4, 5],
                    'risk_multiplier' => 1.5,
                ]),
                'weight'     => 0.10,
                'threshold'  => 40.00,
                'is_active'  => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('security_alerts');
        Schema::dropIfExists('transaction_fraud_analysis');
        Schema::dropIfExists('agent_security_keys');
        Schema::dropIfExists('fraud_patterns');
        Schema::dropIfExists('security_audit_log');
        Schema::dropIfExists('transaction_security');
    }
};
