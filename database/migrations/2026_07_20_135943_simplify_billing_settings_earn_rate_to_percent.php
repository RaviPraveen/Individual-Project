<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('billing_settings', function (Blueprint $table) {
            $table->decimal('points_earn_percent', 8, 3)->default(1)->after('points_earn_count');
        });

        // Convert the saved earn rate to a percentage.
        DB::table('billing_settings')->get()->each(function ($row) {
            $percent = $row->points_earn_amount > 0
                ? ($row->points_earn_count / $row->points_earn_amount) * 100
                : 1;

            DB::table('billing_settings')->where('id', $row->id)->update(['points_earn_percent' => $percent]);
        });

        Schema::table('billing_settings', function (Blueprint $table) {
            $table->dropColumn(['points_earn_amount', 'points_earn_count']);
        });
    }

    public function down(): void
    {
        Schema::table('billing_settings', function (Blueprint $table) {
            $table->decimal('points_earn_amount', 10, 2)->default(100)->after('id');
            $table->decimal('points_earn_count', 8, 2)->default(1)->after('points_earn_amount');
        });

        DB::table('billing_settings')->update([
            'points_earn_amount' => 100,
            'points_earn_count' => DB::raw('points_earn_percent'),
        ]);

        Schema::table('billing_settings', function (Blueprint $table) {
            $table->dropColumn('points_earn_percent');
        });
    }
};
