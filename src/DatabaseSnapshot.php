<?php

declare(strict_types=1);

namespace OpenDxp\TestFoundation;

use OpenDxp\Db;

final class DatabaseSnapshot
{
    private const string PREFIX = 'snapshot_';

    /**
     * @var list<string>|null
     */
    private static ?array $tables = null;

    public static function save(): void
    {
        if (self::$tables !== null) {
            return;
        }

        $connection = Db::get();
        $tables = $connection->createSchemaManager()->listTableNames();

        self::$tables = array_values(array_filter(
            $tables,
            static fn (string $table) => !str_starts_with($table, self::PREFIX),
        ));

        foreach (self::$tables as $table) {
            $copy = $connection->quoteIdentifier(self::getCopyName($table));
            $original = $connection->quoteIdentifier($table);

            $connection->executeStatement(sprintf('DROP TABLE IF EXISTS %s', $copy));
            $connection->executeStatement(sprintf('CREATE TABLE %s LIKE %s', $copy, $original));
            $connection->executeStatement(sprintf('INSERT INTO %s SELECT * FROM %s', $copy, $original));
        }
    }

    public static function restore(): void
    {
        $connection = Db::get();

        // A transaction that the test left open would roll back the restore when the connection closes.
        while ($connection->isTransactionActive()) {
            $connection->rollBack();
        }

        $connection->executeStatement('SET FOREIGN_KEY_CHECKS = 0');

        foreach (self::$tables ?? [] as $table) {
            $copy = $connection->quoteIdentifier(self::getCopyName($table));
            $original = $connection->quoteIdentifier($table);

            $connection->executeStatement(sprintf('DELETE FROM %s', $original));
            $connection->executeStatement(sprintf('INSERT INTO %s SELECT * FROM %s', $original, $copy));
        }

        $connection->executeStatement('SET FOREIGN_KEY_CHECKS = 1');
    }

    /**
     * Returns a short name, because a long table name plus a prefix would exceed the limit of 64 characters.
     */
    private static function getCopyName(string $table): string
    {
        return self::PREFIX . substr(hash('xxh128', $table), 0, 16);
    }
}
