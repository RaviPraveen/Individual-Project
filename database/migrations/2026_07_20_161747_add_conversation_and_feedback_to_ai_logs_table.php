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
        Schema::table('ai_logs', function (Blueprint $table) {
            $table->foreignId('conversation_id')->nullable()->after('user_id')
                ->constrained('ai_conversations')->cascadeOnDelete();
            $table->enum('feedback', ['like', 'dislike'])->nullable()->after('response');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ai_logs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('conversation_id');
            $table->dropColumn('feedback');
        });
    }
};
