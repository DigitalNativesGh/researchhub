<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminLog;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function edit(): View
    {
        return view('admin.settings', ['definitions' => Setting::DEFINITIONS, 'values' => Setting::values()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $request->merge(['registration_open' => $request->boolean('registration_open')]);

        $data = $request->validate(collect(Setting::DEFINITIONS)->map(fn ($d) => $d[4])->all());
        $before = Setting::values();
        Setting::putMany($data);

        $changed = collect($data)->filter(fn ($v, $k) => (string) ($before[$k] ?? '') !== (string) ($v ?? ''))
            ->map(fn ($v, $k) => ['from' => $before[$k] ?? null, 'to' => $v])->all();

        if ($changed) {
            AdminLog::record('settings.updated', 'Updated platform settings: '.implode(', ', array_keys($changed)), null, $changed);
        }

        return back()->with('status', 'Platform settings saved.');
    }
}
