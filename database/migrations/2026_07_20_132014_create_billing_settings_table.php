<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('billing_settings', function (Blueprint $table) {
            $table->id();
            // Earn rate expressed the way a shop admin thinks about it —
            // "spend this much, earn this many points" — rather than an
            // abstract percentage. Defaults (100 / 1) reproduce the previous
            // hardcoded 1% rate so existing behavior doesn't silently change.
            $table->decimal('points_earn_amount', 10, 2)->default(100);
            $table->decimal('points_earn_count', 8, 2)->default(1);
            $table->decimal('points_redeem_value', 8, 2)->default(1);
            $table->decimal('bag_fee', 8, 2)->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('billing_settings');
    }
};
