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
        Schema::table('accounts', function (Blueprint $table) {
            // Add treasury_id for treasury account relationships
            $table->uuid('treasury_id')->nullable()->after('user_uuid')->index();

            // Add user_id for direct foreign key relationship
            if (! Schema::hasColumn('accounts', 'user_id')) {
                $table->foreignId('user_id')->nullable()->after('id')->constrained('users')->onDelete('cascade');
            }

            // Add available_balance for liquidity calculations
            if (! Schema::hasColumn('accounts', 'available_balance')) {
                $table->integer('available_balance')->default(0)->after('balance');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            // Drop the columns in reverse order
            if (Schema::hasColumn('accounts', 'available_balance')) {
                $table->dropColumn('available_balance');
            }

            if (Schema::hasColumn('accounts', 'user_id')) {
                $table->dropForeign(['user_id']);
                $table->dropColumn('user_id');
            }

            if (Schema::hasColumn('accounts', 'treasury_id')) {
                $table->dropIndex(['treasury_id']);
                $table->dropColumn('treasury_id');
            }
        });
    }
};
