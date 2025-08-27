<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CheckDatabaseStructure extends Command
{
    protected $signature = 'db:check-structure';
    protected $description = 'Check the database structure of provider_branches table';

    public function handle()
    {
        $this->info('Checking provider_branches table structure...');

        // Check if table exists
        if (!Schema::hasTable('provider_branches')) {
            $this->error('provider_branches table does not exist!');
            return;
        }

        // Get table columns
        $columns = Schema::getColumnListing('provider_branches');
        
        $this->info("\nColumns in provider_branches table:");
        foreach ($columns as $column) {
            $this->line("- $column");
        }

        // Check specific contact fields
        $this->info("\nChecking contact fields:");
        $this->info("Email field exists: " . (in_array('email', $columns) ? 'YES' : 'NO'));
        $this->info("Phone field exists: " . (in_array('phone', $columns) ? 'YES' : 'NO'));
        $this->info("Address field exists: " . (in_array('address', $columns) ? 'YES' : 'NO'));

        // Check contact relationship fields
        $this->info("\nChecking contact relationship fields:");
        $this->info("gop_contact_id exists: " . (in_array('gop_contact_id', $columns) ? 'YES' : 'NO'));
        $this->info("operation_contact_id exists: " . (in_array('operation_contact_id', $columns) ? 'YES' : 'NO'));
        $this->info("financial_contact_id exists: " . (in_array('financial_contact_id', $columns) ? 'YES' : 'NO'));

        // Check old cost fields
        $this->info("\nChecking old cost fields:");
        $this->info("day_cost exists: " . (in_array('day_cost', $columns) ? 'YES' : 'NO'));
        $this->info("night_cost exists: " . (in_array('night_cost', $columns) ? 'YES' : 'NO'));
        $this->info("weekend_cost exists: " . (in_array('weekend_cost', $columns) ? 'YES' : 'NO'));
        $this->info("weekend_night_cost exists: " . (in_array('weekend_night_cost', $columns) ? 'YES' : 'NO'));

        // Sample data
        $this->info("\nSample data from first branch:");
        $sampleBranch = DB::table('provider_branches')->first();
        if ($sampleBranch) {
            $this->info("Branch Name: " . $sampleBranch->branch_name);
            $this->info("Email: " . ($sampleBranch->email ?? 'NULL'));
            $this->info("Phone: " . ($sampleBranch->phone ?? 'NULL'));
            $this->info("Address: " . ($sampleBranch->address ?? 'NULL'));
        }
    }
}
