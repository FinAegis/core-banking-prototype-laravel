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
        Schema::create('agent_transaction_totals', function (Blueprint $table) {
            $table->id();
            $table->string('agent_id');
            $table->decimal('daily_total', 20, 2)->default(0);
            $table->decimal('weekly_total', 20, 2)->default(0);
            $table->decimal('monthly_total', 20, 2)->default(0);
            $table->timestamp('last_daily_reset')->useCurrent();
            $table->timestamp('last_weekly_reset')->useCurrent();
            $table->timestamp('last_monthly_reset')->useCurrent();
            $table->timestamp('last_transaction_at')->nullable();
            $table->timestamps();

            $table->unique('agent_id');
            $table->index(['agent_id', 'last_daily_reset']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agent_transaction_totals');
    }
};
