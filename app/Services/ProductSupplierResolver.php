<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Resolves the most recent supplier a product was ordered from, based on
 * purchase order history. Informational only — callers still let the admin
 * choose the supplier explicitly wherever this feeds a form.
 */
class ProductSupplierResolver
{
    /**
     * @param  array<int>  $productIds
     * @return array<int, array{id: int, name: string}>
     */
    public function resolve(array $productIds): array
    {
        if (empty($productIds)) {
            return [];
        }

        $rows = DB::table('purchase_order_items')
            ->join('purchase_orders', 'purchase_orders.id', '=', 'purchase_order_items.purchase_order_id')
            ->join('suppliers', 'suppliers.id', '=', 'purchase_orders.supplier_id')
            ->whereIn('purchase_order_items.product_id', $productIds)
            ->orderByDesc('purchase_orders.order_date')
            ->orderByDesc('purchase_orders.id')
            ->get(['purchase_order_items.product_id', 'suppliers.id as supplier_id', 'suppliers.name as supplier_name']);

        $result = [];

        foreach ($rows as $row) {
            if (! isset($result[$row->product_id])) {
                $result[$row->product_id] = ['id' => $row->supplier_id, 'name' => $row->supplier_name];
            }
        }

        return $result;
    }
}
