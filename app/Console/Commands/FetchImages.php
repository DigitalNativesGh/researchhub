<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

/**
 * Downloads the photographs used by the designs (configured in config/researchhub.php) into public/images,
 * so pages no longer hot-link them.
 */
class FetchImages extends Command
{
    protected $signature = 'researchhub:fetch-images {--force : Download again even if the file exists}';

    protected $description = 'Download the design images into public/images';

    public function handle(): int
    {
        File::ensureDirectoryExists(public_path('images'));
        $failed = 0;

        foreach (config('researchhub.images', []) as $key => $image) {
            $target = public_path('images/'.$image['file']);

            if (is_file($target) && ! $this->option('force')) {
                $this->line("  {$key}: already downloaded");

                continue;
            }

            $response = rescue(fn () => Http::timeout(30)->get($image['url']), report: false);

            if (! $response?->successful() || ! str_starts_with((string) $response->header('Content-Type'), 'image/')) {
                $this->error("  {$key}: could not download {$image['url']}");
                $failed++;

                continue;
            }

            File::put($target, $response->body());
            $this->info("  {$key}: saved public/images/{$image['file']}");
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
