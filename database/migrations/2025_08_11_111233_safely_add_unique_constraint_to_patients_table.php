<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // First, handle any duplicate entries
        $duplicates = DB::table('patients')
            ->select('name', 'client_id', DB::raw('COUNT(*) as count'))
            ->groupBy('name', 'client_id')
            ->having('count', '>', 1)
            ->get();

        foreach ($duplicates as $duplicate) {
            // Get all patients with this name and client_id
            $patients = DB::table('patients')
                ->where('name', $duplicate->name)
                ->where('client_id', $duplicate->client_id)
                ->orderBy('created_at', 'asc')
                ->get();

            // Keep the first one, update the rest with a suffix
            $first = true;
            foreach ($patients as $patient) {
                if ($first) {
                    $first = false;
                    continue; // Keep the first one as is
                }

                // Update the name to make it unique
                $newName = $patient->name . ' (' . $patient->id . ')';
                DB::table('patients')
                    ->where('id', $patient->id)
                    ->update(['name' => $newName]);
            }
        }

        // Now add the unique constraint
        Schema::table('patients', function (Blueprint $table) {
            // Check if the constraint doesn't already exist
            if (!Schema::hasIndex('patients', 'patients_name_client_unique')) {
                $table->unique(['name', 'client_id'], 'patients_name_client_unique');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            if (Schema::hasIndex('patients', 'patients_name_client_unique')) {
                $table->dropUnique('patients_name_client_unique');
            }
        });
    }
};
