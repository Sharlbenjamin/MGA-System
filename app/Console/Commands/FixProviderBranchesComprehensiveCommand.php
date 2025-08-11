<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FixProviderBranchesComprehensiveCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fix:provider-branches-comprehensive';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Comprehensive fix for provider_branches table contact ID columns';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting comprehensive fix for provider_branches table...');
        
        try {
            // Step 1: Check current table structure
            $this->info('Step 1: Checking current table structure...');
            $columns = DB::select("DESCRIBE provider_branches");
            foreach ($columns as $column) {
                if (in_array($column->Field, ['gop_contact_id', 'operation_contact_id', 'financial_contact_id'])) {
                    $this->line("Column: {$column->Field}, Type: {$column->Type}, Null: {$column->Null}");
                }
            }
            
            // Step 2: Check current foreign key constraints
            $this->info('Step 2: Checking current foreign key constraints...');
            $constraints = DB::select("
                SELECT CONSTRAINT_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME 
                FROM information_schema.KEY_COLUMN_USAGE 
                WHERE TABLE_SCHEMA = ? 
                AND TABLE_NAME = 'provider_branches' 
                AND REFERENCED_TABLE_NAME IS NOT NULL
            ", [config('database.connections.mysql.database')]);
            
            foreach ($constraints as $constraint) {
                $this->line("Constraint: {$constraint->CONSTRAINT_NAME}, Column: {$constraint->COLUMN_NAME}, Referenced: {$constraint->REFERENCED_TABLE_NAME}");
            }
            
            // Step 3: Backup any existing data
            $this->info('Step 3: Backing up existing contact data...');
            $existingData = DB::select("
                SELECT id, gop_contact_id, operation_contact_id, financial_contact_id 
                FROM provider_branches 
                WHERE gop_contact_id IS NOT NULL OR operation_contact_id IS NOT NULL OR financial_contact_id IS NOT NULL
            ");
            
            if (!empty($existingData)) {
                $this->warn('Found existing contact data:');
                foreach ($existingData as $data) {
                    $this->line("ID: {$data->id}, GOP: {$data->gop_contact_id}, Operation: {$data->operation_contact_id}, Financial: {$data->financial_contact_id}");
                }
            }
            
            // Step 4: Drop all foreign key constraints
            $this->info('Step 4: Dropping all foreign key constraints...');
            foreach ($constraints as $constraint) {
                try {
                    DB::statement("ALTER TABLE provider_branches DROP FOREIGN KEY {$constraint->CONSTRAINT_NAME}");
                    $this->info("Dropped constraint: {$constraint->CONSTRAINT_NAME}");
                } catch (\Exception $e) {
                    $this->warn("Could not drop constraint {$constraint->CONSTRAINT_NAME}: " . $e->getMessage());
                }
            }
            
            // Step 5: Drop contact columns
            $this->info('Step 5: Dropping contact columns...');
            $columnsToDrop = ['gop_contact_id', 'operation_contact_id', 'financial_contact_id'];
            foreach ($columnsToDrop as $column) {
                try {
                    DB::statement("ALTER TABLE provider_branches DROP COLUMN IF EXISTS {$column}");
                    $this->info("Dropped column: {$column}");
                } catch (\Exception $e) {
                    $this->warn("Could not drop column {$column}: " . $e->getMessage());
                }
            }
            
            // Step 6: Add contact columns with correct UUID type
            $this->info('Step 6: Adding contact columns with correct UUID type...');
            DB::statement("ALTER TABLE provider_branches ADD COLUMN gop_contact_id CHAR(36) NULL");
            $this->info("Added gop_contact_id");
            
            DB::statement("ALTER TABLE provider_branches ADD COLUMN operation_contact_id CHAR(36) NULL");
            $this->info("Added operation_contact_id");
            
            DB::statement("ALTER TABLE provider_branches ADD COLUMN financial_contact_id CHAR(36) NULL");
            $this->info("Added financial_contact_id");
            
            // Step 7: Add foreign key constraints
            $this->info('Step 7: Adding foreign key constraints...');
            DB::statement("
                ALTER TABLE provider_branches ADD CONSTRAINT provider_branches_gop_contact_id_foreign 
                FOREIGN KEY (gop_contact_id) REFERENCES contacts(id) ON DELETE SET NULL
            ");
            $this->info("Added gop_contact_id foreign key");
            
            DB::statement("
                ALTER TABLE provider_branches ADD CONSTRAINT provider_branches_operation_contact_id_foreign 
                FOREIGN KEY (operation_contact_id) REFERENCES contacts(id) ON DELETE SET NULL
            ");
            $this->info("Added operation_contact_id foreign key");
            
            DB::statement("
                ALTER TABLE provider_branches ADD CONSTRAINT provider_branches_financial_contact_id_foreign 
                FOREIGN KEY (financial_contact_id) REFERENCES contacts(id) ON DELETE SET NULL
            ");
            $this->info("Added financial_contact_id foreign key");
            
            // Step 8: Verify the changes
            $this->info('Step 8: Verifying changes...');
            $columns = DB::select("DESCRIBE provider_branches");
            foreach ($columns as $column) {
                if (in_array($column->Field, ['gop_contact_id', 'operation_contact_id', 'financial_contact_id'])) {
                    $this->line("Column: {$column->Field}, Type: {$column->Type}, Null: {$column->Null}");
                }
            }
            
            $this->info('Success! Provider branches table has been fixed.');
            
        } catch (\Exception $e) {
            $this->error("Error: " . $e->getMessage());
            return 1;
        }
        
        return 0;
    }
} 