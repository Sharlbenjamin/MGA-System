<x-filament-panels::page>
    {{-- File Information Cards --}}
    <div class="mb-6">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            {{-- File Info Card --}}
            <x-filament::card>
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">
                        File Information
                    </h3>
                </div>
                
                <div class="grid grid-cols-4 gap-4">
                    <div>
                        <label class="text-sm font-medium text-gray-500 dark:text-gray-400">File Reference</label>
                        <p class="text-sm font-semibold text-warning-600 dark:text-warning-400">
                            {{ $this->file->mga_reference }}
                        </p>
                    </div>
                    
                    <div>
                        <label class="text-sm font-medium text-gray-500 dark:text-gray-400">Patient Name</label>
                        <p class="text-sm font-semibold text-danger-600 dark:text-danger-400">
                            {{ $this->file->patient->name ?? 'N/A' }}
                        </p>
                    </div>
                    
                    <div>
                        <label class="text-sm font-medium text-gray-500 dark:text-gray-400">Service Type</label>
                        <p class="text-sm">{{ $this->file->serviceType->name ?? 'N/A' }}</p>
                    </div>
                    
                    <div>
                        <label class="text-sm font-medium text-gray-500 dark:text-gray-400">Country</label>
                        <p class="text-sm">{{ $this->file->country->name ?? 'N/A' }}</p>
                    </div>
                    
                    <div>
                        <label class="text-sm font-medium text-gray-500 dark:text-gray-400">City</label>
                        <p class="text-sm">{{ $this->file->city->name ?? 'N/A' }}</p>
                    </div>
                    
                    <div>
                        <label class="text-sm font-medium text-gray-500 dark:text-gray-400">Address</label>
                        <p class="text-sm">{{ $this->file->address ?? 'N/A' }}</p>
                    </div>
                    
                    <div class="col-span-4">
                        <label class="text-sm font-medium text-gray-500 dark:text-gray-400">Symptoms</label>
                        <p class="text-sm">{{ $this->file->symptoms ?? 'N/A' }}</p>
                    </div>
                    
                    <div>
                        <label class="text-sm font-medium text-gray-500 dark:text-gray-400">Service Date</label>
                        <p class="text-sm">{{ $this->file->service_date?->format('d/m/Y') ?? 'N/A' }}</p>
                    </div>
                    
                    <div>
                        <label class="text-sm font-medium text-gray-500 dark:text-gray-400">Service Time</label>
                        <p class="text-sm">{{ $this->file->service_time ?? 'N/A' }}</p>
                    </div>
                </div>
            </x-filament::card>
            
            {{-- Custom Email Card --}}
            <x-filament::card>
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">
                        Custom Email Addresses
                    </h3>
                </div>
                
                <div>
                    <label class="text-sm font-medium text-gray-500 dark:text-gray-400">Additional Emails</label>
                    @if(empty($this->customEmails))
                        <p class="text-sm text-gray-500 dark:text-gray-400">No additional emails added</p>
                    @else
                        <div class="mt-2 space-y-2">
                            @foreach($this->customEmails as $email)
                                @if(!empty($email['email']))
                                    <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700">
                                        <span class="text-sm text-info-600 dark:text-info-400 font-medium flex-1 mr-3">{{ $email['email'] }}</span>
                                        <button 
                                            type="button"
                                            wire:click="removeCustomEmail({{ $loop->index }})"
                                            class="text-red-500 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300 p-1 rounded-full hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors"
                                        >
                                            <x-heroicon-o-x-mark class="w-4 h-4" />
                                        </button>
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    @endif
                    
                    {{-- Add Email Form --}}
                    <div class="mt-4">
                        <form wire:submit.prevent="addCustomEmail" class="flex gap-3">
                            <x-filament::input.wrapper class="flex-1">
                                <x-filament::input 
                                    type="email" 
                                    wire:model="newEmail" 
                                    placeholder="Enter email address"
                                    class="w-full"
                                />
                            </x-filament::input.wrapper>
                            <x-filament::button type="submit" size="sm" class="flex-shrink-0">
                                Add Email
                            </x-filament::button>
                        </form>
                    </div>
                </div>
            </x-filament::card>
        </div>
    </div>
    
    {{-- Provider Branches Table --}}
    <div class="mb-6">
        <x-filament::card>
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white">
                    Eligible Provider Branches
                </h3>
                <div class="flex items-center gap-2">
                    <span class="text-sm text-gray-500 dark:text-gray-400">
                        {{ $this->getProviderBranchesQuery()->count() }} branches found
                    </span>
                </div>
            </div>
            
            {{ $this->table }}
        </x-filament::card>
    </div>
</x-filament-panels::page>
