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
        if (! $this->indexExists('trips', 'trips_truck_id_idx')) {
            DB::statement('ALTER TABLE trips ADD INDEX trips_truck_id_idx (truck_id)');
        }

        if ($this->indexExists('trips', 'trips_truck_id_is_active_unique')) {
            DB::statement('ALTER TABLE trips DROP INDEX trips_truck_id_is_active_unique');
        }

        DB::statement('ALTER TABLE trips MODIFY is_active TINYINT(1) NULL DEFAULT 1');
        DB::statement('UPDATE trips SET is_active = NULL WHERE is_active = 0');

        if (! $this->indexExists('trips', 'trips_truck_id_is_active_unique')) {
            DB::statement('ALTER TABLE trips ADD UNIQUE KEY trips_truck_id_is_active_unique (truck_id, is_active)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if ($this->indexExists('trips', 'trips_truck_id_is_active_unique')) {
            DB::statement('ALTER TABLE trips DROP INDEX trips_truck_id_is_active_unique');
        }

        DB::statement('UPDATE trips SET is_active = 0 WHERE is_active IS NULL');
        DB::statement('ALTER TABLE trips MODIFY is_active TINYINT(1) NOT NULL DEFAULT 1');

        if (! $this->indexExists('trips', 'trips_truck_id_is_active_unique')) {
            DB::statement('ALTER TABLE trips ADD UNIQUE KEY trips_truck_id_is_active_unique (truck_id, is_active)');
        }
    }

    private function indexExists(string $table, string $index): bool
    {
        $result = DB::selectOne(
            'SELECT COUNT(1) AS total FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?',
            [$table, $index]
        );

        return ((int) ($result->total ?? 0)) > 0;
    }
};
