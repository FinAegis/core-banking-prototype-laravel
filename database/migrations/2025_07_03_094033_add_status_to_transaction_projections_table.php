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
        if (! Schema::hasTable('transaction_projections')) {
            return;
        }

        Schema::table('transaction_projections', function (Blueprint $table) {
            if (! Schema::hasColumn('transaction_projections', 'status')) {
                $table->string('status')->default('completed')->after('metadata');
            }
            if (! Schema::hasColumn('transaction_projections', 'subtype')) {
                $table->string('subtype')->nullable()->after('type');
            }
            if (! Schema::hasColumn('transaction_projections', 'parent_transaction_id')) {
                $table->uuid('parent_transaction_id')->nullable()->after('status');
            }
            if (! Schema::hasColumn('transaction_projections', 'external_reference')) {
                $table->string('external_reference')->nullable()->after('reference');
            }
            if (! Schema::hasColumn('transaction_projections', 'cancelled_at')) {
                $table->timestamp('cancelled_at')->nullable();
            }
            if (! Schema::hasColumn('transaction_projections', 'cancelled_by')) {
                $table->uuid('cancelled_by')->nullable();
            }
            if (! Schema::hasColumn('transaction_projections', 'retried_at')) {
                $table->timestamp('retried_at')->nullable();
            }
            if (! Schema::hasColumn('transaction_projections', 'retry_transaction_id')) {
                $table->uuid('retry_transaction_id')->nullable();
            }
        });

        // Add indexes for performance (outside the table modification for SQLite compatibility)
        if (Schema::hasColumn('transaction_projections', 'status')) {
            Schema::table('transaction_projections', function (Blueprint $table) {
                $table->index('status');
                $table->index(['account_uuid', 'status']);
            });
        }
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
                'retry_transaction_id',
            ]);
        });
    }
};
