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
];
