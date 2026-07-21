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
        Schema::table('promotions', function (Blueprint $table) {
            // Holds the latest AI-generated attempt awaiting admin review —
            // separate from poster_path (the LIVE poster) so nothing goes
            // live until Approve is clicked, per the spec's "nothing goes
            // live until Admin approves" requirement.
            $table->string('pending_poster_path')->nullable()->after('ai_generations');
            $table->boolean('pending_poster_used_ai')->default(false)->after('pending_poster_path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('promotions', function (Blueprint $table) {
            $table->dropColumn(['pending_poster_path', 'pending_poster_used_ai']);
        });
    }
};
