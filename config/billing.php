<?php

return [
    // Maximum checkout discount. Change it here; there is no settings field.
    'max_discount_percent' => 15,

    // Default tax rate, overridden by the saved system setting.
    'tax_percent' => 0,

    // Loyalty rates and bag fees are stored in BillingSetting.

    'po_pending_alert_days' => 3,

    'near_expiry_days' => 7,

    // Sales history window for the slow-moving stock report.
    'dead_stock_days' => 30,

    // Products selling this quantity or less are treated as slow-moving.
    'dead_stock_max_qty_sold' => 3,

    'low_margin_threshold_percent' => 10,
];
