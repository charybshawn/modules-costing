<?php

namespace Cultpantry\Costing\Database\Seeders\Concerns;

/**
 * Reads a seeder's CSV data file into associative rows, one per data row,
 * keyed by the header. Same fgetcsv-based parsing ImportKitchenRentalsFromCsv
 * already uses for the real FoodCorridor import -- one CSV convention for
 * the whole module rather than a second, different one just for seed data.
 */
trait ReadsCsv
{
    /**
     * @return array<int, array<string, string>>
     */
    protected function readCsv(string $filename): array
    {
        $handle = fopen(__DIR__.'/../data/'.$filename, 'r');
        if ($handle === false) {
            return [];
        }

        $header = fgetcsv($handle, escape: '\\');
        if ($header === false) {
            fclose($handle);
            return [];
        }
        $header = array_map(fn (string $col) => trim($col), $header);

        $rows = [];
        while (($row = fgetcsv($handle, escape: '\\')) !== false) {
            if (count($row) !== count($header)) {
                continue;
            }
            $rows[] = array_combine($header, $row);
        }

        fclose($handle);

        return $rows;
    }

    /**
     * Every CSV cell is a string -- this is the one place blank means "no
     * value" (null) rather than a literal empty string, for the columns
     * where that distinction matters (brand, notes, sku, an unset
     * preferred source, an incomplete price-history row's date).
     */
    protected function csvNullable(string $value): ?string
    {
        return $value === '' ? null : $value;
    }

    protected function csvFloat(string $value): ?float
    {
        return $value === '' ? null : (float) $value;
    }

    protected function csvInt(string $value): ?int
    {
        return $value === '' ? null : (int) $value;
    }
}
