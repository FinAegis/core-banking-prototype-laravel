<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Records who placed an account freeze. true = the account owner froze it
| and may lift it again; false (the default, also for every freeze placed
| before this column existed) = staff or the system froze it, and only staff
| (StaffRoles::OPERATORS) may lift it.
*/
return new class () extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('accounts') || Schema::hasColumn('accounts', 'frozen_by_owner')) {
            return;
        }

        Schema::table('accounts', function (Blueprint $table): void {
            $table->boolean('frozen_by_owner')->default(false)->after('frozen');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('accounts') || ! Schema::hasColumn('accounts', 'frozen_by_owner')) {
            return;
        }

        Schema::table('accounts', function (Blueprint $table): void {
            $table->dropColumn('frozen_by_owner');
        });
    }
};
