<?php

namespace App\Support;

/**
 * Builds small, valid text PDFs. Used by the demo seeder and the test suite so that uploads go through the
 * same validation (magic bytes, page count) as real papers, without shipping binary fixtures.
 */
class SamplePdf
{
    /**
     * @param  list<string>  $pages  plain text per page; lines are wrapped at ~90 characters
     */
    public static function make(string $title, array $pages): string
    {
        $objects = [];
        $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objects[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';
        $kids = [];
        $next = 4;

        foreach (array_values($pages) as $i => $text) {
            $lines = $i === 0 ? array_merge([$title, ''], self::wrap($text)) : self::wrap($text);
            $stream = "BT\n/F1 11 Tf\n14 TL\n50 790 Td\n";
            foreach ($lines as $line) {
                $stream .= '('.self::escape($line).") Tj T*\n";
            }
            $stream .= "ET\n";

            $pageId = $next++;
            $contentId = $next++;
            $kids[] = "{$pageId} 0 R";
            $objects[$pageId] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 3 0 R >> >> /Contents {$contentId} 0 R >>";
            $objects[$contentId] = '<< /Length '.strlen($stream)." >>\nstream\n{$stream}endstream";
        }

        $objects[2] = '<< /Type /Pages /Kids ['.implode(' ', $kids).'] /Count '.count($kids).' >>';
        $objects[$next] = '<< /Title ('.self::escape($title).') /Producer (ResearchHub sample) >>';
        $infoId = $next;
        ksort($objects);

        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [];
        foreach ($objects as $id => $body) {
            $offsets[$id] = strlen($pdf);
            $pdf .= "{$id} 0 obj\n{$body}\nendobj\n";
        }

        $xref = strlen($pdf);
        $count = max(array_keys($objects)) + 1;
        $pdf .= "xref\n0 {$count}\n0000000000 65535 f \n";
        for ($id = 1; $id < $count; $id++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$id] ?? 0);
        }
        $pdf .= "trailer\n<< /Size {$count} /Root 1 0 R /Info {$infoId} 0 R >>\nstartxref\n{$xref}\n%%EOF\n";

        return $pdf;
    }

    /** @return list<string> */
    private static function wrap(string $text): array
    {
        $out = [];
        foreach (preg_split('/\R/', $text) as $paragraph) {
            $out = array_merge($out, explode("\n", wordwrap($paragraph, 90, "\n", true)));
        }

        return array_slice($out, 0, 52);
    }

    private static function escape(string $text): string
    {
        $text = iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $text) ?: $text;

        return str_replace(['\\', '(', ')', "\r"], ['\\\\', '\\(', '\\)', ''], $text);
    }
}
