<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promotions', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->text('description')->nullable();

            // Keep the original product price if the catalogue price changes later.
            $table->decimal('current_price', 10, 2);
            $table->decimal('offer_price', 10, 2);
            $table->decimal('discount_percentage', 5, 2);

            $table->string('poster_path')->nullable();
            $table->string('poster_source')->nullable(); // 'ai' | 'custom'
            $table->json('ai_generations')->nullable(); // history of AI poster attempts

            $table->dateTime('start_date');
            $table->dateTime('end_date');
            $table->unsignedInteger('display_duration')->default(10); // seconds
            $table->string('priority')->default('normal'); // high | normal | low
            $table->string('status')->default('scheduled');
            $table->boolean('is_featured')->default(false);
            $table->string('target_screen')->default('customer_display');
            $table->unsignedInteger('display_order')->default(0);
            $table->unsignedInteger('display_count')->default(0);

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'start_date', 'end_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promotions');
    }
};
