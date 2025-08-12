<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ClearFilamentCache extends Command
{
    protected $signature = 'clear:filament-cache';
    protected $description = 'Clear all caches that might affect Filament display';

    public function handle()
    {
        $this->info('Clearing all caches...');
        
        // Clear Laravel caches
        $this->call('cache:clear');
        $this->call('config:clear');
        $this->call('route:clear');
        $this->call('view:clear');
        
        // Clear Filament specific caches
        $this->call('filament:cache-components');
        $this->call('filament:cache-forms');
        $this->call('filament:cache-tables');
        
        // Clear any application caches
        $this->call('optimize:clear');
        
        $this->info('All caches cleared successfully!');
        $this->info('Please refresh your browser and try the grouping again.');
    }
}
