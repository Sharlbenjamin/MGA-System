{{-- BACKUP: Old standalone compact content partial. See file-compact-standalone-old.blade.php. --}}
@php
    $patient = $record->patient;
    $clientName = $patient?->client?->company_name ?? '—';
    $latestInvoice = $record->invoices()->latest()->first();
    $invoiceStatus = $latestInvoice ? $latestInvoice->status : 'No Invoice';
    $latestBill = $record->bills()->latest()->first();
    $billStatus = $latestBill ? $latestBill->status : 'No Bill';
    $truncate = fn ($s, $len = 80) => $s ? (strlen($s) > $len ? substr($s, 0, $len) . '…' : $s) : '—';
    $statusColor = match ($record->status ?? '') {
        'Assisted' => 'green',
        'Cancelled', 'Void' => 'red',
        default => 'yellow',
    };
    $badgeClass = fn ($c) => match ($c) {
        'green' => 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400',
        'red' => 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',
        'yellow' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400',
        'gray' => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
        default => 'bg-gray-100 text-gray-800',
    };
@endphp
<div class="rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10">
    <div class="grid grid-cols-1 gap-4 md:grid-cols-12 md:gap-6">
        <div class="md:col-span-4">
            <div class="rounded-lg border border-gray-200 bg-gray-50/50 p-4 dark:border-white/10 dark:bg-gray-500/5">
                <h3 class="mb-3 text-sm font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Patient & Client</h3>
                <dl class="space-y-2 text-sm">
                    @if($patient && $patient->name)<div><dt class="font-medium text-gray-500 dark:text-gray-400">Patient</dt><dd class="font-semibold text-gray-950 dark:text-white">{{ $patient->name }}</dd></div>@endif
                    @if($patient && $patient->dob)<div><dt class="font-medium text-gray-500 dark:text-gray-400">DOB</dt><dd>{{ \Carbon\Carbon::parse($patient->dob)->format('d/m/Y') }}</dd></div>@endif
                    @if($patient && $patient->gender)<div><dt class="font-medium text-gray-500 dark:text-gray-400">Gender</dt><dd>{{ $patient->gender }}</dd></div>@endif
                    @if($record->phone)<div><dt class="font-medium text-gray-500 dark:text-gray-400">Phone</dt><dd>{{ $record->phone }}</dd></div>@endif
                    @if($record->email)<div><dt class="font-medium text-gray-500 dark:text-gray-400">Email</dt><dd>{{ $record->email }}</dd></div>@endif
                    <div><dt class="font-medium text-gray-500 dark:text-gray-400">Client</dt><dd class="font-semibold">{{ $clientName }}</dd></div>
                    @if($record->client_reference)<div><dt class="font-medium text-gray-500 dark:text-gray-400">Client Ref</dt><dd>{{ $record->client_reference }}</dd></div>@endif
                </dl>
            </div>
        </div>
        <div class="md:col-span-4">
            <div class="rounded-lg border border-gray-200 bg-gray-50/50 p-4 dark:border-white/10 dark:bg-gray-500/5">
                <h3 class="mb-3 text-sm font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Case Details</h3>
                <dl class="space-y-2 text-sm">
                    @if($record->serviceType)<div><dt class="font-medium text-gray-500 dark:text-gray-400">Service</dt><dd>{{ $record->serviceType->name }}</dd></div>@endif
                    @if($record->country)<div><dt class="font-medium text-gray-500 dark:text-gray-400">Country</dt><dd>{{ $record->country->name }}</dd></div>@endif
                    @if($record->city)<div><dt class="font-medium text-gray-500 dark:text-gray-400">City</dt><dd>{{ $record->city->name }}</dd></div>@endif
                    @if($record->service_date)<div><dt class="font-medium text-gray-500 dark:text-gray-400">Service Date</dt><dd>{{ $record->service_date->format('d/m/Y') }}</dd></div>@endif
                    @if($record->service_time)<div><dt class="font-medium text-gray-500 dark:text-gray-400">Service Time</dt><dd>{{ \Carbon\Carbon::parse($record->service_time)->format('h:i A') }}</dd></div>@endif
                    <div><dt class="font-medium text-gray-500 dark:text-gray-400">Status</dt><dd><span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium {{ $badgeClass($statusColor) }}">{{ $record->status }}</span></dd></div>
                    <div><dt class="font-medium text-gray-500 dark:text-gray-400">Invoice</dt><dd><span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium {{ $badgeClass($invoiceStatus === 'Paid' ? 'green' : ($invoiceStatus === 'No Invoice' ? 'gray' : 'yellow')) }}">{{ $invoiceStatus }}</span></dd></div>
                    <div><dt class="font-medium text-gray-500 dark:text-gray-400">Bill</dt><dd><span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium {{ $badgeClass($billStatus === 'Paid' ? 'green' : ($billStatus === 'No Bill' ? 'gray' : 'yellow')) }}">{{ $billStatus }}</span></dd></div>
                </dl>
            </div>
        </div>
        <div class="md:col-span-4">
            <div class="rounded-lg border border-gray-200 bg-gray-50/50 p-4 dark:border-white/10 dark:bg-gray-500/5">
                <h3 class="mb-3 text-sm font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Medical Summary</h3>
                <dl class="space-y-2 text-sm">
                    @if($record->address)<div><dt class="font-medium text-gray-500 dark:text-gray-400">Address</dt><dd class="break-words">{{ $truncate($record->address, 80) }}</dd></div>@endif
                    @if($record->symptoms)<div><dt class="font-medium text-gray-500 dark:text-gray-400">Symptoms</dt><dd class="break-words">{{ $truncate($record->symptoms, 80) }}</dd></div>@endif
                    @if($record->diagnosis)<div><dt class="font-medium text-gray-500 dark:text-gray-400">Diagnosis</dt><dd class="break-words">{{ $record->diagnosis }}</dd></div>@endif
                    @if($record->google_drive_link)<div><dt class="font-medium text-gray-500 dark:text-gray-400">Drive</dt><dd><a href="{{ str_starts_with($record->google_drive_link, 'http') ? $record->google_drive_link : 'https://' . $record->google_drive_link }}" target="_blank" rel="noopener" class="text-primary-600 hover:underline dark:text-primary-400">Open folder</a></dd></div>@endif
                </dl>
            </div>
        </div>
    </div>

    <details class="mt-6 group rounded-lg border border-gray-200 bg-gray-50/50 dark:border-white/10 dark:bg-gray-500/5">
        <summary class="flex cursor-pointer list-none items-center justify-between rounded-lg px-4 py-3 text-left text-sm font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 [&::-webkit-details-marker]:hidden">
            <span>Summary</span>
            <svg class="h-5 w-5 transition-transform group-open:rotate-180" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" /></svg>
        </summary>
        <div class="rounded-b-lg border-t border-gray-200 bg-white dark:border-white/10 dark:bg-white/5">
            <div class="grid grid-cols-1 gap-4 md:grid-cols-12">
                <div class="p-4 md:col-span-6">
                    <h4 class="mb-2 text-sm font-semibold text-gray-700 dark:text-gray-300">Case Summary</h4>
                    <ul class="list-inside list-disc space-y-1 text-sm text-gray-600 dark:text-gray-400">
                        @foreach(array_filter(explode("\n", $summaryText)) as $line)
                            <li>{{ $line }}</li>
                        @endforeach
                    </ul>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <a href="{{ route('filament.admin.resources.files.edit', $record) }}" class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-semibold shadow-sm hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:hover:bg-gray-700">Edit summary</a>
                        <textarea id="summary-copy-{{ $record->id }}" class="hidden" readonly>{{ $summaryText }}</textarea>
                        <button type="button" onclick="navigator.clipboard.writeText(document.getElementById('summary-copy-{{ $record->id }}').value).then(() => alert('Copied to clipboard'))" class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-semibold shadow-sm hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:hover:bg-gray-700">Copy for email / WhatsApp</button>
                    </div>
                </div>
                <div class="border-t border-gray-200 p-4 dark:border-white/10 md:col-span-6 md:border-t-0 md:border-l">
                    <h4 class="mb-2 text-sm font-semibold text-gray-700 dark:text-gray-300">Tasks</h4>
                    <div class="space-y-2">
                        @foreach($compactTasks as $t)
                            <div class="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-gray-200 bg-gray-50/50 px-3 py-2 dark:border-white/10 dark:bg-gray-500/5">
                                <span class="text-sm font-medium">{{ $t['name'] }}</span>
                                <div class="flex items-center gap-2">
                                    <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium {{ $t['status'] === 'Done' ? $badgeClass('green') : $badgeClass('yellow') }}">{{ $t['status'] }}</span>
                                    <span class="text-xs text-gray-500 dark:text-gray-400">{{ $t['assignee'] }}</span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </details>

    <div class="mt-6 rounded-lg border border-gray-200 bg-gray-50/50 p-4 dark:border-white/10 dark:bg-gray-500/5">
        <h3 class="mb-3 text-sm font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Provider Details</h3>
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

    <div class="mt-6 flex flex-wrap gap-2">
        <span class="text-sm text-gray-500 dark:text-gray-400">Quick actions:</span>
        <a href="{{ route('filament.admin.resources.files.view', ['record' => $record]) }}" class="text-sm font-semibold text-primary-600 hover:underline dark:text-primary-400">Open full file view (Old View)</a>
    </div>
</div>
