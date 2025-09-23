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
        // Agent reputations table - current reputation state
        Schema::create('agent_reputations', function (Blueprint $table) {
            $table->id();
            $table->string('reputation_id')->unique();
            $table->string('agent_id')->index();
            $table->decimal('score', 5, 2)->default(50.00); // 0-100 scale
            $table->enum('trust_level', ['untrusted', 'low', 'neutral', 'high', 'trusted'])->default('neutral');
            $table->integer('total_transactions')->default(0);
            $table->integer('successful_transactions')->default(0);
            $table->integer('failed_transactions')->default(0);
            $table->integer('disputed_transactions')->default(0);
            $table->decimal('success_rate', 5, 2)->default(0.00);
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamp('last_decay_at')->nullable();
            $table->json('metadata')->nullable();
            $table->enum('status', ['active', 'suspended', 'frozen'])->default('active');
            $table->timestamps();

            $table->index(['agent_id', 'status']);
            $table->index(['trust_level', 'status']);
            $table->index('score');
        });

        // Reputation history table - track all reputation changes
        Schema::create('reputation_history', function (Blueprint $table) {
            $table->id();
            $table->string('reputation_id');
            $table->string('agent_id')->index();
            $table->string('transaction_id')->nullable()->index();
            $table->enum('change_type', [
                'initialization',
                'transaction',
                'dispute',
                'boost',
                'decay',
                'manual_adjustment',
            ]);
            $table->decimal('previous_score', 5, 2);
            $table->decimal('new_score', 5, 2);
            $table->decimal('score_change', 5, 2);
            $table->string('previous_trust_level', 20);
            $table->string('new_trust_level', 20);
            $table->string('reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['reputation_id', 'occurred_at']);
            $table->index(['agent_id', 'change_type']);
            $table->index('occurred_at');
        });

        // Reputation factors table - factors affecting reputation
        Schema::create('reputation_factors', function (Blueprint $table) {
            $table->id();
            $table->string('reputation_id');
            $table->string('agent_id')->index();
            $table->enum('factor_type', [
                'transaction_success',
                'transaction_failure',
                'dispute_raised',
                'dispute_resolved',
                'positive_feedback',
                'negative_feedback',
                'verification_completed',
                'violation_reported',
                'inactivity_penalty',
                'achievement_bonus',
            ]);
            $table->decimal('impact_score', 5, 2); // Impact on reputation score
            $table->string('reference_id')->nullable(); // Transaction ID, Dispute ID, etc.
            $table->string('reference_type')->nullable(); // Type of reference
            $table->json('details')->nullable();
            $table->timestamp('applied_at');
            $table->timestamps();

            $table->index(['reputation_id', 'factor_type']);
            $table->index(['agent_id', 'applied_at']);
            $table->index('reference_id');
        });

        // Trust relationships table - agent-to-agent trust scores
        Schema::create('trust_relationships', function (Blueprint $table) {
            $table->id();
            $table->string('agent_a_id');
            $table->string('agent_b_id');
            $table->decimal('trust_score', 5, 2); // Combined trust score
            $table->integer('shared_transactions')->default(0);
            $table->integer('successful_interactions')->default(0);
            $table->integer('disputed_interactions')->default(0);
            $table->decimal('interaction_success_rate', 5, 2)->default(0.00);
            $table->timestamp('last_interaction_at')->nullable();
            $table->json('trust_factors')->nullable();
            $table->enum('status', ['active', 'blocked', 'restricted'])->default('active');
            $table->timestamps();

            $table->unique(['agent_a_id', 'agent_b_id']);
            $table->index(['agent_a_id', 'trust_score']);
            $table->index(['agent_b_id', 'trust_score']);
            $table->index('status');
        });

        // Reputation thresholds table - configurable thresholds
        Schema::create('reputation_thresholds', function (Blueprint $table) {
            $table->id();
            $table->string('operation_type')->unique();
            $table->decimal('minimum_score', 5, 2);
            $table->string('minimum_trust_level', 20)->nullable();
            $table->integer('minimum_transactions')->default(0);
            $table->decimal('minimum_success_rate', 5, 2)->default(0.00);
            $table->boolean('manual_review_required')->default(false);
            $table->json('additional_requirements')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('is_active');
        });

        // Insert default thresholds
        DB::table('reputation_thresholds')->insert([
            [
                'operation_type'         => 'escrow',
                'minimum_score'          => 30.00,
                'minimum_trust_level'    => 'low',
                'minimum_transactions'   => 0,
                'minimum_success_rate'   => 0.00,
                'manual_review_required' => false,
                'is_active'              => true,
                'created_at'             => now(),
                'updated_at'             => now(),
            ],
            [
                'operation_type'         => 'high_value',
                'minimum_score'          => 50.00,
                'minimum_trust_level'    => 'neutral',
                'minimum_transactions'   => 10,
                'minimum_success_rate'   => 70.00,
                'manual_review_required' => false,
                'is_active'              => true,
                'created_at'             => now(),
                'updated_at'             => now(),
            ],
            [
                'operation_type'         => 'instant_settlement',
                'minimum_score'          => 70.00,
                'minimum_trust_level'    => 'high',
                'minimum_transactions'   => 50,
                'minimum_success_rate'   => 85.00,
                'manual_review_required' => false,
                'is_active'              => true,
                'created_at'             => now(),
                'updated_at'             => now(),
            ],
        ]);

        // Reputation decay configuration table
        Schema::create('reputation_decay_config', function (Blueprint $table) {
            $table->id();
            $table->integer('days_threshold')->default(30); // Days of inactivity before decay starts
            $table->decimal('decay_rate', 5, 4)->default(0.01); // 1% per period
            $table->decimal('max_decay_per_period', 5, 2)->default(50.00); // Max 50% decay
            $table->integer('decay_interval_hours')->default(24); // How often to run decay
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Insert default decay config
        DB::table('reputation_decay_config')->insert([
            'days_threshold'       => 30,
            'decay_rate'           => 0.0100,
            'max_decay_per_period' => 50.00,
            'decay_interval_hours' => 24,
            'is_active'            => true,
            'created_at'           => now(),
            'updated_at'           => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reputation_decay_config');
        Schema::dropIfExists('reputation_thresholds');
        Schema::dropIfExists('trust_relationships');
        Schema::dropIfExists('reputation_factors');
        Schema::dropIfExists('reputation_history');
        Schema::dropIfExists('agent_reputations');
    }
};
