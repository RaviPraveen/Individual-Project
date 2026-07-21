<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\Product;
use App\Models\PurchaseOrder;
use Illuminate\Support\Facades\Cache;

/**
 * Scans for alert-worthy conditions (low stock, stale pending POs, near-expiry
 * stock) and writes a notifications row for anything not already flagged in
 * the last day. The 24h de-dupe window (rather than "skip while any row,
 * read or unread, exists") is what makes mark-as-read actually mean
 * something: an acknowledged alert stays gone until the next day, but a
 * still-unresolved problem does resurface instead of being silenced forever.
 */
class NotificationGenerator
{
    public function generate(): void
    {
        // Cheap external throttle so this doesn't re-run its queries on every
        // single page load — once every 10 minutes is plenty for alerts that
        // are inherently day-scale (stock levels, PO age, expiry dates).
        Cache::remember('notification_generator_last_run', now()->addMinutes(10), function () {
            $this->generateLowStock();
            $this->generatePendingPurchaseOrders();
            $this->generateNearExpiry();

            return true;
        });
    }

    private function generateLowStock(): void
    {
        Product::query()
            ->where('is_active', true)
            ->whereColumn('stock_qty', '<=', 'reorder_level')
            ->each(function (Product $product) {
                $this->createIfNotRecent(
                    'low_stock',
                    "{$product->name} is at or below its reorder level ({$product->stock_qty} left, reorder at {$product->reorder_level}).",
                    route('admin.products.index', ['name' => $product->name])
                );
            });
    }

    private function generatePendingPurchaseOrders(): void
    {
        $days = config('billing.po_pending_alert_days', 3);

        PurchaseOrder::query()
            ->where('status', 'pending')
            ->where('created_at', '<=', now()->subDays($days))
            ->each(function (PurchaseOrder $po) {
                $this->createIfNotRecent(
                    'pending_po',
                    "Purchase order #{$po->id} has been pending for over {$this->daysAgo($po->created_at)} days.",
                    route('admin.purchase-orders.show', $po)
                );
            });
    }

    private function generateNearExpiry(): void
    {
        $days = config('billing.near_expiry_days', 7);

        Product::query()
            ->where('is_active', true)
            ->whereNotNull('expiry_date')
            ->whereDate('expiry_date', '>=', now())
            ->whereDate('expiry_date', '<=', now()->addDays($days))
            ->each(function (Product $product) {
                $this->createIfNotRecent(
                    'near_expiry',
                    "{$product->name} expires on {$product->expiry_date->format('Y-m-d')}.",
                    route('admin.products.index', ['name' => $product->name])
                );
            });
    }

    private function createIfNotRecent(string $type, string $message, string $link): void
    {
        $alreadyFlagged = Notification::where('type', $type)
            ->where('link', $link)
            ->where('created_at', '>=', now()->subDay())
            ->exists();

        if ($alreadyFlagged) {
            return;
        }

        Notification::create([
            'type' => $type,
            'message' => $message,
            'link' => $link,
            'is_read' => false,
        ]);
    }

    private function daysAgo(\Illuminate\Support\Carbon $date): int
    {
        return (int) $date->diffInDays(now());
    }
}
