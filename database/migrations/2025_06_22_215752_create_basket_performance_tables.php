<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('basket_performances', function (Blueprint $table) {
            $table->id();
            $table->string('basket_asset_code', 10);
            $table->enum('period_type', ['hour', 'day', 'week', 'month', 'quarter', 'year', 'all_time']);
            $table->dateTime('period_start');
            $table->dateTime('period_end');
            $table->decimal('start_value', 20, 4);
            $table->decimal('end_value', 20, 4);
            $table->decimal('high_value', 20, 4);
            $table->decimal('low_value', 20, 4);
            $table->decimal('average_value', 20, 4);
            $table->decimal('return_value', 20, 4);
            $table->decimal('return_percentage', 10, 4);
            $table->decimal('volatility', 10, 4)->nullable();
            $table->decimal('sharpe_ratio', 10, 4)->nullable();
            $table->decimal('max_drawdown', 10, 4)->nullable();
            $table->integer('value_count')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();
            
            // Indexes
            $table->index('basket_asset_code');
            $table->index('period_type');
            $table->index(['basket_asset_code', 'period_type']);
            $table->index(['period_start', 'period_end']);
            $table->unique(['basket_asset_code', 'period_type', 'period_start'], 'unique_basket_period');
            
            // Foreign key
            $table->foreign('basket_asset_code')->references('code')->on('basket_assets')->onDelete('cascade');
        });
        
        Schema::create('component_performances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('basket_performance_id')->constrained('basket_performances')->onDelete('cascade');
            $table->string('asset_code', 10);
            $table->decimal('start_weight', 8, 4);
            $table->decimal('end_weight', 8, 4);
            $table->decimal('average_weight', 8, 4);
            $table->decimal('contribution_value', 20, 4);
            $table->decimal('contribution_percentage', 10, 4);
            $table->decimal('return_value', 20, 4);
            $table->decimal('return_percentage', 10, 4);
            $table->timestamps();
            
            // Indexes
            $table->index('basket_performance_id');
            $table->index('asset_code');
            $table->index(['basket_performance_id', 'asset_code']);
            
            // Foreign key
            $table->foreign('asset_code')->references('code')->on('assets');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('component_performances');
        Schema::dropIfExists('basket_performances');
    }
};
