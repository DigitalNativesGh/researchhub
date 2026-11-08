<?php

namespace App\Services\Data;

use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;

/**
 * Reads CSV or Excel files into a header row plus data rows (all values as trimmed strings).
 */
class TabularReader
{
    public const MAX_ROWS = 100000;

    /** @return array{0: list<string>, 1: list<list<string>>} */
    public function read(string $path, string $extension): array
    {
        $rows = in_array(strtolower($extension), ['xlsx', 'xls'], true) ? $this->excel($path) : $this->csv($path);

        // Drop fully empty rows.
        $rows = array_values(array_filter($rows, fn ($row) => implode('', $row) !== ''));

        if ($rows === []) {
            throw new RuntimeException('The file is empty.');
        }

        $header = array_map(fn ($h) => trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $h)), array_shift($rows));

        // Trim trailing empty header cells (common in Excel exports).
        while ($header !== [] && end($header) === '') {
            array_pop($header);
        }

        $width = count($header);
        $rows = array_map(fn ($row) => array_slice(array_pad($row, $width, ''), 0, $width), $rows);

        if (count($rows) > self::MAX_ROWS) {
            throw new RuntimeException('The file has more than '.number_format(self::MAX_ROWS).' rows.');
        }

        return [$header, $rows];
    }

    private function csv(string $path): array
    {
        $handle = fopen($path, 'r');
        if (! $handle) {
            throw new RuntimeException('The file could not be opened.');
        }

        $sample = fread($handle, 8192);
        rewind($handle);
        $delimiter = $this->sniff($sample);

        $rows = [];
        while (($row = fgetcsv($handle, 0, $delimiter, '"', '')) !== false) {
            if ($row === [null]) {
                continue;
            }
            $rows[] = array_map(fn ($v) => trim((string) $v), $row);
            if (count($rows) > self::MAX_ROWS + 1) {
                break;
            }
        }
        fclose($handle);

        return $rows;
    }

    private function excel(string $path): array
    {
        try {
            $reader = IOFactory::createReaderForFile($path);
            $reader->setReadDataOnly(true);
            $sheet = $reader->load($path)->getSheet(0);
        } catch (\Throwable $e) {
            throw new RuntimeException('The Excel file could not be read: '.$e->getMessage(), previous: $e);
        }

        $rows = [];
        foreach ($sheet->toArray(null, true, false, false) as $row) {
            $rows[] = array_map(function ($v) {
                if (is_float($v) && floor($v) == $v && abs($v) < 1e15) {
                    return (string) (int) $v;
                }

                return trim((string) $v);
            }, $row);
        }

        return $rows;
    }

    private function sniff(string $sample): string
    {
        $firstLine = strtok($sample, "\n") ?: $sample;
        $counts = [',' => substr_count($firstLine, ','), ';' => substr_count($firstLine, ';'), "\t" => substr_count($firstLine, "\t")];
        arsort($counts);

        return array_key_first($counts);
    }
}
