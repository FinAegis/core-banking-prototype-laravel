<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('banners', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('subtitle')->nullable();
            $table->string('image_url')->nullable();
            $table->string('action_url')->nullable();
            $table->string('action_type')->default('url'); // url, screen, deeplink
            $table->integer('position')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->json('target_audience')->nullable();
            $table->json('dismissed_by')->nullable(); // array of user IDs
            $table->timestamps();

            $table->index(['active', 'starts_at', 'ends_at']);
            $table->index('position');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('banners');
    }
};
