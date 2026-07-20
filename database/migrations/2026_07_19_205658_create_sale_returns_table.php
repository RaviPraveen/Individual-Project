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
        Schema::create('sale_returns', function (Blueprint $table) {
            $table->id();
            $table->string('return_no')->unique();
            $table->foreignId('sale_id')->constrained();
            $table->foreignId('processed_by')->constrained('users');
            $table->text('reason')->nullable();
            $table->enum('refund_method', ['cash', 'card', 'other']);
            $table->decimal('subtotal_refunded', 12, 2)->default(0);
            $table->decimal('discount_refunded', 12, 2)->default(0);
            $table->decimal('tax_refunded', 12, 2)->default(0);
            $table->decimal('total_refunded', 12, 2)->default(0);
            $table->unsignedInteger('points_clawed_back')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sale_returns');
    }
};
