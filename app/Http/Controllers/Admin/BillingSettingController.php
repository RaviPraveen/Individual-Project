<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BillingSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BillingSettingController extends Controller
{
    public function edit(): View
    {
        return view('admin.billing-settings.edit', [
            'settings' => BillingSetting::current(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'points_earn_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'points_redeem_value' => ['required', 'numeric', 'min:0'],
            'bag_fee' => ['required', 'numeric', 'min:0'],
        ]);

        BillingSetting::current()->update($validated);

        return redirect()->route('admin.billing-settings.edit')->with('success', 'Billing settings saved.');
    }
}
