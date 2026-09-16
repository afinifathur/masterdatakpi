<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('md_heat_numbers', function (Blueprint $table) {
            if (!Schema::hasColumn('md_heat_numbers', 'traveler_number')) {
                $table->string('traveler_number', 60)->nullable()->unique()->after('id');
            }
        });

        // Drop composite unique constraint on (heat_number, item_code) to allow multi-KTR per heat+item
        try {
            Schema::table('md_heat_numbers', function (Blueprint $table) {
                $table->dropUnique(['heat_number', 'item_code']);
            });
        } catch (\Throwable $e) {
            // Constraint may have already been dropped or has driver-specific name
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('md_heat_numbers', function (Blueprint $table) {
            if (Schema::hasColumn('md_heat_numbers', 'traveler_number')) {
                $table->dropColumn('traveler_number');
            }
            try {
                $table->unique(['heat_number', 'item_code']);
            } catch (\Throwable $e) {}
        });
    }
};
