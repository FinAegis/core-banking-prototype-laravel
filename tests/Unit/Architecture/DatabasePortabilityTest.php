<?php

declare(strict_types=1);

/*
 * Production runs MariaDB 11.4; CI also runs MySQL 8 and SQLite. MariaDB has
 * no `->` / `->>` JSON operators, so a raw `col->>'$.key'` that works on
 * MySQL and SQLite fails there with a syntax error (the account transfer
 * history returned 500 in production). Use Laravel's JSON path syntax
 * (`where('col->key', ...)`), which the query grammar compiles per driver.
 */
it('uses no MySQL-only JSON operators in raw SQL', function (): void {
    $root = dirname(__DIR__, 3);
    $offenders = [];

    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/app', FilesystemIterator::SKIP_DOTS));
    foreach ($files as $file) {
        if (! $file instanceof SplFileInfo || $file->getExtension() !== 'php') {
            continue;
        }

        foreach (file($file->getPathname()) ?: [] as $number => $line) {
            if (preg_match("/[A-Za-z_`]+\\s*->>?\\s*'\\\$/", $line) === 1) {
                $offenders[] = substr($file->getPathname(), strlen($root) + 1) . ':' . ($number + 1);
            }
        }
    }

    expect($offenders)->toBe([]);
});
