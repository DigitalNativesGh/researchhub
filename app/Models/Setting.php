<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Platform settings managed from the admin dashboard. Values are stored as strings and cast by the
 * definitions below; settings with a "config" key override that config value on every request.
 */
class Setting extends Model
{
    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['key', 'value'];

    private const CACHE_KEY = 'platform-settings';

    /** key => [label, type, help, config key|null, rules] */
    public const DEFINITIONS = [
        'registration_open' => ['Allow new registrations', 'bool', 'When off, the sign-up page is closed and only existing accounts can sign in.', null, ['boolean']],
        'announcement' => ['Announcement banner', 'text', 'Shown at the top of every signed-in page. Leave empty to hide it.', null, ['nullable', 'string', 'max:500']],
        'announcement_level' => ['Banner style', 'select:info,warning', 'Use "warning" for outages or maintenance.', null, ['required', 'in:info,warning']],
        'price_undergraduate' => ['Price: Undergraduate Research', 'decimal', 'Charged once per project, in the payment currency. 0 makes it free.', 'researchhub.payments.prices.undergraduate', ['required', 'numeric', 'min:0', 'max:1000000']],
        'price_mba' => ['Price: MBA Research', 'decimal', 'Charged once per project, in the payment currency. 0 makes it free.', 'researchhub.payments.prices.mba', ['required', 'numeric', 'min:0', 'max:1000000']],
        'price_mphil' => ['Price: MPhil Research', 'decimal', 'Charged once per project, in the payment currency. 0 makes it free.', 'researchhub.payments.prices.mphil', ['required', 'numeric', 'min:0', 'max:1000000']],
        'price_phd' => ['Price: PhD Thesis', 'decimal', 'Charged once per project, in the payment currency. 0 makes it free.', 'researchhub.payments.prices.phd', ['required', 'numeric', 'min:0', 'max:1000000']],
        'title_suggestions_per_hour' => ['Title suggestion requests per researcher per hour', 'int', 'The only AI feature. There is also a fixed limit of 5 requests a minute.', 'researchhub.rate_limits.title_suggestions_per_hour', ['required', 'integer', 'min:1', 'max:200']],
        'paper_max_count' => ['Maximum files per project', 'int', 'Source papers plus guideline files; uploads beyond this are refused.', 'researchhub.uploads.paper_max_count', ['required', 'integer', 'min:1', 'max:200']],
        'min_sources' => ['Minimum source papers before submitting', 'int', 'Researchers cannot submit a project with fewer source papers.', 'researchhub.submission.min_sources', ['required', 'integer', 'min:0', 'max:50']],
    ];

    private const DEFAULTS = ['registration_open' => true, 'announcement' => null, 'announcement_level' => 'info'];

    /** @return array<string, mixed> every setting, cast, with defaults filled in */
    public static function values(): array
    {
        $stored = Cache::rememberForever(self::CACHE_KEY, fn () => static::query()->pluck('value', 'key')->all());

        $values = [];
        foreach (self::DEFINITIONS as $key => [, $type, , $config]) {
            $default = $config ? config($config) : (self::DEFAULTS[$key] ?? null);
            $values[$key] = array_key_exists($key, $stored) ? self::cast($stored[$key], $type) : $default;
        }

        return $values;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return self::values()[$key] ?? $default;
    }

    /** @param array<string, mixed> $values */
    public static function putMany(array $values): void
    {
        foreach ($values as $key => $value) {
            if (isset(self::DEFINITIONS[$key])) {
                static::updateOrCreate(['key' => $key], ['value' => is_bool($value) ? ($value ? '1' : '0') : $value]);
            }
        }

        Cache::forget(self::CACHE_KEY);
        self::applyToConfig();
    }

    /** Push overrides into config so the rest of the app keeps reading config(). */
    public static function applyToConfig(): void
    {
        $stored = Cache::rememberForever(self::CACHE_KEY, fn () => static::query()->pluck('value', 'key')->all());

        foreach (self::DEFINITIONS as $key => [, $type, , $config]) {
            if ($config && array_key_exists($key, $stored) && $stored[$key] !== null) {
                config([$config => self::cast($stored[$key], $type)]);
            }
        }
    }

    private static function cast(?string $value, string $type): mixed
    {
        return match ($type) {
            'bool' => filter_var($value, FILTER_VALIDATE_BOOL),
            'int' => (int) $value,
            'decimal' => round((float) $value, 2),
            default => $value,
        };
    }
}
