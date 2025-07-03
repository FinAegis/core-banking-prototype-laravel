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
        Schema::table('transaction_projections', function (Blueprint $table) {
            $table->string('status')->default('completed')->after('metadata');
            $table->string('subtype')->nullable()->after('type');
            $table->uuid('parent_transaction_id')->nullable()->after('status');
            $table->string('external_reference')->nullable()->after('reference');
            $table->timestamp('cancelled_at')->nullable();
            $table->uuid('cancelled_by')->nullable();
            $table->timestamp('retried_at')->nullable();
            $table->uuid('retry_transaction_id')->nullable();
            
            // Add indexes for performance
            $table->index('status');
            $table->index(['account_uuid', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transaction_projections', function (Blueprint $table) {
            $table->dropIndex(['account_uuid', 'status']);
            $table->dropIndex(['status']);
            
            $table->dropColumn([
                'status',
                'subtype',
                'parent_transaction_id',
                'external_reference',
                'cancelled_at',
                'cancelled_by',
                'retried_at',
                'retry_transaction_id'
            ]);
        });
    }
};
