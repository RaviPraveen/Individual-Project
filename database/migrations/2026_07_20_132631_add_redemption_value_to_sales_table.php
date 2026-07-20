<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            // Stores the actual Rs value redeemed at time of sale, so a
            // later return's refund calc never depends on the current
            // (possibly since-changed) BillingSetting redeem rate.
            $table->decimal('redemption_value', 12, 2)->default(0)->after('points_redeemed');
        });

        // Best-effort backfill for pre-existing rows using the redeem value
        // in effect before this settings page existed (1 point = Rs 1).
        DB::table('sales')
            ->where('points_redeemed', '>', 0)
            ->update(['redemption_value' => DB::raw('points_redeemed * 1')]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn('redemption_value');
        });
    }
};
