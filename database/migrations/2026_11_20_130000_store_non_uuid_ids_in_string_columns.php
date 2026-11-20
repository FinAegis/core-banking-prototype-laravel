<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| These columns were declared uuid() but the code stores other ids in them:
| - compliance_alerts.entity_id, aml_screenings.entity_id: polymorphic ids
|   (bigint user and transaction ids, uuids, 'system');
| - orders.account_id, trades.buyer_account_id / seller_account_id: the
|   Exchange domain keys orders by the account's bigint id;
| - loan_applications.borrower_id, loans.borrower_id: the user's bigint id
|   (GraphQL) or uuid (web).
| On MySQL a uuid() column is CHAR(36) and accepted them; on MariaDB
| (production) it is a native UUID column and every such write failed.
| They become plain strings, keeping their nullability and indexes.
*/
return new class () extends Migration {
    /**
     * @var array<string, list<array{0: string, 1: int, 2: bool}>> table => [column, length, nullable]
     */
    private const COLUMNS = [
        'compliance_alerts' => [['entity_id', 255, true]],
        'aml_screenings'    => [['entity_id', 255, false]],
        'orders'            => [['account_id', 64, false]],
        'trades'            => [['buyer_account_id', 64, false], ['seller_account_id', 64, false]],
        'loan_applications' => [['borrower_id', 64, false]],
        'loans'             => [['borrower_id', 64, false]],
    ];

    public function up(): void
    {
        foreach (self::COLUMNS as $tableName => $columns) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            foreach ($columns as [$column, $length, $nullable]) {
                if (! Schema::hasColumn($tableName, $column)) {
                    continue;
                }

                Schema::table($tableName, function (Blueprint $table) use ($column, $length, $nullable) {
                    $table->string($column, $length)->nullable($nullable)->change();
                });
            }
        }
    }

    public function down(): void
    {
        // Not reversible: rows written after this migration may hold ids that
        // a native UUID column rejects.
    }
};
