<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SettingKey;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SettingsRequest;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public function edit(): Response
    {
        return Inertia::render('admin/Settings', [
            'riskAmberDays' => Setting::integer(SettingKey::RiskAmberDays),
            'riskRedDays' => Setting::integer(SettingKey::RiskRedDays),
        ]);
    }

    public function update(SettingsRequest $request): RedirectResponse
    {
        Setting::put(SettingKey::RiskAmberDays, (string) $request->integer('risk_amber_days'));
        Setting::put(SettingKey::RiskRedDays, (string) $request->integer('risk_red_days'));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Settings saved.')]);

        return back();
    }
}
