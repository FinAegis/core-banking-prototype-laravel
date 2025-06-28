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
        Schema::create('cgo_pricing_rounds', function (Blueprint $table) {
            $table->id();
            $table->integer('round_number')->unique();
            $table->decimal('share_price', 10, 4);
            $table->decimal('max_shares_available', 15, 4);
            $table->decimal('shares_sold', 15, 4)->default(0);
            $table->decimal('total_raised', 15, 2)->default(0);
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->boolean('is_active')->default(false);
            $table->timestamps();
            
            $table->index('is_active');
            $table->index('round_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cgo_pricing_rounds');
    }
};
