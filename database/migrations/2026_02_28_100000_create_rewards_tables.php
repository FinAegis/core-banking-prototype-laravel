<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('reward_profiles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('xp')->default(0);
            $table->unsignedSmallInteger('level')->default(1);
            $table->unsignedSmallInteger('current_streak')->default(0);
            $table->unsignedSmallInteger('longest_streak')->default(0);
            $table->date('last_activity_date')->nullable();
            $table->unsignedInteger('points_balance')->default(0);
            $table->timestamps();

            $table->unique('user_id');
            $table->index('level');
        });

        Schema::create('reward_quests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('slug', 80)->unique();
            $table->string('title', 120);
            $table->text('description');
            $table->unsignedInteger('xp_reward')->default(0);
            $table->unsignedInteger('points_reward')->default(0);
            $table->string('category', 40)->default('general');
            $table->string('icon', 40)->nullable();
            $table->boolean('is_repeatable')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->json('criteria')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });

        Schema::create('reward_quest_completions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('reward_profile_id')->constrained('reward_profiles')->cascadeOnDelete();
            $table->foreignUuid('quest_id')->constrained('reward_quests')->cascadeOnDelete();
            $table->timestamp('completed_at');
            $table->unsignedInteger('xp_earned')->default(0);
            $table->unsignedInteger('points_earned')->default(0);
            $table->timestamps();

            $table->index(['reward_profile_id', 'quest_id']);
        });

        Schema::create('reward_shop_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('slug', 80)->unique();
            $table->string('title', 120);
            $table->text('description');
            $table->unsignedInteger('points_cost');
            $table->string('category', 40)->default('general');
            $table->string('icon', 40)->nullable();
            $table->unsignedInteger('stock')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });

        Schema::create('reward_redemptions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('reward_profile_id')->constrained('reward_profiles')->cascadeOnDelete();
            $table->foreignUuid('shop_item_id')->constrained('reward_shop_items')->cascadeOnDelete();
            $table->unsignedInteger('points_spent');
            $table->string('status', 20)->default('completed');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('reward_profile_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reward_redemptions');
        Schema::dropIfExists('reward_quest_completions');
        Schema::dropIfExists('reward_shop_items');
        Schema::dropIfExists('reward_quests');
        Schema::dropIfExists('reward_profiles');
    }
};
