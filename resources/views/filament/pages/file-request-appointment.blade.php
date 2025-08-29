<x-filament-panels::page>
    <div class="space-y-6">
        <!-- File Information Card -->
        <x-filament::card>
            <x-filament::card.header>
                <x-filament::card.heading>
                    File Information
                </x-filament::card.heading>
            </x-filament::card.header>

            <x-filament::card.content>
                {{ $this->infolist }}
            </x-filament::card.content>
        </x-filament::card>

        <!-- Custom Email Addresses Card -->
        <x-filament::card>
            <x-filament::card.header>
                <x-filament::card.heading>
                    Additional Email Addresses
                </x-filament::card.heading>
                <x-filament::card.description>
                    Add custom email addresses to include in appointment requests
                </x-filament::card.description>
            </x-filament::card.header>

            <x-filament::card.content>
                {{ $this->form }}
            </x-filament::card.content>
        </x-filament::card>

        <!-- Provider Branches Table -->
        <x-filament::card>
            <x-filament::card.header>
                <x-filament::card.heading>
                    Eligible Provider Branches
                </x-filament::card.heading>
                <x-filament::card.description>
                    Select branches to send appointment requests
                </x-filament::card.description>
            </x-filament::card.header>

            <x-filament::card.content>
                {{ $this->table }}
            </x-filament::card.content>
        </x-filament::card>
    </div>
</x-filament-panels::page>
