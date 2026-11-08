<?php

namespace App\Services\Data;

use App\Enums\DatasetStatus;
use App\Models\Dataset;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Checks a CSV or Excel file the researcher uploads: it opens, has a header row and at least one row of data.
 * The team works with the data itself; this only catches files that are empty, corrupt or not tables.
 */
class DatasetValidator
{
    public function __construct(private readonly TabularReader $reader) {}

    public function inspect(Dataset $dataset): array
    {
        $local = tempnam(sys_get_temp_dir(), 'rh-data');
        file_put_contents($local, Storage::disk(config('researchhub.disk'))->get($dataset->file_path));

        try {
            [$header, $rows] = $this->reader->read($local, $dataset->extension());
        } catch (RuntimeException $e) {
            return self::report(DatasetStatus::Invalid, [$e->getMessage()]);
        } finally {
            @unlink($local);
        }

        $errors = [];
        $warnings = [];
        $named = array_values(array_filter($header, fn ($h) => trim((string) $h) !== ''));

        if ($named === []) {
            $errors[] = 'The first row must contain the column names (for example the question codes from your instrument).';
        }
        if ($rows === []) {
            $errors[] = 'The file has a header row but no data.';
        }
        $duplicates = array_keys(array_filter(array_count_values($named), fn ($c) => $c > 1));
        if ($duplicates) {
            $warnings[] = 'These column names appear more than once: '.implode(', ', $duplicates).'.';
        }
        if (count($named) < count($header)) {
            $warnings[] = (count($header) - count($named)).' column(s) have no name.';
        }

        $status = $errors ? DatasetStatus::Invalid : ($warnings ? DatasetStatus::ValidWithWarnings : DatasetStatus::Valid);

        return array_merge(self::report($status, $errors, $warnings), ['rows' => count($rows), 'header' => $header]);
    }

    public static function report(DatasetStatus $status, array $errors = [], array $warnings = []): array
    {
        return ['status' => $status->value, 'errors' => $errors, 'warnings' => $warnings, 'rows' => 0, 'header' => []];
    }
}
