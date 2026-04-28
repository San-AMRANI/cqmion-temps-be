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
        Schema::table('trucks', function (Blueprint $table): void {
            $table->string('driver_name')->nullable()->after('registration_number');
            $table->unique('driver_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('trucks', function (Blueprint $table): void {
            $table->dropUnique('trucks_driver_name_unique');
            $table->dropColumn('driver_name');
        });
    }
};
