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
        Schema::create('contact_submissions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->string('subject');
            $table->text('message');
            $table->enum('priority', ['low', 'medium', 'high', 'urgent'])->default('low');
            $table->string('attachment_path')->nullable();
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->enum('status', ['pending', 'responded', 'closed'])->default('pending');
            $table->timestamp('responded_at')->nullable();
            $table->text('response_notes')->nullable();
            $table->timestamps();

            $table->index(['status', 'priority']);
            $table->index('email');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contact_submissions');
    }
};