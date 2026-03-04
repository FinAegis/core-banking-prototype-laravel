<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('referral_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('code', 8)->unique();
            $table->unsignedInteger('uses_count')->default(0);
            $table->unsignedInteger('max_uses')->default(50);
            $table->boolean('active')->default(true);
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['code', 'active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referral_codes');
    }
};
