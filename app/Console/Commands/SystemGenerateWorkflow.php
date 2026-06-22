<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;

class SystemGenerateWorkflow extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'system:generate-workflow {--force : Force regeneration even if no changes detected}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate and maintain the comprehensive System Workflow documentation';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🚀 Starting System Workflow Documentation Generator...');
        
        $scriptPath = base_path('scripts/generate_system_workflow.php');
        
        if (!file_exists($scriptPath)) {
            $this->error('❌ Generator script not found at: ' . $scriptPath);
            return 1;
        }

        try {
            // Run the generator script
            $result = Process::run(['php', $scriptPath]);
            
            if ($result->successful()) {
                $this->info('✅ System Workflow documentation generated successfully!');
                $this->line('');
                $this->line('📄 Documentation location: docs/system-workflow/System Workflow.md');
                $this->line('📊 Manifest location: docs/system-workflow/manifest.json');
                $this->line('');
                
                // Show output from the script
                if ($result->output()) {
                    $this->line('Script output:');
                    $this->line($result->output());
                }
                
                return 0;
            } else {
                $this->error('❌ Failed to generate documentation:');
                $this->error($result->errorOutput());
                return 1;
            }
        } catch (\Exception $e) {
            $this->error('❌ Exception occurred: ' . $e->getMessage());
            return 1;
        }
    }
}
