<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_settings', function (Blueprint $table) {
            $table->id();
            // Start with one point per Rs 100 spent.
            $table->decimal('points_earn_amount', 10, 2)->default(100);
            $table->decimal('points_earn_count', 8, 2)->default(1);
            $table->decimal('points_redeem_value', 8, 2)->default(1);
            $table->decimal('bag_fee', 8, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_settings');
    }
};
