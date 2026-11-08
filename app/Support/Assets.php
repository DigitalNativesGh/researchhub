<?php

namespace App\Support;

/**
 * Decides how the design's Tailwind CSS is delivered.
 *
 * The design files load Tailwind v3 from the Play CDN with an inline config. In production the same
 * config is compiled with Vite (tailwind.config.js). When no compiled build exists yet (for example
 * right after cloning, before `npm run build`), we fall back to the CDN exactly as the designs do,
 * so pages still render identically.
 */
class Assets
{
    public static function useCdn(): bool
    {
        $mode = config('researchhub.assets.tailwind', 'auto');

        if ($mode === 'cdn') {
            return true;
        }

        if ($mode === 'vite') {
            return false;
        }

        return ! is_file(public_path('hot')) && ! is_file(public_path('build/manifest.json'));
    }

    /**
     * URL for one of the design's images. Images hot-linked by the design files can be downloaded
     * into public/images with `php artisan researchhub:fetch-images`; until then the original URL is used.
     */
    public static function image(string $key): string
    {
        $image = config("researchhub.images.$key");

        if (! $image) {
            return '';
        }

        $local = 'images/'.$image['file'];

        return is_file(public_path($local)) ? asset($local) : $image['url'];
    }
}
