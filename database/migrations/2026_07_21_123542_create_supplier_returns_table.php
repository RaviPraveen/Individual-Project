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
        Schema::create('supplier_returns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained();
            $table->foreignId('created_by')->constrained('users');
            $table->date('return_date');
            $table->enum('status', ['pending', 'completed', 'cancelled'])->default('pending');
            $table->string('reason_summary')->nullable();
            $table->decimal('credit_note_value', 12, 2)->default(0);
            $table->enum('resolution', ['credit', 'replacement', 'refund', 'none'])->default('none');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supplier_returns');
    }
};
