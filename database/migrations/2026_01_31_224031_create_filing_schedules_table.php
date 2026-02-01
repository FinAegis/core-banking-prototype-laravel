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
        Schema::create('filing_schedules', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('report_type');
            $table->string('jurisdiction', 10);
            $table->string('regulator')->default('default');
            $table->string('frequency')->default('quarterly');
            $table->unsignedInteger('deadline_days')->default(30);
            $table->time('deadline_time')->nullable();
            $table->date('next_due_date')->nullable();
            $table->timestamp('last_filed_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('auto_generate')->default(false);
            $table->json('notification_settings')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['jurisdiction', 'report_type']);
            $table->index(['next_due_date', 'is_active']);
            $table->index('regulator');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('filing_schedules');
    }
};
