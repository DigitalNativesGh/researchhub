<?php

namespace App\Support;

/**
 * Checks that a file's contents match its extension, so a renamed file cannot slip through upload validation.
 */
class FileSignature
{
    public static function matches(string $path, string $extension): bool
    {
        $handle = @fopen($path, 'rb');
        if (! $handle) {
            return false;
        }
        $head = (string) fread($handle, 4096);
        fclose($handle);

        return match (strtolower($extension)) {
            'pdf' => str_contains(substr($head, 0, 1024), '%PDF-'),
            'docx' => self::zipContains($path, 'word/document.xml'),
            'xlsx' => self::zipContains($path, 'xl/workbook.xml'),
            'odt' => self::zipContains($path, 'content.xml') && str_contains($head, 'application/vnd.oasis.opendocument.text'),
            'doc', 'xls' => str_starts_with($head, "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1"), // OLE2 compound file
            'rtf' => str_starts_with(ltrim($head), '{\rtf'),
            'sav' => str_starts_with($head, '$FL2') || str_starts_with($head, '$FL3'),
            'dta' => str_starts_with($head, '<stata_dta>') || (in_array(ord($head[0] ?? "\0"), [113, 114, 115], true) && in_array(ord($head[1] ?? "\0"), [1, 2], true)),
            'txt', 'csv' => $head !== '' && ! str_contains($head, "\0"), // plain text: no binary NUL bytes
            default => false,
        };
    }

    private static function zipContains(string $path, string $entry): bool
    {
        $zip = new \ZipArchive;
        if ($zip->open($path) !== true) {
            return false;
        }
        $found = $zip->locateName($entry) !== false;
        $zip->close();

        return $found;
    }
}
