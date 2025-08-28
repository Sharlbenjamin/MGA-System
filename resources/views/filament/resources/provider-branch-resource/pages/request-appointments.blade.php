<x-filament-panels::page>
    <div class="space-y-6 max-w-7xl mx-auto">
        <!-- Header with File Info -->
        {{ $this->infolist }}



        <!-- Custom Emails Section -->
        <div class="bg-white p-4 rounded-lg border shadow-sm">
            <h3 class="text-lg font-semibold mb-4 text-gray-800">Custom Email Addresses</h3>
            <div class="space-y-2">
                @foreach($this->customEmails as $index => $emailData)
                    <div class="flex gap-2 items-center">
                        <input type="email" wire:model="customEmails.{{ $index }}.email" placeholder="Enter custom email address" 
                               class="flex-1 px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <button type="button" wire:click="removeCustomEmail({{ $index }})" 
                                class="filament-button filament-button-size-sm inline-flex items-center justify-center py-2 px-4 font-medium rounded-lg border transition-colors focus:outline-none focus:ring-offset-2 focus:ring-2 disabled:opacity-50 focus:z-10 filament-page-button-action bg-danger-600 hover:bg-danger-500 focus:bg-danger-700 focus:ring-danger-500 text-white border-transparent">
                            Remove
                        </button>
                    </div>
                @endforeach
                <div class="flex gap-2">
                    <button type="button" wire:click="addCustomEmail" 
                            class="filament-button filament-button-size-sm inline-flex items-center justify-center py-2 px-4 font-medium rounded-lg border transition-colors focus:outline-none focus:ring-offset-2 focus:ring-2 disabled:opacity-50 focus:z-10 filament-page-button-action bg-primary-600 hover:bg-primary-500 focus:bg-primary-700 focus:ring-primary-500 text-white border-transparent">
                        Add Email
                    </button>
                </div>
                
                <!-- Send Email Button -->
                @if(count($this->customEmails) > 0)
                    <div class="mt-4 pt-4 border-t border-gray-200">
                        <button type="button" wire:click="sendRequests" 
                                style="background-color: #059669; color: white; border: none; padding: 8px 24px; border-radius: 8px; font-weight: 500; cursor: pointer; transition: background-color 0.2s; box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1); display: inline-flex; align-items: center; justify-content: center;"
                                onmouseover="this.style.backgroundColor='#047857'"
                                onmouseout="this.style.backgroundColor='#059669'">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 4.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                            </svg>
                            Send Custom Emails
                        </button>
                    </div>
                @endif
            </div>
        </div>

        <!-- Modern Filament Table -->
        {{ $this->table }}
    </div>


</x-filament-panels::page>
