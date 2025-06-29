<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fraud_scores', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('entity_id')->index(); // Can be transaction_id, user_id, account_id
            $table->string('entity_type'); // transaction, user, account, session
            $table->string('score_type'); // real_time, batch, ml_prediction
            
            // Scoring Details
            $table->decimal('total_score', 5, 2); // 0-100
            $table->string('risk_level'); // very_low, low, medium, high, very_high
            $table->json('score_breakdown'); // Individual rule scores
            $table->json('triggered_rules'); // Rules that contributed to score
            
            // Context Data
            $table->json('entity_snapshot'); // Entity state at time of scoring
            $table->json('behavioral_factors')->nullable(); // Behavioral analysis results
            $table->json('device_factors')->nullable(); // Device fingerprint data
            $table->json('network_factors')->nullable(); // IP, location data
            
            // ML Components
            $table->decimal('ml_score', 5, 2)->nullable(); // ML model score
            $table->string('ml_model_version')->nullable();
            $table->json('ml_features')->nullable(); // Features used for ML scoring
            $table->json('ml_explanation')->nullable(); // SHAP values or similar
            
            // Decision
            $table->string('decision'); // allow, block, challenge, review
            $table->json('decision_factors'); // What influenced the decision
            $table->timestamp('decision_at');
            $table->boolean('is_override')->default(false); // Manual override
            $table->foreignId('override_by')->nullable()->constrained('users');
            $table->text('override_reason')->nullable();
            
            // Feedback Loop
            $table->string('outcome')->nullable(); // fraud, legitimate, unknown
            $table->timestamp('outcome_confirmed_at')->nullable();
            $table->foreignId('confirmed_by')->nullable()->constrained('users');
            $table->text('outcome_notes')->nullable();
            
            $table->timestamps();
            
            $table->index(['entity_id', 'entity_type']);
            $table->index(['risk_level', 'created_at']);
            $table->index('decision');
            $table->index(['outcome', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fraud_scores');
    }
};