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
        Schema::create('setting_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('setting_id')->constrained()->onDelete('cascade');
            $table->string('key', 255);
            $table->json('old_value')->nullable();
            $table->json('new_value');
            $table->string('changed_by')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            
            $table->index(['key', 'created_at']);
            $table->index('changed_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('setting_audits');
    }
};
