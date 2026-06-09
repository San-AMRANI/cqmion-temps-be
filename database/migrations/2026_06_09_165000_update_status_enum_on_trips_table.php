<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE trips MODIFY COLUMN status ENUM('STARTED', 'ARRIVED_PORT', 'LEFT_PORT', 'COMPLETED', 'CANCELLED') DEFAULT 'STARTED'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE trips MODIFY COLUMN status ENUM('STARTED', 'ARRIVED_PORT', 'LEFT_PORT', 'COMPLETED') DEFAULT 'STARTED'");
    }
};
