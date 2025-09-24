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
        Schema::table('agent_wallets', function (Blueprint $table) {
            // Add balance field (in addition to available/held/total balance)
            $table->decimal('balance', 20, 2)->default(0)->after('currency');

            // Add blockchain integration fields
            $table->string('blockchain_address')->nullable()->after('metadata');

            // Add account linking fields
            $table->uuid('linked_account_uuid')->nullable()->after('blockchain_address');
            $table->timestamp('linked_at')->nullable()->after('linked_account_uuid');
            $table->json('link_metadata')->nullable()->after('linked_at');

            // Add status field if it doesn't exist
            if (! Schema::hasColumn('agent_wallets', 'status')) {
                $table->string('status')->default('active')->after('is_active');
            }

            // Add indexes
            $table->index('linked_account_uuid');
            $table->index('blockchain_address');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('agent_wallets', function (Blueprint $table) {
            $table->dropColumn([
                'balance',
                'blockchain_address',
                'linked_account_uuid',
                'linked_at',
                'link_metadata',
            ]);

            if (Schema::hasColumn('agent_wallets', 'status')) {
                $table->dropColumn('status');
            }

            $table->dropIndex(['linked_account_uuid']);
            $table->dropIndex(['blockchain_address']);
        });
    }
};
