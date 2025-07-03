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
        Schema::table('cgo_investments', function (Blueprint $table) {
            if (!Schema::hasColumn('cgo_investments', 'email')) {
                $table->string('email')->nullable()->after('user_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cgo_investments', function (Blueprint $table) {
            $table->dropColumn('email');
        });
    }
};