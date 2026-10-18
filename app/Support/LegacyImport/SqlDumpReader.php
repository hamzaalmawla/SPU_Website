<?php

declare(strict_types=1);

namespace App\Support\LegacyImport;

use RuntimeException;

/**
 * Reads rows out of a phpMyAdmin SQL dump of the old site without loading it into a database:
 * "INSERT INTO `t` (`a`, `b`) VALUES" followed by one "(...)," line per row.
 */
class SqlDumpReader
{
    /**
     * @param  list<string>  $tables
     * @return iterable<array{0: string, 1: array<string, ?string>}> [table, row keyed by column name]
     */
    public function rows(string $path, array $tables): iterable
    {
        $handle = is_file($path) ? fopen($path, 'rb') : false;
        if ($handle === false) {
            throw new RuntimeException('Unable to open the SQL dump: '.$path);
        }

        $table = null;
        $columns = [];

        try {
            while (($line = fgets($handle)) !== false) {
                if (str_starts_with($line, 'INSERT INTO `')) {
                    $table = null;
                    if (preg_match('/^INSERT INTO `([^`]+)` \(([^)]*)\) VALUES/', $line, $matches) === 1 && in_array($matches[1], $tables, true)) {
                        $table = $matches[1];
                        $columns = array_map(static fn (string $column): string => trim($column, ' `'), explode(',', $matches[2]));
                    }

                    continue;
                }

                $line = trim($line);
                if ($table === null || $line === '' || $line[0] !== '(') {
                    continue;
                }

                $values = str_getcsv(substr(rtrim($line, ',;'), 1, -1), ',', "'", '\\');
                if (count($values) !== count($columns)) {
                    continue;
                }

                $row = [];
                foreach ($columns as $index => $column) {
                    $value = trim($values[$index]);
                    $row[$column] = strtoupper($value) === 'NULL' ? null : str_replace(['\\r', '\\n', '\\t', "\\'", '\\"', '\\\\'], ["\r", "\n", "\t", "'", '"', '\\'], $value);
                }

                yield [$table, $row];
            }
        } finally {
            fclose($handle);
        }
    }
}
