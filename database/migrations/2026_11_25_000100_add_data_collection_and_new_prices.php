<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Optional data collection add-on: a researcher can ask, at the start of a project, for the team to collect
 * the data on their behalf. The payment records whether it was included (and its amount covers it), and the
 * project carries the flag so the team knows.
 *
 * Also applies the price list of 2026-11-25 to any prices an administrator had saved under Platform Settings,
 * since saved settings override the config defaults.
 */
return new class extends Migration
{
    private const PRICES = [
        'price_undergraduate' => 200, 'price_mba' => 400, 'price_msc' => 450, 'price_mphil' => 500, 'price_phd' => 1500,
        'price_systematic_review' => 100, 'price_research_paper' => 150,
    ];

    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->boolean('data_collection')->default(false)->after('degree_level');
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->boolean('data_collection')->default(false)->after('degree_level');
        });

        foreach (self::PRICES as $key => $value) {
            DB::table('settings')->where('key', $key)->update(['value' => (string) $value, 'updated_at' => now()]);
        }
        Cache::forget('platform-settings');
    }

    public function down(): void
    {
        Schema::table('payments', fn (Blueprint $table) => $table->dropColumn('data_collection'));
        Schema::table('projects', fn (Blueprint $table) => $table->dropColumn('data_collection'));
    }
};
