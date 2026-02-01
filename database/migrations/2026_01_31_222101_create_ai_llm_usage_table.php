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
        Schema::create('ai_llm_usage', function (Blueprint $table) {
            $table->id();
            $table->uuid('conversation_id')->nullable()->index();
            $table->uuid('user_uuid')->nullable()->index();
            $table->string('provider'); // openai, anthropic, etc.
            $table->string('model');
            $table->integer('prompt_tokens')->default(0);
            $table->integer('completion_tokens')->default(0);
            $table->integer('total_tokens')->default(0);
            $table->decimal('cost_usd', 10, 6)->default(0);
            $table->integer('latency_ms')->default(0);
            $table->string('request_type')->nullable(); // query, analysis, compliance
            $table->boolean('success')->default(true);
            $table->text('error_message')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('provider');
            $table->index('model');
            $table->index('created_at');
            $table->index(['provider', 'created_at']);
            $table->index(['user_uuid', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_llm_usage');
    }
};
