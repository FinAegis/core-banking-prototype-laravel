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
        Schema::create('batch_job_items', function (Blueprint $table) {
            $table->id();
            $table->uuid('batch_job_uuid');
            $table->integer('sequence')->unsigned();
            $table->string('status')->default('pending'); // pending, processing, completed, failed
            $table->json('data');
            $table->json('result')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            
            $table->index('batch_job_uuid');
            $table->index('status');
            $table->unique(['batch_job_uuid', 'sequence']);
            
            $table->foreign('batch_job_uuid')
                ->references('uuid')
                ->on('batch_jobs')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('batch_job_items');
    }
};
