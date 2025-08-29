<x-filament-panels::page>
    {{-- File Information Cards --}}
    <div class="mb-6">
        <div class="grid grid-cols-1 gap-6">
            {{-- File Info Card --}}
            <x-filament::card class="bg-gradient-to-r from-blue-50 to-indigo-50 dark:from-blue-900/20 dark:to-indigo-900/20 border-l-4 border-blue-500">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-xl font-bold text-blue-900 dark:text-blue-100">
                        📋 File Information
                    </h3>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                    <div class="bg-white dark:bg-gray-800 p-4 rounded-lg border border-gray-200 dark:border-gray-700 shadow-sm">
                        <label class="text-xs font-semibold text-gray-600 dark:text-gray-400 uppercase tracking-wide">File Reference</label>
                        <p class="text-lg font-bold text-amber-600 dark:text-amber-400 mt-1">
                            {{ $this->file->mga_reference }}
                        </p>
                    </div>
                    
                    <div class="bg-white dark:bg-gray-800 p-4 rounded-lg border border-gray-200 dark:border-gray-700 shadow-sm">
                        <label class="text-xs font-semibold text-gray-600 dark:text-gray-400 uppercase tracking-wide">Patient Name</label>
                        <p class="text-lg font-bold text-red-600 dark:text-red-400 mt-1">
                            {{ $this->file->patient->name ?? 'N/A' }}
                        </p>
                    </div>
                    
                    <div class="bg-white dark:bg-gray-800 p-4 rounded-lg border border-gray-200 dark:border-gray-700 shadow-sm">
                        <label class="text-xs font-semibold text-gray-600 dark:text-gray-400 uppercase tracking-wide">Service Type</label>
                        <p class="text-lg font-bold text-blue-600 dark:text-blue-400 mt-1">
                            {{ $this->file->serviceType->name ?? 'N/A' }}
                        </p>
                    </div>
                    
                    <div class="bg-white dark:bg-gray-800 p-4 rounded-lg border border-gray-200 dark:border-gray-700 shadow-sm">
                        <label class="text-xs font-semibold text-gray-600 dark:text-gray-400 uppercase tracking-wide">Country</label>
                        <p class="text-lg font-bold text-green-600 dark:text-green-400 mt-1">
                            {{ $this->file->country->name ?? 'N/A' }}
                        </p>
                    </div>
                    
                    <div class="bg-white dark:bg-gray-800 p-4 rounded-lg border border-gray-200 dark:border-gray-700 shadow-sm">
                        <label class="text-xs font-semibold text-gray-600 dark:text-gray-400 uppercase tracking-wide">City</label>
                        <p class="text-lg font-bold text-purple-600 dark:text-purple-400 mt-1">
                            {{ $this->file->city->name ?? 'N/A' }}
                        </p>
                    </div>
                    
                    <div class="bg-white dark:bg-gray-800 p-4 rounded-lg border border-gray-200 dark:border-gray-700 shadow-sm">
                        <label class="text-xs font-semibold text-gray-600 dark:text-gray-400 uppercase tracking-wide">Address</label>
                        <p class="text-lg font-bold text-gray-700 dark:text-gray-300 mt-1">
                            {{ $this->file->address ?? 'N/A' }}
                        </p>
                    </div>
                    
                    <div class="col-span-1 md:col-span-2 lg:col-span-4 bg-white dark:bg-gray-800 p-4 rounded-lg border border-gray-200 dark:border-gray-700 shadow-sm">
                        <label class="text-xs font-semibold text-gray-600 dark:text-gray-400 uppercase tracking-wide">Symptoms</label>
                        <p class="text-base font-medium text-gray-700 dark:text-gray-300 mt-1">
                            {{ $this->file->symptoms ?? 'N/A' }}
                        </p>
                    </div>
                    
                    <div class="bg-white dark:bg-gray-800 p-4 rounded-lg border border-gray-200 dark:border-gray-700 shadow-sm">
                        <label class="text-xs font-semibold text-gray-600 dark:text-gray-400 uppercase tracking-wide">Service Date</label>
                        <p class="text-lg font-bold text-indigo-600 dark:text-indigo-400 mt-1">
                            {{ $this->file->service_date?->format('d/m/Y') ?? 'N/A' }}
                        </p>
                    </div>
                    
                    <div class="bg-white dark:bg-gray-800 p-4 rounded-lg border border-gray-200 dark:border-gray-700 shadow-sm">
                        <label class="text-xs font-semibold text-gray-600 dark:text-gray-400 uppercase tracking-wide">Service Time</label>
                        <p class="text-lg font-bold text-teal-600 dark:text-teal-400 mt-1">
                            {{ $this->file->service_time ?? 'N/A' }}
                        </p>
                    </div>
                </div>
            </x-filament::card>
            
            {{-- Custom Email Card --}}
            <x-filament::card class="bg-gradient-to-r from-green-50 to-emerald-50 dark:from-green-900/20 dark:to-emerald-900/20 border-l-4 border-green-500">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-xl font-bold text-green-900 dark:text-green-100">
                        📧 Custom Email Addresses
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
                            <x-filament::input.wrapper class="w-1/2">
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
                        
                        {{-- Send Custom Email Request Button --}}
                        <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                            <x-filament::button 
                                wire:click="sendCustomEmailRequest"
                                size="lg"
                                class="w-full bg-gradient-to-r from-purple-600 to-pink-600 hover:from-purple-700 hover:to-pink-700 text-white font-semibold py-3 px-6 rounded-lg shadow-lg hover:shadow-xl transition-all duration-200"
                            >
                                <x-heroicon-o-paper-airplane class="w-5 h-5 mr-2" />
                                Send Custom Email Request
                            </x-filament::button>
                        </div>
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
