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
        Schema::create('ai_prompt_templates', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('category'); // query, analysis, compliance, code_generation
            $table->text('system_prompt');
            $table->text('user_template');
            $table->json('variables')->nullable();
            $table->json('metadata')->nullable();
            $table->string('version')->default('1.0');
            $table->boolean('is_active')->default(true);
            $table->integer('usage_count')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index('category');
            $table->index('is_active');
            $table->index(['category', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_prompt_templates');
    }
};
