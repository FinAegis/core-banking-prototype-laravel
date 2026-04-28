<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('mcp_token_policies', function (Blueprint $table) {
            $table->id();
            $table->string('token_id', 100)->unique();
            $table->unsignedBigInteger('daily_limit_minor');
            $table->string('daily_limit_currency', 8)->default('USD');
            $table->unsignedBigInteger('daily_spend_minor')->default(0);
            $table->timestamp('daily_window_start_at')->useCurrent();
            $table->json('scoped_tools')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mcp_token_policies');
    }
};
