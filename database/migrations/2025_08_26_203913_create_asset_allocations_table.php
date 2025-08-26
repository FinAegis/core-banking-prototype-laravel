<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('asset_allocations', function (Blueprint $table) {
            $table->id();
            $table->uuid('portfolio_id');
            $table->string('asset_class');
            $table->decimal('target_weight', 8, 4); // e.g., 25.5000 for 25.5%
            $table->decimal('current_weight', 8, 4);
            $table->decimal('drift', 8, 4);
            $table->decimal('target_amount', 15, 2)->nullable();
            $table->decimal('current_amount', 15, 2)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['portfolio_id', 'asset_class']);
            $table->index('asset_class');
            $table->unique(['portfolio_id', 'asset_class']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_allocations');
    }
};
