<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('receipt_settings', function (Blueprint $table) {
            $table->dropColumn(['show_qr_code', 'show_barcode']);
        });
    }

    public function down(): void
    {
        Schema::table('receipt_settings', function (Blueprint $table) {
            $table->boolean('show_qr_code')->default(false);
            $table->boolean('show_barcode')->default(false);
        });
    }
};
