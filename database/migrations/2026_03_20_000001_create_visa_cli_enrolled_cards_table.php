<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('visa_cli_enrolled_cards', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('card_identifier')->unique()->comment('Visa CLI card identifier');
            $table->string('last4', 4)->comment('Last 4 digits of card');
            $table->string('network')->default('visa')->comment('Card network');
            $table->string('status')->default('enrolled')->comment('Card status');
            $table->string('github_username')->nullable()->comment('GitHub username linked to card');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visa_cli_enrolled_cards');
    }
};
