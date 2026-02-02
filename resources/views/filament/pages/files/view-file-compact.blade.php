@php
    $summaryText = '';
    $compactTasks = [];
    if (is_array($state)) {
        $record = $state['record'] ?? null;
        $summaryText = $state['summaryText'] ?? '';
        $compactTasks = $state['compactTasks'] ?? [];
    } elseif ($state instanceof \Closure) {
        $record = isset($record) ? $record : null;
    } elseif (is_object($state)) {
        $record = $state;
    } else {
        $record = isset($record) ? $record : null;
    }
    if (!$record || $record instanceof \Closure) {
        echo '<p class="text-gray-500">No file data.</p>';
        return;
    }
    $patient = $record->patient;
    $clientName = $patient?->client?->company_name ?? '—';
    $latestInvoice = $record->invoices()->latest()->first();
    $invoiceStatus = $latestInvoice ? $latestInvoice->status : 'No Invoice';
    $latestBill = $record->bills()->latest()->first();
    $billStatus = $latestBill ? $latestBill->status : 'No Bill';
    $truncate = fn ($s, $len = 80) => $s ? (strlen($s) > $len ? substr($s, 0, $len) . '…' : $s) : '—';
    $statusColor = match ($record->status ?? '') {
        'Assisted' => 'success',
        'Cancelled', 'Void' => 'danger',
        default => 'warning',
    };
