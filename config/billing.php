<?php

return [
    /*
     * Maximum discount percentage a cashier may apply to a sale at checkout.
     * Adjustable here by the admin; not exposed as a UI setting in this build.
     */
    'max_discount_percent' => 15,

    /*
     * Flat sales tax percentage applied to the subtotal after discount.
     */
    'tax_percent' => 0,

    // Loyalty earn rate, redeem value, and bag fee are admin-editable at
    // runtime — see App\Models\BillingSetting (admin/billing-settings).

    /*
     * A pending purchase order older than this triggers a dashboard notification.
     */
    'po_pending_alert_days' => 3,

    /*
     * Products expiring within this many days are flagged as near-expiry
     * (dashboard notification + the near-expiry report).
     */
    'near_expiry_days' => 7,

    /*
     * Default lookback window (days) for the Dead-Stock / Slow-Moving report.
     */
    'dead_stock_days' => 30,

    /*
     * A product is considered slow-moving/dead stock if its total quantity
     * sold within the lookback window is at or below this threshold
     * (0 included, i.e. zero sales in the period).
     */
    'dead_stock_max_qty_sold' => 3,

    /*
     * Products/categories with a gross margin below this percentage are
     * flagged as low-margin on the Revenue by Product / by Category pages.
     */
    'low_margin_threshold_percent' => 10,
];
