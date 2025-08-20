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
        Schema::create('user_fee_tiers', function (Blueprint $table) {
            $table->id();
            $table->string('user_id')->unique();
            $table->string('tier_override')->nullable();
            $table->string('reason')->nullable();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index('expires_at');
        });

        // Add missing columns to orders table if they don't exist
        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table) {
                if (! Schema::hasColumn('orders', 'user_id')) {
                    $table->string('user_id')->nullable()->after('account_id');
                }
                if (! Schema::hasColumn('orders', 'fee_amount')) {
                    $table->decimal('fee_amount', 36, 18)->nullable()->after('average_price');
                }
                if (! Schema::hasColumn('orders', 'executed_at')) {
                    $table->timestamp('executed_at')->nullable();
                }
            });
        }

        // Create promotions table
        Schema::create('promotions', function (Blueprint $table) {
            $table->id();
            $table->string('user_id');
            $table->decimal('discount_rate', 5, 4);
            $table->boolean('active')->default(true);
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index('active');
            $table->index('expires_at');
        });

        // Create pool_promotions table
        Schema::create('pool_promotions', function (Blueprint $table) {
            $table->id();
            $table->string('pool_id');
            $table->decimal('discount_rate', 5, 4);
            $table->boolean('active')->default(true);
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index('pool_id');
            $table->index('active');
            $table->index('expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pool_promotions');
        Schema::dropIfExists('promotions');

        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropColumn(['user_id', 'fee_amount', 'executed_at']);
            });
        }

        Schema::dropIfExists('user_fee_tiers');
    }
};
