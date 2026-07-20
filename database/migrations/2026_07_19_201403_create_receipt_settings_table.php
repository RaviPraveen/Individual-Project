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
        Schema::create('receipt_settings', function (Blueprint $table) {
            $table->id();

            // Business identity
            $table->string('shop_name')->default('Welcome Foodcity');
            $table->string('branch_name')->nullable();
            $table->text('address')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('website')->nullable();
            $table->string('tax_number')->nullable();
            $table->string('business_reg_number')->nullable();

            // Messages
            $table->string('footer_message')->nullable();
            $table->string('thank_you_message')->default('Thank you for shopping with us!');
            $table->text('return_policy')->nullable();

            // Layout / print format
            $table->enum('paper_size', ['thermal', 'a4'])->default('thermal');
            $table->enum('receipt_width', ['58mm', '80mm'])->default('80mm');
            $table->enum('header_alignment', ['left', 'center', 'right'])->default('center');
            $table->enum('footer_alignment', ['left', 'center', 'right'])->default('center');
            $table->unsignedSmallInteger('receipt_margin')->default(8);
            $table->unsignedSmallInteger('receipt_padding')->default(12);

            // Typography
            $table->string('font_family')->default('sans-serif');
            $table->unsignedTinyInteger('font_size')->default(12);
            $table->enum('font_weight', ['normal', 'medium', 'bold'])->default('normal');

            // Branding / extras
            $table->string('logo_path')->nullable();
            $table->boolean('show_qr_code')->default(false);
            $table->boolean('show_barcode')->default(false);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('receipt_settings');
    }
};
