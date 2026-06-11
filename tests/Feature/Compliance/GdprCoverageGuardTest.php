<?php

declare(strict_types=1);

use App\Domain\Compliance\Services\GdprService;
use Illuminate\Support\Facades\Schema;

/**
 * Schema regression guard for GDPR coverage (mirrors the philosophy of
 * bin/check-test-coverage): every table carrying a user FK column must be
 * consciously opted IN (GdprService::COVERED_USER_DATA_TABLES — handled by
 * export/erasure) or OUT (GdprService::EXCLUDED_USER_DATA_TABLES — with a
 * written justification). A new user-data table that is neither fails here.
 */

/**
 * @return array<int, string> tables in the migrated schema that carry a user FK column
 */
function gdprUserLinkedTablesInSchema(): array
{
    $found = [];

    /** @var array<int, array{name: string}> $tables */
    $tables = Schema::getTables();

    foreach ($tables as $table) {
        $name = $table['name'];

        // Framework-internal bookkeeping table — never user data.
        if ($name === 'migrations') {
            continue;
        }

        foreach (Schema::getColumnListing($name) as $column) {
            if (GdprService::isUserLinkColumn($column)) {
                $found[] = $name;

                break;
            }
        }
    }

    sort($found);

    return $found;
}

test('every table with a user FK column is consciously covered or excluded by GdprService', function () {
    $userLinkedTables = gdprUserLinkedTablesInSchema();

    expect($userLinkedTables)->not->toBeEmpty();

    $uncovered = GdprService::uncoveredUserDataTables($userLinkedTables);

    expect($uncovered)->toBeEmpty(
        'New user-data table(s) detected with no GDPR coverage decision: ['
        . implode(', ', $uncovered) . ']. Either handle them in '
        . 'GdprService::exportUserData()/deleteUserData() and add them to '
        . 'GdprService::COVERED_USER_DATA_TABLES, or add a justified entry to '
        . 'GdprService::EXCLUDED_USER_DATA_TABLES.'
    );
});

test('no table is listed as both covered and excluded', function () {
    $overlap = array_values(array_intersect(
        GdprService::COVERED_USER_DATA_TABLES,
        array_keys(GdprService::EXCLUDED_USER_DATA_TABLES),
    ));

    expect($overlap)->toBeEmpty(
        'Tables listed in both COVERED_USER_DATA_TABLES and EXCLUDED_USER_DATA_TABLES: ['
        . implode(', ', $overlap) . ']'
    );
});

test('covered and excluded lists contain no stale (dropped or renamed) tables', function () {
    $userLinkedTables = gdprUserLinkedTablesInSchema();

    $listed = array_merge(
        GdprService::COVERED_USER_DATA_TABLES,
        array_keys(GdprService::EXCLUDED_USER_DATA_TABLES),
    );

    $stale = array_values(array_diff($listed, $userLinkedTables));

    expect($stale)->toBeEmpty(
        'Tables listed in GdprService coverage constants that no longer exist '
        . '(or no longer carry a user FK column): [' . implode(', ', $stale) . ']. '
        . 'Remove or rename the stale entries.'
    );
});

test('every excluded table carries a non-empty justification', function () {
    foreach (GdprService::EXCLUDED_USER_DATA_TABLES as $table => $justification) {
        expect(trim($justification) !== '')->toBeTrue("Excluded table {$table} has no justification");
    }
});

// Unit-level checks of the pure diff logic: the guard must flag a synthetic
// uncovered table and must accept known covered/excluded ones.
test('uncoveredUserDataTables flags a synthetic uncovered table', function () {
    $result = GdprService::uncoveredUserDataTables([
        'users',                       // covered
        'sessions',                    // excluded
        'synthetic_new_pii_table',     // neither => must be flagged
    ]);

    expect($result)->toBe(['synthetic_new_pii_table']);
});

test('uncoveredUserDataTables returns empty for fully covered input', function () {
    expect(GdprService::uncoveredUserDataTables(['users', 'bridge_customers', 'sessions']))->toBeEmpty();
});

test('isUserLinkColumn matches canonical and suffixed user FK columns only', function () {
    expect(GdprService::isUserLinkColumn('user_id'))->toBeTrue();
    expect(GdprService::isUserLinkColumn('user_uuid'))->toBeTrue();
    expect(GdprService::isUserLinkColumn('privy_user_id'))->toBeTrue();
    expect(GdprService::isUserLinkColumn('previous_user_id'))->toBeTrue();
    expect(GdprService::isUserLinkColumn('first_user_id'))->toBeTrue();
    expect(GdprService::isUserLinkColumn('subject_user_uuid'))->toBeTrue();
    expect(GdprService::isUserLinkColumn('USER_ID'))->toBeTrue();

    expect(GdprService::isUserLinkColumn('id'))->toBeFalse();
    expect(GdprService::isUserLinkColumn('uuid'))->toBeFalse();
    expect(GdprService::isUserLinkColumn('operator_id'))->toBeFalse();
    expect(GdprService::isUserLinkColumn('user_agent'))->toBeFalse();
    expect(GdprService::isUserLinkColumn('username'))->toBeFalse();
});
