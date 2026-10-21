<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Access token for the public BaaS onboarding status and documents
     * endpoints. The token is returned once by POST
     * /api/v2/financial-institutions/apply; only its SHA-256 hash is stored.
     * Applications created before this column existed have no token and are
     * reachable through the admin panel only.
     */
    public function up(): void
    {
        Schema::table('financial_institution_applications', function (Blueprint $table): void {
            $table->string('access_token_hash', 64)->nullable()->after('application_number');
        });
    }

    public function down(): void
    {
        Schema::table('financial_institution_applications', function (Blueprint $table): void {
            $table->dropColumn('access_token_hash');
        });
    }
};
