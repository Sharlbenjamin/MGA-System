<x-filament-panels::page>
    <div class="space-y-6">
        {{-- File Selection Form --}}
        <div class="bg-gradient-to-r from-blue-50 to-indigo-50 dark:from-blue-950 dark:to-indigo-950 shadow-lg ring-1 ring-blue-200/50 dark:ring-blue-800/50 rounded-xl border border-blue-200 dark:border-blue-800">
            <div class="px-6 py-4">
                <h3 class="text-lg font-semibold text-blue-900 dark:text-blue-100">📋 File Selection</h3>
                <p class="mt-1 text-sm text-blue-700 dark:text-blue-300">Choose an MGA file to view provider branches with distance calculations</p>
            </div>
            <form wire:submit.prevent="submit" class="space-y-6 p-6">
                {{ $this->form }}
            </form>
        </div>

        {{-- Provider Branches Table --}}
        <div class="bg-gradient-to-r from-slate-50 to-gray-50 dark:from-slate-950 dark:to-gray-950 shadow-xl ring-1 ring-slate-200/50 dark:ring-slate-800/50 rounded-xl border border-slate-200 dark:border-slate-800">
            <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-700 bg-gradient-to-r from-slate-100 to-gray-100 dark:from-slate-900 dark:to-gray-900 rounded-t-xl">
                <h3 class="text-lg font-bold text-slate-900 dark:text-slate-100">🏥 Provider Branches</h3>
                <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">
                    Available provider branches with contact information, services, and real-time distance calculations.
                    @if(!$this->selectedFile)
                        <strong>Select a file above to see distance calculations and costs.</strong>
                    @endif
                </p>
            </div>
            <div class="overflow-hidden">
                {{ $this->table }}
            </div>
        </div>

        {{-- Email Configuration Info --}}
        @if($this->selectedFile)
        <div class="bg-gradient-to-r from-green-50 to-emerald-50 dark:from-green-950 dark:to-emerald-950 border-2 border-green-200 dark:border-green-800 rounded-xl p-4 shadow-lg">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-10 h-10 bg-green-500 rounded-full flex items-center justify-center">
                        <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 4.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                        </svg>
                    </div>
                </div>
                <div class="ml-4 flex-1">
                    <h3 class="text-lg font-bold text-green-900 dark:text-green-100">
                        📧 Ready to Send Appointment Requests
                    </h3>
                    <p class="mt-1 text-sm text-green-700 dark:text-green-300">
                        Use the <strong>"Configure Email Recipients"</strong> button above to add custom emails, then <strong>"Send to All Branches"</strong> or use bulk actions on selected branches in the table.
                    </p>
                </div>
            </div>
        </div>
        @endif
    </div>

    @script
    <script>
        function showPhoneNumber(branchId, branchName, phoneNumber) {
            // Use Livewire to trigger the notification
            $wire.call('showPhoneNotification', branchId);
        }
    </script>
    @endscript
</x-filament-panels::page>