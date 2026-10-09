<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Historical compatibility migration:
 * The preceding create_missions_table migration already defines these fields.
 * Preserve existing values and avoid destructive down() operations.
 */
return new class extends Migration
{
    public function up(): void
    {
        $columns = [
            'title', 'category', 'difficulty', 'reward_xp',
            'description', 'deadline', 'status',
        ];

        foreach ($columns as $column) {
            if (! Schema::hasColumn('missions', $column)) {
                throw new \RuntimeException(
                    "Missing missions.{$column}. Restore the baseline schema or create an explicit data-safe repair migration."
                );
            }
        }
    }

    public function down(): void
    {
        // No-op: these columns belong to create_missions_table.
        // Removing them would destroy pre-existing mission data.
    }
};