@endphp
<div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10">
    {{-- 12-col grid, 3 columns (4 each) --}}
    <div class="grid grid-cols-1 gap-4 md:grid-cols-12 md:gap-6">
        {{-- Column 1: Patient + Client --}}
        <div class="md:col-span-4">
            <div class="fi-section-content rounded-lg border border-gray-200 bg-gray-50/50 p-4 dark:border-white/10 dark:bg-gray-500/5">
                <h3 class="fi-section-header-heading mb-3 text-sm font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Patient & Client</h3>
                <dl class="space-y-2 text-sm">
                    @if($patient && $patient->name)
                        <div><dt class="font-medium text-gray-500 dark:text-gray-400">Patient</dt><dd class="font-semibold text-gray-950 dark:text-white">{{ $patient->name }}</dd></div>
                    @endif
                    @if($patient && $patient->dob)
                        <div><dt class="font-medium text-gray-500 dark:text-gray-400">DOB</dt><dd>{{ \Carbon\Carbon::parse($patient->dob)->format('d/m/Y') }}</dd></div>
                    @endif
                    @if($patient && $patient->gender)
                        <div><dt class="font-medium text-gray-500 dark:text-gray-400">Gender</dt><dd>{{ $patient->gender }}</dd></div>
                    @endif
                    @if($record->phone)
                        <div><dt class="font-medium text-gray-500 dark:text-gray-400">Phone</dt><dd>{{ $record->phone }}</dd></div>
                    @endif
                    @if($record->email)
                        <div><dt class="font-medium text-gray-500 dark:text-gray-400">Email</dt><dd>{{ $record->email }}</dd></div>
                    @endif
                    <div><dt class="font-medium text-gray-500 dark:text-gray-400">Client</dt><dd class="font-semibold">{{ $clientName }}</dd></div>
                    @if($record->client_reference)
                        <div><dt class="font-medium text-gray-500 dark:text-gray-400">Client Ref</dt><dd>{{ $record->client_reference }}</dd></div>
                    @endif
                </dl>
            </div>
        </div>
        {{-- Column 2: Case Details --}}
        <div class="md:col-span-4">
            <div class="fi-section-content rounded-lg border border-gray-200 bg-gray-50/50 p-4 dark:border-white/10 dark:bg-gray-500/5">
                <h3 class="fi-section-header-heading mb-3 text-sm font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Case Details</h3>
                <dl class="space-y-2 text-sm">
                    @if($record->serviceType)
                        <div><dt class="font-medium text-gray-500 dark:text-gray-400">Service</dt><dd>{{ $record->serviceType->name }}</dd></div>
                    @endif
                    @if($record->country)
                        <div><dt class="font-medium text-gray-500 dark:text-gray-400">Country</dt><dd>{{ $record->country->name }}</dd></div>
                    @endif
                    @if($record->city)
                        <div><dt class="font-medium text-gray-500 dark:text-gray-400">City</dt><dd>{{ $record->city->name }}</dd></div>
                    @endif
                    @if($record->service_date)
                        <div><dt class="font-medium text-gray-500 dark:text-gray-400">Service Date</dt><dd>{{ $record->service_date->format('d/m/Y') }}</dd></div>
                    @endif
                    @if($record->service_time)
                        <div><dt class="font-medium text-gray-500 dark:text-gray-400">Service Time</dt><dd>{{ \Carbon\Carbon::parse($record->service_time)->format('h:i A') }}</dd></div>
                    @endif
                    <div><dt class="font-medium text-gray-500 dark:text-gray-400">Status</dt><dd><x-filament::badge :color="$statusColor">{{ $record->status }}</x-filament::badge></dd></div>
                    <div><dt class="font-medium text-gray-500 dark:text-gray-400">Invoice</dt><dd><x-filament::badge :color="$invoiceStatus === 'Paid' ? 'success' : ($invoiceStatus === 'No Invoice' ? 'gray' : 'warning')">{{ $invoiceStatus }}</x-filament::badge></dd></div>
                    <div><dt class="font-medium text-gray-500 dark:text-gray-400">Bill</dt><dd><x-filament::badge :color="$billStatus === 'Paid' ? 'success' : ($billStatus === 'No Bill' ? 'gray' : 'warning')">{{ $billStatus }}</x-filament::badge></dd></div>
                </dl>
            </div>
        </div>
        {{-- Column 3: Medical Summary --}}
        <div class="md:col-span-4">
            <div class="fi-section-content rounded-lg border border-gray-200 bg-gray-50/50 p-4 dark:border-white/10 dark:bg-gray-500/5">
                <h3 class="fi-section-header-heading mb-3 text-sm font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Medical Summary</h3>
                <dl class="space-y-2 text-sm">
                    @if($record->address)
                        <div><dt class="font-medium text-gray-500 dark:text-gray-400">Address</dt><dd class="break-words">{{ $truncate($record->address, 80) }}</dd></div>
                    @endif
                    @if($record->symptoms)
                        <div><dt class="font-medium text-gray-500 dark:text-gray-400">Symptoms</dt><dd class="break-words">{{ $truncate($record->symptoms, 80) }}</dd></div>
                    @endif
                    @if($record->diagnosis)
                        <div><dt class="font-medium text-gray-500 dark:text-gray-400">Diagnosis</dt><dd class="break-words">{{ $record->diagnosis }}</dd></div>
                    @endif
                    @if($record->google_drive_link)
                        <div><dt class="font-medium text-gray-500 dark:text-gray-400">Drive</dt><dd><a href="{{ str_starts_with($record->google_drive_link, 'http') ? $record->google_drive_link : 'https://' . $record->google_drive_link }}" target="_blank" rel="noopener" class="text-primary-600 hover:underline dark:text-primary-400">Open folder</a></dd></div>
                    @endif
                </dl>
            </div>
        </div>
    </div>

    {{-- Summary section (collapsible, collapsed by default) --}}
    <div class="mt-6" x-data="{ open: false }">
        <button type="button" @click="open = !open" class="fi-section-header-heading flex w-full items-center justify-between rounded-lg border border-gray-200 bg-gray-50/50 px-4 py-3 text-left text-sm font-semibold uppercase tracking-wider text-gray-500 dark:border-white/10 dark:bg-gray-500/5 dark:text-gray-400">
            <span>Summary</span>
            <svg class="h-5 w-5 transition-transform" :class="{ 'rotate-180': open }" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" /></svg>
        </button>
        <div x-show="open" x-transition class="rounded-b-lg border border-t-0 border-gray-200 bg-white dark:border-white/10 dark:bg-white/5">
            <div class="grid grid-cols-1 gap-4 md:grid-cols-12">
                <div class="p-4 md:col-span-6">
                    <h4 class="mb-2 text-sm font-semibold text-gray-700 dark:text-gray-300">Case Summary</h4>
                    <ul class="list-inside list-disc space-y-1 text-sm text-gray-600 dark:text-gray-400">
                        @foreach(array_filter(explode("\n", $summaryText)) as $line)
                            <li>{{ $line }}</li>
                        @endforeach
                    </ul>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <a href="{{ route('filament.admin.resources.files.edit', $record) }}" class="inline-flex items-center justify-center gap-1 rounded-lg px-3 py-2 text-sm font-semibold shadow-sm ring-1 ring-gray-950/10 transition hover:bg-gray-50 dark:ring-white/20 dark:hover:bg-white/5 fi-btn">Edit summary</a>
                        <x-filament::button size="sm" tag="button" wire:click="copySummaryToClipboard" color="gray">Copy for email / WhatsApp</x-filament::button>
                    </div>
                </div>
                <div class="border-t border-gray-200 p-4 dark:border-white/10 md:col-span-6 md:border-t-0 md:border-l">
                    <h4 class="mb-2 text-sm font-semibold text-gray-700 dark:text-gray-300">Tasks</h4>
                    <div class="space-y-2">
                        @foreach($compactTasks as $t)
                            <div class="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-gray-200 bg-gray-50/50 px-3 py-2 dark:border-white/10 dark:bg-gray-500/5">
                                <span class="text-sm font-medium">{{ $t['name'] }}</span>
                                <div class="flex items-center gap-2">
                                    <x-filament::badge :color="$t['status'] === 'Done' ? 'success' : 'warning'">{{ $t['status'] }}</x-filament::badge>
                                    <span class="text-xs text-gray-500 dark:text-gray-400">{{ $t['assignee'] }}</span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Provider Details --}}
    <div class="mt-6 rounded-lg border border-gray-200 bg-gray-50/50 p-4 dark:border-white/10 dark:bg-gray-500/5">
        <h3 class="fi-section-header-heading mb-3 text-sm font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Provider Details</h3>
        @if($record->providerBranch && $record->providerBranch->provider)
            @php $pb = $record->providerBranch; $prov = $pb->provider; @endphp
            <dl class="grid grid-cols-1 gap-2 text-sm sm:grid-cols-2">
                <div><dt class="font-medium text-gray-500 dark:text-gray-400">Provider</dt><dd>{{ $prov->name }}</dd></div>
                <div><dt class="font-medium text-gray-500 dark:text-gray-400">Branch</dt><dd>{{ $pb->branch_name }}</dd></div>
                @if($pb->address ?? null)<div><dt class="font-medium text-gray-500 dark:text-gray-400">Address</dt><dd class="break-words">{{ $truncate($pb->address, 60) }}</dd></div>@endif
                @if($pb->city ?? null)<div><dt class="font-medium text-gray-500 dark:text-gray-400">City</dt><dd>{{ $pb->city?->name ?? '—' }}</dd></div>@endif
                @if($prov->email ?? null)<div><dt class="font-medium text-gray-500 dark:text-gray-400">Email</dt><dd>{{ $prov->email }}</dd></div>@endif
                @if($prov->phone ?? null)<div><dt class="font-medium text-gray-500 dark:text-gray-400">Phone</dt><dd>{{ $prov->phone }}</dd></div>@endif
            </dl>
            <div class="mt-3 flex flex-wrap gap-3">
                <a href="{{ route('filament.admin.resources.providers.edit', $prov->id) }}" class="text-sm font-semibold text-primary-600 hover:underline dark:text-primary-400">Open Provider</a>
                <a href="{{ route('filament.admin.resources.provider-branches.edit', $pb->id) }}" class="text-sm font-semibold text-primary-600 hover:underline dark:text-primary-400">Open Branch</a>
            </div>
        @else
            <p class="text-sm text-gray-500 dark:text-gray-400">No provider assigned</p>
        @endif
    </div>

    {{-- Quick Actions (floating) --}}
    <div class="fixed bottom-6 right-6 z-30" x-data="{ open: false }">
        <div x-show="open" x-transition class="absolute bottom-14 right-0 w-56 rounded-xl border border-gray-200 bg-white shadow-lg ring-1 ring-gray-950/5 dark:border-white/10 dark:bg-gray-900 dark:ring-white/10">
            <div class="p-1">
                <button type="button" wire:click="mountAction('requestAppointment')" class="fi-btn relative grid-flow-col items-center justify-center gap-1.5 rounded-lg px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-white/5 w-full text-left flex items-center gap-2">
                    @svg('heroicon-o-calendar', 'h-5 w-5') Request Appointment
                </button>
                <button type="button" wire:click="mountAction('extractConsent')" class="fi-btn relative grid-flow-col items-center justify-center gap-1.5 rounded-lg px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-white/5 w-full text-left flex items-center gap-2">
                    @svg('heroicon-o-document-text', 'h-5 w-5') Extract Consent
                </button>
                <button type="button" wire:click="mountAction('addComment')" class="fi-btn relative grid-flow-col items-center justify-center gap-1.5 rounded-lg px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-white/5 w-full text-left flex items-center gap-2">
                    @svg('heroicon-o-chat-bubble-left', 'h-5 w-5') Add Comment
                </button>
                <a href="{{ route('filament.admin.resources.files.edit', $record) }}" class="fi-btn relative grid-flow-col items-center justify-center gap-1.5 rounded-lg px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-white/5 w-full text-left flex items-center gap-2">
                    @svg('heroicon-o-building-office-2', 'h-5 w-5') Assign Provider
                </a>
                <button type="button" wire:click="mountAction('assignEmployee')" class="fi-btn relative grid-flow-col items-center justify-center gap-1.5 rounded-lg px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-white/5 w-full text-left flex items-center gap-2">
                    @svg('heroicon-o-user-plus', 'h-5 w-5') Assign Employee
                </button>
            </div>
        </div>
        <button type="button" @click="open = !open" class="fi-btn relative flex h-14 w-14 items-center justify-center rounded-full bg-primary-600 text-white shadow-lg hover:bg-primary-500 dark:bg-primary-500 dark:hover:bg-primary-400" title="Quick actions">
            @svg('heroicon-o-plus', 'h-7 w-7')
        </button>
    </div>
</div>
