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
                                class="inline-flex items-center justify-center px-6 py-2 bg-emerald-600 text-white text-sm font-medium rounded-lg hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 transition-colors duration-200 shadow-sm">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 4.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                            </svg>
                            Send Email to Custom Addresses
                        </button>
                    </div>
                @endif
            </div>
        </div>

        <!-- Modern Filament Table -->
        {{ $this->table }}
    </div>

    <!-- Phone Info Modal -->
    @if($selectedBranchForPhone ?? false)
        <div class="fixed inset-0 bg-gray-600 bg-opacity-50 z-50">
            <div class="flex items-center justify-center min-h-screen p-4">
                <div class="bg-white rounded-lg shadow-xl max-w-md w-full p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-semibold text-gray-900">Contact Information</h3>
                        <button type="button" wire:click="$set('selectedBranchForPhone', null)" class="text-gray-400 hover:text-gray-600">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                    @php
                        $phoneInfo = $this->getPhoneInfo($selectedBranchForPhone);
                    @endphp
                    @if($phoneInfo)
                        <div class="space-y-3">
                            <div class="font-medium text-gray-900">{{ $phoneInfo['branch_name'] }}</div>
                            <div class="text-sm text-gray-600 space-y-2">
                                @if($phoneInfo['direct_phone'])
                                    <div><strong>Direct Phone:</strong> {{ $phoneInfo['direct_phone'] }}</div>
                                @endif
                                @if($phoneInfo['operation_contact']['name'])
                                    <div><strong>Operation Contact:</strong> {{ $phoneInfo['operation_contact']['name'] }}</div>
                                    @if($phoneInfo['operation_contact']['phone'])
                                        <div class="ml-4">Phone: {{ $phoneInfo['operation_contact']['phone'] }}</div>
                                    @endif
                                    @if($phoneInfo['operation_contact']['email'])
                                        <div class="ml-4">Email: {{ $phoneInfo['operation_contact']['email'] }}</div>
                                    @endif
                                @endif
                                @if($phoneInfo['gop_contact']['name'])
                                    <div><strong>GOP Contact:</strong> {{ $phoneInfo['gop_contact']['name'] }}</div>
                                    @if($phoneInfo['gop_contact']['phone'])
                                        <div class="ml-4">Phone: {{ $phoneInfo['gop_contact']['phone'] }}</div>
                                    @endif
                                    @if($phoneInfo['gop_contact']['email'])
                                        <div class="ml-4">Email: {{ $phoneInfo['gop_contact']['email'] }}</div>
                                    @endif
                                @endif
                                @if($phoneInfo['financial_contact']['name'])
                                    <div><strong>Financial Contact:</strong> {{ $phoneInfo['financial_contact']['name'] }}</div>
                                    @if($phoneInfo['financial_contact']['phone'])
                                        <div class="ml-4">Phone: {{ $phoneInfo['financial_contact']['phone'] }}</div>
                                    @endif
                                    @if($phoneInfo['financial_contact']['email'])
                                        <div class="ml-4">Email: {{ $phoneInfo['financial_contact']['email'] }}</div>
                                    @endif
                                @endif
                            </div>
                        </div>
                    @else
                        <div class="text-gray-500">Contact information not available.</div>
                    @endif
                    <div class="mt-6 flex justify-end">
                        <button type="button" wire:click="$set('selectedBranchForPhone', null)" 
                                class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-gray-500">
                            Close
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</x-filament-panels::page>
