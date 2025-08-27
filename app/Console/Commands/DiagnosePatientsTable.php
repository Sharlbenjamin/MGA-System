<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class DiagnosePatientsTable extends Command
{
    protected $signature = 'diagnose:patients-table';
    protected $description = 'Diagnose the current state of the patients table';

    public function handle()
    {
        $this->info('🔍 Diagnosing patients table...');

        // Check if table exists
        if (!Schema::hasTable('patients')) {
            $this->error('❌ Patients table does not exist!');
            return 1;
        }

        $this->info('✅ Patients table exists');

        // Check columns
        $columns = Schema::getColumnListing('patients');
        $this->info('�� Current columns: ' . implode(', ', $columns));

        // Check specific columns
        $hasCountry = in_array('country', $columns);
        $hasCountryId = in_array('country_id', $columns);

        $this->info('Country column: ' . ($hasCountry ? '✅' : '❌'));
        $this->info('Country ID column: ' . ($hasCountryId ? '✅' : '❌'));

        // Check foreign key constraints
        $this->info('🔗 Checking foreign key constraints...');
        
        try {
            $constraints = DB::select("
                SELECT CONSTRAINT_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME 
                FROM information_schema.KEY_COLUMN_USAGE 
                WHERE TABLE_NAME = 'patients' 
                AND REFERENCED_TABLE_NAME IS NOT NULL
            ");
            
            if (empty($constraints)) {
                $this->warn('⚠️  No foreign key constraints found');
            } else {
                foreach ($constraints as $constraint) {
                    $this->info("  - {$constraint->COLUMN_NAME} → {$constraint->REFERENCED_TABLE_NAME}");
                }
            }
        } catch (\Exception $e) {
            $this->warn('Could not check foreign key constraints: ' . $e->getMessage());
        }

        // Check data
        $patientCount = DB::table('patients')->count();
        $this->info("�� Total patients: {$patientCount}");

        if ($patientCount > 0) {
            $samplePatient = DB::table('patients')->first();
            $this->info('📝 Sample patient data:');
            $this->line(json_encode($samplePatient, JSON_PRETTY_PRINT));
        }

        // Check if referenced tables exist
        $this->info('🔍 Checking referenced tables...');
        $referencedTables = ['clients', 'countries'];
        
        foreach ($referencedTables as $table) {
            $exists = Schema::hasTable($table);
            $this->info("  {$table} table: " . ($exists ? '✅' : '❌'));
            
            if ($exists) {
                $count = DB::table($table)->count();
                $this->info("    Records: {$count}");
            }
        }

        // Check for potential issues
        $this->info('⚠️  Potential issues:');
        
        if ($hasCountry && !$hasCountryId) {
            $this->warn('  - Model expects "country_id" but table has "country" column');
        }
        
        if (!$hasCountry && $hasCountryId) {
            $this->warn('  - Model expects "country" but table has "country_id" column');
        }

        return 0;
    }
}
