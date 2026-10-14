<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('promotions', function (Blueprint $table) {
            // Store the preview separately until an admin approves it.
            $table->string('pending_poster_path')->nullable()->after('ai_generations');
            $table->boolean('pending_poster_used_ai')->default(false)->after('pending_poster_path');
        });
    }

    public function down(): void
    {
        Schema::table('promotions', function (Blueprint $table) {
            $table->dropColumn(['pending_poster_path', 'pending_poster_used_ai']);
        });
    }
};
