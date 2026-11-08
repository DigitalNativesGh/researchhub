<?php

namespace App\Jobs;

use App\Enums\DatasetStatus;
use App\Models\Dataset;
use App\Services\Data\DatasetValidator;
use App\Support\FileSignature;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Checks a data file the researcher uploaded, before they submit it: CSV and Excel files must open as tables;
 * SPSS, Stata and transcript files must be what their extension says (and transcripts must contain text).
 */
class ValidateDataset implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public function __construct(public Dataset $dataset)
    {
        $this->onQueue('default');
    }

    public function handle(DatasetValidator $validator): void
    {
        $dataset = $this->dataset->fresh();

        if (! $dataset || $dataset->submitted_at) {
            return;
        }

        $dataset->forceFill(['status' => DatasetStatus::Validating])->save();
        $extension = $dataset->extension();
        $local = tempnam(sys_get_temp_dir(), 'rh-check');
        file_put_contents($local, Storage::disk(config('researchhub.disk'))->get($dataset->file_path));

        try {
            $report = match (true) {
                ! FileSignature::matches($local, $extension) => DatasetValidator::report(DatasetStatus::Invalid, [self::wrongType($extension)]),
                in_array($extension, ['csv', 'xlsx'], true) => $validator->inspect($dataset),
                $dataset->kind === Dataset::KIND_TRANSCRIPT => $this->transcript($local, $extension),
                default => DatasetValidator::report(DatasetStatus::Valid), // .sav / .dta: opened by the team
            };
        } finally {
            @unlink($local);
        }

        $dataset->forceFill([
            'validation_report' => $report,
            'row_count' => $report['rows'] ?: null,
            'status' => DatasetStatus::from($report['status']),
        ])->save();
    }

    private function transcript(string $path, string $extension): array
    {
        $words = match ($extension) {
            'txt' => str_word_count((string) file_get_contents($path)),
            'docx' => $this->docxWords($path),
            default => 20, // PDF: the signature check is enough
        };

        return $words < 20
            ? DatasetValidator::report(DatasetStatus::Invalid, ['The transcript contains little or no readable text.'])
            : DatasetValidator::report(DatasetStatus::Valid);
    }

    private function docxWords(string $path): int
    {
        $zip = new \ZipArchive;
        if ($zip->open($path) !== true) {
            return 0;
        }
        $xml = (string) $zip->getFromName('word/document.xml');
        $zip->close();

        return str_word_count(strip_tags(str_replace('</w:p>', ' </w:p>', $xml)));
    }

    private static function wrongType(string $extension): string
    {
        return match ($extension) {
            'sav' => 'This does not look like an SPSS data file (.sav). Export it again from SPSS.',
            'dta' => 'This does not look like a Stata data file (.dta). Save it again from Stata.',
            default => 'The file is not a valid .'.$extension.' file.',
        };
    }

    public function failed(Throwable $e): void
    {
        $this->dataset->forceFill([
            'status' => DatasetStatus::Invalid,
            'validation_report' => DatasetValidator::report(DatasetStatus::Invalid, ['The file could not be checked. Please upload it again.']),
        ])->save();
    }
}
