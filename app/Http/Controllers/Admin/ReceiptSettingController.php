<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ReceiptSetting;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class ReceiptSettingController extends Controller
{
    public function edit(): View
    {
        return view('admin.receipt-settings.edit', [
            'settings' => ReceiptSetting::current(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'shop_name' => ['required', 'string', 'max:255'],
            'branch_name' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:1000'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'website' => ['nullable', 'string', 'max:255'],
            'tax_number' => ['nullable', 'string', 'max:100'],
            'business_reg_number' => ['nullable', 'string', 'max:100'],
            'footer_message' => ['nullable', 'string', 'max:255'],
            'thank_you_message' => ['nullable', 'string', 'max:255'],
            'return_policy' => ['nullable', 'string', 'max:2000'],
            'paper_size' => ['required', 'in:thermal,a4'],
            'receipt_width' => ['required', 'in:58mm,80mm'],
            'header_alignment' => ['required', 'in:left,center,right'],
            'footer_alignment' => ['required', 'in:left,center,right'],
            'receipt_margin' => ['required', 'integer', 'min:0', 'max:40'],
            'receipt_padding' => ['required', 'integer', 'min:0', 'max:40'],
            'font_family' => ['required', 'in:sans-serif,serif,monospace'],
            'font_size' => ['required', 'integer', 'min:8', 'max:24'],
            'font_weight' => ['required', 'in:normal,medium,bold'],
        ]);

        ReceiptSetting::current()->update($validated);

        return redirect()->route('admin.receipt-settings.edit')->with('success', 'Receipt settings saved.');
    }

    public function reset(): RedirectResponse
    {
        $settings = ReceiptSetting::current();
        $logo = $settings->logo_path;
        $settings->delete();

        if ($logo) {
            Storage::disk('public')->delete($logo);
        }

        ReceiptSetting::current();

        return redirect()->route('admin.receipt-settings.edit')->with('success', 'Receipt settings reset to defaults.');
    }

    public function uploadLogo(Request $request): JsonResponse
    {
        $request->validate([
            'logo' => ['required', 'image', 'max:2048'],
        ]);

        $settings = ReceiptSetting::current();

        if ($settings->logo_path) {
            Storage::disk('public')->delete($settings->logo_path);
        }

        $path = $request->file('logo')->store('receipt-logos', 'public');
        $settings->update(['logo_path' => $path]);

        return response()->json(['logo_url' => Storage::disk('public')->url($path)]);
    }

    public function removeLogo(): RedirectResponse
    {
        $settings = ReceiptSetting::current();

        if ($settings->logo_path) {
            Storage::disk('public')->delete($settings->logo_path);
            $settings->update(['logo_path' => null]);
        }

        return redirect()->route('admin.receipt-settings.edit')->with('success', 'Logo removed.');
    }

    /**
     * Renders a sample receipt PDF using the currently *saved* settings, so
     * the admin can see exactly what a printed/emailed copy looks like.
     */
    public function pdf(): Response
    {
        $data = [
            'invoice_no' => 'INV-20260101-0001',
            'date' => now()->format('Y-m-d H:i'),
            'cashier_name' => 'Cashier User',
            'customer_name' => 'Walk-in',
            'payment_method' => 'cash',
            'items' => [
                ['name' => 'Rice 5kg', 'qty' => 2, 'price' => 1200.00, 'total' => 2400.00],
                ['name' => 'Milk Powder 400g', 'qty' => 1, 'price' => 650.00, 'total' => 650.00],
                ['name' => 'Sugar 1kg', 'qty' => 3, 'price' => 280.00, 'total' => 840.00],
            ],
            'subtotal' => 3890.00,
            'discount' => 100.00,
            'tax' => 0.00,
            'total' => 3790.00,
            'points_earned' => 37,
        ];

        $pdf = Pdf::loadView('receipts.pdf', ['settings' => ReceiptSetting::current(), 'data' => $data]);

        return $pdf->stream('receipt-preview.pdf');
    }
}
