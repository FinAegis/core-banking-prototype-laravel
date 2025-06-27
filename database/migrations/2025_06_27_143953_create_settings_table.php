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
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('group', 100)->default('general')->index();
            $table->string('key', 255)->unique();
            $table->json('value');
            $table->string('type', 50)->default('string');
            $table->string('label', 255);
            $table->text('description')->nullable();
            $table->boolean('is_public')->default(false)->index();
            $table->boolean('is_encrypted')->default(false);
            $table->json('validation_rules')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            
            $table->index(['group', 'key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
