<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            // Preserve the redeemed amount for later refunds.
            $table->decimal('redemption_value', 12, 2)->default(0)->after('points_redeemed');
        });

        // Older sales used one point = Rs 1.
        DB::table('sales')
            ->where('points_redeemed', '>', 0)
            ->update(['redemption_value' => DB::raw('points_redeemed * 1')]);
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn('redemption_value');
        });
    }
};
