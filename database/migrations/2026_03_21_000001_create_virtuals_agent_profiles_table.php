<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('virtuals_agent_profiles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('virtuals_agent_id')->unique()->comment('Virtuals Protocol agent identifier');
            $table->foreignId('employer_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('agent_name');
            $table->text('agent_description')->nullable();
            $table->string('status')->default('registered');
            $table->uuid('x402_spending_limit_id')->nullable()->comment('Cross-domain FK to spending limits');
            $table->uuid('card_id')->nullable();
            $table->string('trustcert_subject_id')->nullable();
            $table->string('chain')->default('base');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['employer_user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('virtuals_agent_profiles');
    }
};
