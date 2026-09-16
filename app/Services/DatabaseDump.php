<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Writes the database out as SQL, in PHP.
 *
 * The obvious way to do this is to shell out to mysqldump, and on a machine
 * you control that is the right answer. Shared hosting is not that machine:
 * exec() and proc_open() are commonly disabled, and mysqldump is often absent
 * even when they are not. A backup that only runs in development is not a
 * backup.
 *
 * So this reads the schema and the rows through the connection the app already
 * has, and streams them to a file. Rows are fetched in pages and written as
 * they arrive, so a table larger than memory is not a problem.
 */
class DatabaseDump
{
    /** Rows held in memory at once. */
    private const PAGE = 500;

    /**
     * Writes every table to $path and returns the number of rows written.
     */
    public function writeTo(string $path): int
    {
        $handle = fopen($path, 'wb');

        if ($handle === false) {
            throw new \RuntimeException("Cannot write the database dump to {$path}");
        }

        try {
            $this->preamble($handle);

            $rows = 0;

            foreach ($this->tables() as $table) {
                $rows += $this->dumpTable($handle, $table);
            }

            if ($this->isMysql()) {
                fwrite($handle, "\nSET FOREIGN_KEY_CHECKS = 1;\n");
            }

            return $rows;
        } finally {
            fclose($handle);
        }
    }

    /**
     * The tables belonging to this database.
     *
     * Schema::getTables() reports every schema the connection can see, which
     * on a shared MySQL server is every other database on the box, so the
     * results are filtered back to the one the app is configured for.
     *
     * @return list<string>
     */
    public function tables(): array
    {
        $database = DB::getDatabaseName();
        $names = [];

        foreach (Schema::getTables() as $table) {
            $schema = $table['schema'] ?? null;

            if ($this->isMysql() && $schema !== null && $schema !== $database) {
                continue;
            }

            $name = (string) $table['name'];

            // SQLite keeps its own bookkeeping in tables nothing should restore.
            if (str_starts_with($name, 'sqlite_')) {
                continue;
            }

            $names[] = $name;
        }

        sort($names);

        return $names;
    }

    private function isMysql(): bool
    {
        return in_array(DB::getDriverName(), ['mysql', 'mariadb'], true);
    }

    /** An identifier quoted the way this driver expects. */
    private function quote(string $identifier): string
    {
        return $this->isMysql()
            ? '`' . str_replace('`', '``', $identifier) . '`'
            : '"' . str_replace('"', '""', $identifier) . '"';
    }

    private function preamble($handle): void
    {
        fwrite($handle, sprintf(
            "-- NexHRIS database dump\n-- %s\n-- database: %s (%s)\n\n",
            now()->toDateTimeString(),
            DB::getDatabaseName(),
            DB::getDriverName(),
        ));

        if ($this->isMysql()) {
            fwrite($handle, "SET FOREIGN_KEY_CHECKS = 0;\nSET NAMES utf8mb4;\n\n");
        }
    }

    /** The CREATE TABLE the driver reports for this table. */
    private function createStatement(string $table): string
    {
        if ($this->isMysql()) {
            $row = (array) DB::select('SHOW CREATE TABLE ' . $this->quote($table))[0];

            return array_values($row)[1];
        }

        $row = DB::selectOne(
            'SELECT sql FROM sqlite_master WHERE type = ? AND name = ?',
            ['table', $table],
        );

        return (string) ($row->sql ?? '');
    }

    private function dumpTable($handle, string $table): int
    {
        $quoted = $this->quote($table);

        fwrite($handle, "\n--\n-- {$table}\n--\n\n");
        fwrite($handle, "DROP TABLE IF EXISTS {$quoted};\n");
        fwrite($handle, $this->createStatement($table) . ";\n\n");

        $pdo = DB::connection()->getPdo();
        $written = 0;
        $offset = 0;

        while (true) {
            $rows = DB::select("SELECT * FROM {$quoted} LIMIT " . self::PAGE . " OFFSET {$offset}");

            if ($rows === []) {
                break;
            }

            foreach ($rows as $row) {
                $values = [];

                foreach ((array) $row as $value) {
                    $values[] = match (true) {
                        $value === null => 'NULL',
                        is_int($value), is_float($value) => (string) $value,
                        is_bool($value) => $value ? '1' : '0',
                        default => $pdo->quote((string) $value),
                    };
                }

                fwrite($handle, "INSERT INTO {$quoted} VALUES (" . implode(',', $values) . ");\n");
                $written++;
            }

            $offset += self::PAGE;
        }

        return $written;
    }
}
