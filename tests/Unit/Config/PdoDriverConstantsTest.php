<?php

declare(strict_types=1);

use Pdo\Mysql as PdoMysql;
use Tests\TestCase;

uses(TestCase::class);

/**
 * @return array<string, mixed>
 */
function loadDatabaseConfig(): array
{
    return require dirname(__DIR__, 3) . '/config/database.php';
}

afterEach(function (): void {
    putenv('MYSQL_ATTR_SSL_CA');
    unset($_ENV['MYSQL_ATTR_SSL_CA'], $_SERVER['MYSQL_ATTR_SSL_CA']);
});

it('passes the SSL CA path to every MySQL and MariaDB connection', function (): void {
    putenv('MYSQL_ATTR_SSL_CA=/etc/ssl/certs/db-ca.pem');
    $_ENV['MYSQL_ATTR_SSL_CA'] = '/etc/ssl/certs/db-ca.pem';
    $_SERVER['MYSQL_ATTR_SSL_CA'] = '/etc/ssl/certs/db-ca.pem';

    $connections = array_filter(
        loadDatabaseConfig()['connections'],
        fn (array $connection): bool => in_array($connection['driver'] ?? null, ['mysql', 'mariadb'], true),
    );

    expect($connections)->not->toBeEmpty();
    foreach ($connections as $name => $connection) {
        expect($connection['options'])->toBe([PdoMysql::ATTR_SSL_CA => '/etc/ssl/certs/db-ca.pem'], "connection {$name}");
    }
});

it('leaves the connection options empty when no SSL CA is set', function (): void {
    expect(loadDatabaseConfig()['connections']['mysql']['options'])->toBe([]);
});

it('uses the driver-specific PDO classes instead of the constants PHP 8.5 deprecates', function (): void {
    // PHP 8.5 deprecates PDO::MYSQL_ATTR_*, PDO::PGSQL_*, PDO::SQLITE_* and the other
    // driver constants on the base PDO class; every use prints a notice. The
    // replacements (Pdo\Mysql::ATTR_*, Pdo\Pgsql::*, ...) exist since PHP 8.4.
    $root = dirname(__DIR__, 3);
    $offenders = [];

    foreach (['app', 'config', 'database', 'bootstrap', 'routes'] as $directory) {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/' . $directory, FilesystemIterator::SKIP_DOTS));
        foreach ($files as $file) {
            if (! $file instanceof SplFileInfo || $file->getExtension() !== 'php') {
                continue;
            }

            $contents = (string) file_get_contents($file->getPathname());
            if (preg_match('/\bPDO::(MYSQL|PGSQL|SQLITE|DBLIB|FB|ODBC|OCI)_[A-Z_]+/', $contents, $match) === 1) {
                $offenders[] = substr($file->getPathname(), strlen($root) + 1) . ': ' . $match[0];
            }
        }
    }

    expect($offenders)->toBe([]);
});
