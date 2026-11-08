<?php

namespace App\Support;

/**
 * The upload size the server can actually accept: the app's own limit, capped by PHP's upload_max_filesize and
 * post_max_size (a file larger than these never reaches Laravel).
 */
class UploadLimits
{
    /** Largest single file, in KB, given the app's own limit for that kind of file. */
    public static function fileKb(int $appKb): int
    {
        return max(1, min($appKb, self::iniKb('upload_max_filesize'), self::iniKb('post_max_size')));
    }

    /** Largest whole request, in KB (PHP's post_max_size). */
    public static function requestKb(): int
    {
        return self::iniKb('post_max_size');
    }

    public static function mb(int $kb): string
    {
        $mb = $kb / 1024;

        return ($mb >= 10 || fmod($mb, 1) == 0.0 ? (string) round($mb) : number_format($mb, 1)).' MB';
    }

    /** "8M" → 8192; 0 or empty means unlimited. */
    private static function iniKb(string $key): int
    {
        $value = trim((string) ini_get($key));
        if ($value === '' || $value === '0' || $value === '-1') {
            return PHP_INT_MAX;
        }

        $number = (float) $value;

        return (int) match (strtolower(substr($value, -1))) {
            'g' => $number * 1024 * 1024,
            'm' => $number * 1024,
            'k' => $number,
            default => $number / 1024,
        };
    }
}
