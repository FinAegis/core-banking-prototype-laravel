<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| When the user last signed in. The compliance access review reads it to find
| inactive accounts, and the AI risk saga reads it as a signal. Nothing kept
| it before, so the review saw every account as never used.
*/
return new class () extends Migration {
    public function up(): void
    {
        if (Schema::hasColumn('users', 'last_login_at')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->timestamp('last_login_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'last_login_at')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropIndex(['last_login_at']);
                $table->dropColumn('last_login_at');
            });
        }
    }
};
