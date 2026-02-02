@php
    use App\Helpers\FileCompactViewHelper;

    $summaryText = '';
    $compactTasks = [];
    $record = null;

    $stateOrEntry = isset($getState) && is_callable($getState) ? $getState() : ($state ?? $compact_content ?? null);

    if (is_array($stateOrEntry) && isset($stateOrEntry['record'])) {
        $record = $stateOrEntry['record'];
        $summaryText = $stateOrEntry['summaryText'] ?? '';
        $compactTasks = $stateOrEntry['compactTasks'] ?? [];
    } elseif (isset($record) && is_object($record) && !($record instanceof \Closure) && method_exists($record, 'getKey')) {
        $summaryText = FileCompactViewHelper::formatCaseInfo($record);
        $compactTasks = FileCompactViewHelper::getCompactTasks($record);
    } elseif (is_object($stateOrEntry) && !($stateOrEntry instanceof \Closure) && method_exists($stateOrEntry, 'getKey')) {
        $record = $stateOrEntry;
        $summaryText = FileCompactViewHelper::formatCaseInfo($record);
        $compactTasks = FileCompactViewHelper::getCompactTasks($record);
    }

    if (!$record || !is_object($record) || $record instanceof \Closure || !method_exists($record, 'getKey')) {
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
<div class="fi-section w-full max-w-none min-w-0 rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10">
    {{-- 3 columns: Patient+Client | Case Details | Medical Summary --}}
    <div class="grid w-full min-w-0 grid-cols-1 gap-4 sm:grid-cols-3 md:gap-6">
        {{-- Column 1: Patient + Client --}}
        <div class="min-w-0 sm:min-w-[200px]">
            <div class="fi-section-content rounded-lg border border-gray-200 bg-gray-50/50 p-4 dark:border-white/10 dark:bg-gray-500/5">
                <h3 class="mb-3 text-base font-semibold text-gray-900 dark:text-white">Patient & Client</h3>
                <dl class="space-y-2 text-sm">
                    @if($patient && $patient->name)
                        <div class="flex flex-nowrap gap-x-2"><dt class="shrink-0 font-medium text-gray-500 dark:text-gray-400">Patient:</dt><dd class="min-w-0 font-semibold text-gray-950 dark:text-white">{{ $patient->name }}</dd></div>
                    @endif
                    @if($patient && $patient->dob)
                        <div class="flex flex-nowrap gap-x-2"><dt class="shrink-0 font-medium text-gray-500 dark:text-gray-400">DOB:</dt><dd class="min-w-0">{{ \Carbon\Carbon::parse($patient->dob)->format('d/m/Y') }}</dd></div>
                    @endif
                    @if($patient && $patient->gender)
                        <div class="flex flex-nowrap gap-x-2"><dt class="shrink-0 font-medium text-gray-500 dark:text-gray-400">Gender:</dt><dd class="min-w-0">{{ $patient->gender }}</dd></div>
                    @endif
                    @if($record->phone)
                        <div class="flex flex-nowrap gap-x-2"><dt class="shrink-0 font-medium text-gray-500 dark:text-gray-400">Phone:</dt><dd class="min-w-0">{{ $record->phone }}</dd></div>
                    @endif
                    @if($record->email)
                        <div class="flex flex-nowrap gap-x-2"><dt class="shrink-0 font-medium text-gray-500 dark:text-gray-400">Email:</dt><dd class="min-w-0">{{ $record->email }}</dd></div>
                    @endif
                </dl>
                <hr class="my-3 border-gray-200 dark:border-white/10" />
                <dl class="space-y-2 text-sm">
                    <div class="flex flex-nowrap gap-x-2"><dt class="shrink-0 font-medium text-gray-500 dark:text-gray-400">Client:</dt><dd class="min-w-0 font-semibold">{{ $clientName }}</dd></div>
                    @if($record->client_reference)
                        <div class="flex flex-nowrap gap-x-2"><dt class="shrink-0 font-medium text-gray-500 dark:text-gray-400">Client Ref:</dt><dd class="min-w-0">{{ $record->client_reference }}</dd></div>
                    @endif
                </dl>
            </div>
        </div>
        {{-- Column 2: Case Details --}}
        <div class="min-w-0 sm:min-w-[200px]">
            <div class="fi-section-content rounded-lg border border-gray-200 bg-gray-50/50 p-4 dark:border-white/10 dark:bg-gray-500/5">
                <h3 class="mb-3 text-base font-semibold text-gray-900 dark:text-white">Case Details</h3>
                <dl class="space-y-2 text-sm">
                    @if($record->serviceType)
                        <div class="flex flex-nowrap gap-x-2"><dt class="shrink-0 font-medium text-gray-500 dark:text-gray-400">Service:</dt><dd class="min-w-0">{{ $record->serviceType->name }}</dd></div>
                    @endif
                    @if($record->country)
                        <div class="flex flex-nowrap gap-x-2"><dt class="shrink-0 font-medium text-gray-500 dark:text-gray-400">Country:</dt><dd class="min-w-0">{{ $record->country->name }}</dd></div>
                    @endif
                    @if($record->city)
                        <div class="flex flex-nowrap gap-x-2"><dt class="shrink-0 font-medium text-gray-500 dark:text-gray-400">City:</dt><dd class="min-w-0">{{ $record->city->name }}</dd></div>
                    @endif
                    @if($record->service_date)
                        <div class="flex flex-nowrap gap-x-2"><dt class="shrink-0 font-medium text-gray-500 dark:text-gray-400">Service Date:</dt><dd class="min-w-0">{{ $record->service_date->format('d/m/Y') }}</dd></div>
                    @endif
                    @if($record->service_time)
                        <div class="flex flex-nowrap gap-x-2"><dt class="shrink-0 font-medium text-gray-500 dark:text-gray-400">Service Time:</dt><dd class="min-w-0">{{ \Carbon\Carbon::parse($record->service_time)->format('h:i A') }}</dd></div>
                    @endif
                    <div class="flex flex-nowrap gap-x-2 items-center"><dt class="shrink-0 font-medium text-gray-500 dark:text-gray-400">Status:</dt><dd class="min-w-0 inline-block"><x-filament::badge :color="$statusColor">{{ $record->status }}</x-filament::badge></dd></div>
                    <div class="flex flex-nowrap gap-x-2 items-center"><dt class="shrink-0 font-medium text-gray-500 dark:text-gray-400">Invoice:</dt><dd class="min-w-0 inline-block"><x-filament::badge :color="$invoiceStatus === 'Paid' ? 'success' : ($invoiceStatus === 'No Invoice' ? 'gray' : 'warning')">{{ $invoiceStatus }}</x-filament::badge></dd></div>
                    <div class="flex flex-nowrap gap-x-2 items-center"><dt class="shrink-0 font-medium text-gray-500 dark:text-gray-400">Bill:</dt><dd class="min-w-0 inline-block"><x-filament::badge :color="$billStatus === 'Paid' ? 'success' : ($billStatus === 'No Bill' ? 'gray' : 'warning')">{{ $billStatus }}</x-filament::badge></dd></div>
                </dl>
            </div>
        </div>
        {{-- Column 3: Medical Summary --}}
        <div class="min-w-0 sm:min-w-[200px]">
            <div class="fi-section-content rounded-lg border border-gray-200 bg-gray-50/50 p-4 dark:border-white/10 dark:bg-gray-500/5">
                <h3 class="mb-3 text-base font-semibold text-gray-900 dark:text-white">Medical Summary</h3>
                <dl class="space-y-2 text-sm">
                    @if($record->address)
                        <div class="flex flex-nowrap gap-x-2"><dt class="shrink-0 font-medium text-gray-500 dark:text-gray-400">Address:</dt><dd class="min-w-0 break-words">{{ $truncate($record->address, 80) }}</dd></div>
                    @endif
                    @if($record->symptoms)
                        <div class="flex flex-nowrap gap-x-2"><dt class="shrink-0 font-medium text-gray-500 dark:text-gray-400">Symptoms:</dt><dd class="min-w-0 break-words">{{ $truncate($record->symptoms, 80) }}</dd></div>
                    @endif
                    @if($record->diagnosis)
                        <div class="flex flex-nowrap gap-x-2"><dt class="shrink-0 font-medium text-gray-500 dark:text-gray-400">Diagnosis:</dt><dd class="min-w-0 break-words">{{ $record->diagnosis }}</dd></div>
                    @endif
                    @if($record->google_drive_link)
                        <div class="flex flex-nowrap gap-x-2"><dt class="shrink-0 font-medium text-gray-500 dark:text-gray-400">Drive:</dt><dd class="min-w-0"><a href="{{ str_starts_with($record->google_drive_link, 'http') ? $record->google_drive_link : 'https://' . $record->google_drive_link }}" target="_blank" rel="noopener" class="text-primary-600 hover:underline dark:text-primary-400">Open folder</a></dd></div>
                    @endif
                </dl>
            </div>
        </div>
    </div>

    {{-- Provider Details --}}
    <div class="mt-6 rounded-lg border border-gray-200 bg-gray-50/50 p-4 dark:border-white/10 dark:bg-gray-500/5">
        <h3 class="mb-3 text-base font-semibold text-gray-900 dark:text-white">Provider Details</h3>
        @if($record->providerBranch && $record->providerBranch->provider)
            @php $pb = $record->providerBranch; $prov = $pb->provider; @endphp
            <dl class="space-y-2 text-sm">
                <div class="flex flex-nowrap gap-x-2"><dt class="shrink-0 font-medium text-gray-500 dark:text-gray-400">Provider:</dt><dd class="min-w-0">{{ $prov->name }}</dd></div>
                <div class="flex flex-nowrap gap-x-2"><dt class="shrink-0 font-medium text-gray-500 dark:text-gray-400">Branch:</dt><dd class="min-w-0">{{ $pb->branch_name }}</dd></div>
                @if($pb->address ?? null)<div class="flex flex-nowrap gap-x-2"><dt class="shrink-0 font-medium text-gray-500 dark:text-gray-400">Address:</dt><dd class="min-w-0 break-words">{{ $truncate($pb->address, 60) }}</dd></div>@endif
                @if($pb->city ?? null)<div class="flex flex-nowrap gap-x-2"><dt class="shrink-0 font-medium text-gray-500 dark:text-gray-400">City:</dt><dd class="min-w-0">{{ $pb->city?->name ?? '—' }}</dd></div>@endif
                @if($prov->email ?? null)<div class="flex flex-nowrap gap-x-2"><dt class="shrink-0 font-medium text-gray-500 dark:text-gray-400">Email:</dt><dd class="min-w-0">{{ $prov->email }}</dd></div>@endif
                @if($prov->phone ?? null)<div class="flex flex-nowrap gap-x-2"><dt class="shrink-0 font-medium text-gray-500 dark:text-gray-400">Phone:</dt><dd class="min-w-0">{{ $prov->phone }}</dd></div>@endif
            </dl>
            <div class="mt-3 flex flex-wrap gap-3">
                <a href="{{ route('filament.admin.resources.providers.edit', $prov->id) }}" class="text-sm font-semibold text-primary-600 hover:underline dark:text-primary-400">Open Provider</a>
                <a href="{{ route('filament.admin.resources.provider-branches.edit', $pb->id) }}" class="text-sm font-semibold text-primary-600 hover:underline dark:text-primary-400">Open Branch</a>
            </div>
        @else
            <p class="text-sm text-gray-500 dark:text-gray-400">No provider assigned</p>
        @endif
    </div>

    {{-- Summary section: normal box with 2 columns --}}
    <div class="mt-6 rounded-lg border border-gray-200 bg-gray-50/50 p-4 dark:border-white/10 dark:bg-gray-500/5">
        <h3 class="mb-3 text-base font-semibold text-gray-900 dark:text-white">Summary</h3>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
                <h4 class="mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">Case Summary</h4>
                <ul class="list-inside list-disc space-y-1 text-sm text-gray-600 dark:text-gray-400">
                    @foreach(array_filter(explode("\n", $summaryText)) as $line)
                        <li>{{ $line }}</li>
                    @endforeach
                </ul>
            </div>
            <div class="border-t border-gray-200 pt-4 dark:border-white/10 sm:border-t-0 sm:border-l sm:pl-4 sm:pt-0">
                <h4 class="mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">Tasks</h4>
                <div class="space-y-2">
                    @foreach($compactTasks as $t)
                        <div class="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-gray-200 bg-white px-3 py-2 dark:border-white/10 dark:bg-white/5">
                            <span class="text-sm font-medium">{{ $t['name'] }}</span>
                            <div class="flex items-center gap-2">
                                <span class="inline-block"><x-filament::badge :color="$t['status'] === 'Done' ? 'success' : 'warning'">{{ $t['status'] }}</x-filament::badge></span>
                                <span class="text-xs text-gray-500 dark:text-gray-400">{{ $t['assignee'] }}</span>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
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
