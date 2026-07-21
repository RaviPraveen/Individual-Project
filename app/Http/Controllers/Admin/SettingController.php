<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingController extends Controller
{
    public function edit(): View
    {
        return view('admin.settings.edit', [
            'taxRate' => Setting::get('tax_rate', config('billing.tax_percent')),
            'currencySymbol' => Setting::get('currency_symbol', 'Rs'),
            'lowStockThresholdDefault' => Setting::get('low_stock_threshold_default', 5),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'tax_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'currency_symbol' => ['required', 'string', 'max:10'],
            'low_stock_threshold_default' => ['required', 'integer', 'min:0'],
        ]);

        Setting::set('tax_rate', (string) $validated['tax_rate']);
        Setting::set('currency_symbol', $validated['currency_symbol']);
        Setting::set('low_stock_threshold_default', (string) $validated['low_stock_threshold_default']);

        return redirect()->route('admin.settings.edit')->with('success', 'Settings saved.');
    }
}
