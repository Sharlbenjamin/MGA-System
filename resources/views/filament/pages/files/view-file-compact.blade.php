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
    // Heroicon for Service type (conditional on service type name)
    $serviceIcon = 'heroicon-o-wrench';
    if ($record->serviceType) {
        $serviceIcon = match (strtolower(trim($record->serviceType->name))) {
            'telemedicine' => 'heroicon-o-video-camera',
            'house call' => 'heroicon-o-home',
            'hospital visit' => 'heroicon-o-building-office-2',
            default => 'heroicon-o-wrench',
        };
    }
    $iconClass = 'h-4 w-4 shrink-0 text-blue-600 dark:text-blue-400'; // applied to icon wrapper span
@endphp
<div class="fi-section w-full max-w-none min-w-0 rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10">
    {{-- Explicit grid: Row1 [Patient+Client rowspan2 | Case Details rowspan2 | Medical Summary]; Row2 [ | | Provider]; Row3 [Summary | Tasks] --}}
    <div class="grid w-full min-w-0 grid-cols-1 gap-4 sm:grid-cols-3 sm:grid-rows-[auto_auto_auto] md:gap-6">
        {{-- Row 1-2, Col 1: Patient + Client (colspan 1, rowspan 2) --}}
        <div class="min-w-0 sm:col-start-1 sm:row-start-1 sm:row-span-2 sm:min-w-[200px]">
            <div class="fi-section-content rounded-lg border border-gray-200 bg-gray-50/50 p-4 dark:border-white/10 dark:bg-gray-500/5 h-full">
                <h3 class="mb-3 text-base font-semibold text-gray-900 dark:text-white">Patient & Client</h3>
                <hr class="my-4 mx-2 border-gray-200 dark:border-white/10 sm:mx-4" />
                <dl class="space-y-2 text-sm">
                    @if($patient && $patient->name)
                        <div class="flex flex-nowrap gap-x-2 items-center"><span class="{{ $iconClass }}">@svg('heroicon-o-user', 'h-4 w-4')</span><dt class="shrink-0 font-medium text-gray-500 dark:text-gray-400">Patient:</dt><dd class="min-w-0 font-semibold text-gray-950 dark:text-white">{{ $patient->name }}</dd></div>
                    @endif
                    @if($patient && $patient->dob)
                        <div class="flex flex-nowrap gap-x-2 items-center"><span class="{{ $iconClass }}">@svg('heroicon-o-calendar-days', 'h-4 w-4')</span><dt class="shrink-0 font-medium text-gray-500 dark:text-gray-400">DOB:</dt><dd class="min-w-0">{{ \Carbon\Carbon::parse($patient->dob)->format('d/m/Y') }}</dd></div>
                    @endif
                    @if($patient && $patient->gender)
                        <div class="flex flex-nowrap gap-x-2 items-center"><span class="{{ $iconClass }}">@svg('heroicon-o-identification', 'h-4 w-4')</span><dt class="shrink-0 font-medium text-gray-500 dark:text-gray-400">Gender:</dt><dd class="min-w-0">{{ $patient->gender }}</dd></div>
                    @endif
                    @if($record->phone)
                        <div class="flex flex-nowrap gap-x-2 items-center"><span class="{{ $iconClass }}">@svg('heroicon-o-phone', 'h-4 w-4')</span><dt class="shrink-0 font-medium text-gray-500 dark:text-gray-400">Phone:</dt><dd class="min-w-0">{{ $record->phone }}</dd></div>
                    @endif
                    @if($record->email)
                        <div class="flex flex-nowrap gap-x-2 items-center"><span class="{{ $iconClass }}">@svg('heroicon-o-envelope', 'h-4 w-4')</span><dt class="shrink-0 font-medium text-gray-500 dark:text-gray-400">Email:</dt><dd class="min-w-0">{{ $record->email }}</dd></div>
                    @endif
                </dl>
                <hr class="my-4 mx-2 border-gray-200 dark:border-white/10 sm:mx-4" />
                <dl class="space-y-2 text-sm">
                    <div class="flex flex-nowrap gap-x-2 items-center"><span class="{{ $iconClass }}">@svg('heroicon-o-building-office-2', 'h-4 w-4')</span><dt class="shrink-0 font-medium text-gray-500 dark:text-gray-400">Client:</dt><dd class="min-w-0 font-semibold">{{ $clientName }}</dd></div>
                    @if($record->client_reference)
                        <div class="flex flex-nowrap gap-x-2 items-center"><span class="{{ $iconClass }}">@svg('heroicon-o-hashtag', 'h-4 w-4')</span><dt class="shrink-0 font-medium text-gray-500 dark:text-gray-400">Client Ref:</dt><dd class="min-w-0">{{ $record->client_reference }}</dd></div>
                    @endif
                </dl>
            </div>
        </div>
        {{-- Row 1-2, Col 2: Case Details (colspan 1, rowspan 2) --}}
        <div class="min-w-0 sm:col-start-2 sm:row-start-1 sm:row-span-2 sm:min-w-[200px]">
            <div class="fi-section-content rounded-lg border border-gray-200 bg-gray-50/50 p-4 dark:border-white/10 dark:bg-gray-500/5 h-full">
                <h3 class="mb-3 text-base font-semibold text-gray-900 dark:text-white">Case Details</h3>
                <hr class="my-4 mx-2 border-gray-200 dark:border-white/10 sm:mx-4" />
                <dl class="space-y-2 text-sm">
                    @if($record->serviceType)
                        <div class="flex flex-nowrap gap-x-2 items-center"><span class="{{ $iconClass }}">@svg($serviceIcon, 'h-4 w-4')</span><dt class="shrink-0 font-medium text-gray-500 dark:text-gray-400">Service:</dt><dd class="min-w-0">{{ $record->serviceType->name }}</dd></div>
                    @endif
                    @if($record->country)
                        <div class="flex flex-nowrap gap-x-2 items-center"><span class="{{ $iconClass }}">@svg('heroicon-o-globe-alt', 'h-4 w-4')</span><dt class="shrink-0 font-medium text-gray-500 dark:text-gray-400">Country:</dt><dd class="min-w-0">{{ $record->country->name }}</dd></div>
                    @endif
                    @if($record->city)
                        <div class="flex flex-nowrap gap-x-2 items-center"><span class="{{ $iconClass }}">@svg('heroicon-o-map-pin', 'h-4 w-4')</span><dt class="shrink-0 font-medium text-gray-500 dark:text-gray-400">City:</dt><dd class="min-w-0">{{ $record->city->name }}</dd></div>
                    @endif
                    @if($record->service_date)
                        <div class="flex flex-nowrap gap-x-2 items-center"><span class="{{ $iconClass }}">@svg('heroicon-o-calendar', 'h-4 w-4')</span><dt class="shrink-0 font-medium text-gray-500 dark:text-gray-400">Service Date:</dt><dd class="min-w-0">{{ $record->service_date->format('d/m/Y') }}</dd></div>
                    @endif
                    @if($record->service_time)
                        <div class="flex flex-nowrap gap-x-2 items-center"><span class="{{ $iconClass }}">@svg('heroicon-o-clock', 'h-4 w-4')</span><dt class="shrink-0 font-medium text-gray-500 dark:text-gray-400">Service Time:</dt><dd class="min-w-0">{{ \Carbon\Carbon::parse($record->service_time)->format('h:i A') }}</dd></div>
                    @endif
                    <div class="flex flex-nowrap gap-x-2 items-center"><span class="{{ $iconClass }}">@svg('heroicon-o-flag', 'h-4 w-4')</span><dt class="shrink-0 font-medium text-gray-500 dark:text-gray-400">Status:</dt><dd class="min-w-0 inline-block"><x-filament::badge :color="$statusColor">{{ $record->status }}</x-filament::badge></dd></div>
                    <div class="flex flex-nowrap gap-x-2 items-center"><span class="{{ $iconClass }}">@svg('heroicon-o-banknotes', 'h-4 w-4')</span><dt class="shrink-0 font-medium text-gray-500 dark:text-gray-400">Invoice:</dt><dd class="min-w-0 inline-block"><x-filament::badge :color="$invoiceStatus === 'Paid' ? 'success' : ($invoiceStatus === 'No Invoice' ? 'gray' : 'warning')">{{ $invoiceStatus }}</x-filament::badge></dd></div>
                    <div class="flex flex-nowrap gap-x-2 items-center"><span class="{{ $iconClass }}">@svg('heroicon-o-document-text', 'h-4 w-4')</span><dt class="shrink-0 font-medium text-gray-500 dark:text-gray-400">Bill:</dt><dd class="min-w-0 inline-block"><x-filament::badge :color="$billStatus === 'Paid' ? 'success' : ($billStatus === 'No Bill' ? 'gray' : 'warning')">{{ $billStatus }}</x-filament::badge></dd></div>
                </dl>
            </div>
        </div>
        {{-- Row 1-2, Col 3: Medical Summary + Provider Details (one box, rowspan 2) --}}
        <div class="min-w-0 sm:col-start-3 sm:row-start-1 sm:row-span-2 sm:min-w-[200px]">
            <div class="fi-section-content rounded-lg border border-gray-200 bg-gray-50/50 p-4 dark:border-white/10 dark:bg-gray-500/5 h-full">
                <h3 class="mb-3 text-base font-semibold text-gray-900 dark:text-white">Medical Summary</h3>
                <hr class="my-4 mx-2 border-gray-200 dark:border-white/10 sm:mx-4" />
                <dl class="space-y-2 text-sm">
                    @if($record->address)
                        <div class="flex flex-nowrap gap-x-2 items-center"><span class="{{ $iconClass }}">@svg('heroicon-o-map-pin', 'h-4 w-4')</span><dt class="shrink-0 font-medium text-gray-500 dark:text-gray-400">Address:</dt><dd class="min-w-0 break-words">{{ $truncate($record->address, 80) }}</dd></div>
                    @endif
                    @if($record->symptoms)
                        <div class="flex flex-nowrap gap-x-2 items-center"><span class="{{ $iconClass }}">@svg('heroicon-o-heart', 'h-4 w-4')</span><dt class="shrink-0 font-medium text-gray-500 dark:text-gray-400">Symptoms:</dt><dd class="min-w-0 break-words">{{ $truncate($record->symptoms, 80) }}</dd></div>
                    @endif
                    @if($record->diagnosis)
                        <div class="flex flex-nowrap gap-x-2 items-center"><span class="{{ $iconClass }}">@svg('heroicon-o-clipboard-document-list', 'h-4 w-4')</span><dt class="shrink-0 font-medium text-gray-500 dark:text-gray-400">Diagnosis:</dt><dd class="min-w-0 break-words">{{ $record->diagnosis }}</dd></div>
                    @endif
                    @if($record->google_drive_link)
                        <div class="flex flex-nowrap gap-x-2 items-center"><span class="{{ $iconClass }}">@svg('heroicon-o-folder-open', 'h-4 w-4')</span><dt class="shrink-0 font-medium text-gray-500 dark:text-gray-400">Drive:</dt><dd class="min-w-0"><a href="{{ str_starts_with($record->google_drive_link, 'http') ? $record->google_drive_link : 'https://' . $record->google_drive_link }}" target="_blank" rel="noopener" class="text-primary-600 hover:underline dark:text-primary-400">Open folder</a></dd></div>
                    @endif
                </dl>
                <hr class="my-4 mx-2 border-gray-200 dark:border-white/10 sm:mx-4" />
                <h3 class="mb-3 text-base font-semibold text-gray-900 dark:text-white">Provider Details</h3>
                <hr class="my-4 mx-2 border-gray-200 dark:border-white/10 sm:mx-4" />
                @if($record->providerBranch && $record->providerBranch->provider)
                    @php $pb = $record->providerBranch; $prov = $pb->provider; @endphp
                    <dl class="space-y-2 text-sm">
                        <div class="flex flex-nowrap gap-x-2 items-center"><span class="{{ $iconClass }}">@svg('heroicon-o-building-office-2', 'h-4 w-4')</span><dt class="shrink-0 font-medium text-gray-500 dark:text-gray-400">Provider:</dt><dd class="min-w-0 flex flex-wrap items-center gap-x-2 gap-y-1">{{ $prov->name }}<a href="{{ route('filament.admin.resources.providers.edit', $prov->id) }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1 text-sm font-semibold text-primary-600 hover:underline dark:text-primary-400">Edit Provider @svg('heroicon-o-arrow-top-right-on-square', 'h-3.5 w-3.5')</a></dd></div>
                        <div class="flex flex-nowrap gap-x-2 items-center"><span class="{{ $iconClass }}">@svg('heroicon-o-building-storefront', 'h-4 w-4')</span><dt class="shrink-0 font-medium text-gray-500 dark:text-gray-400">Branch:</dt><dd class="min-w-0 flex flex-wrap items-center gap-x-2 gap-y-1">{{ $pb->branch_name }}<a href="{{ route('filament.admin.resources.provider-branches.edit', $pb->id) }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1 text-sm font-semibold text-primary-600 hover:underline dark:text-primary-400">Edit Branch @svg('heroicon-o-arrow-top-right-on-square', 'h-3.5 w-3.5')</a></dd></div>
                        @if($pb->address ?? null)<div class="flex flex-nowrap gap-x-2 items-center"><span class="{{ $iconClass }}">@svg('heroicon-o-map-pin', 'h-4 w-4')</span><dt class="shrink-0 font-medium text-gray-500 dark:text-gray-400">Address:</dt><dd class="min-w-0 break-words">{{ $truncate($pb->address, 60) }}</dd></div>@endif
                        @if($pb->city ?? null)<div class="flex flex-nowrap gap-x-2 items-center"><span class="{{ $iconClass }}">@svg('heroicon-o-map', 'h-4 w-4')</span><dt class="shrink-0 font-medium text-gray-500 dark:text-gray-400">City:</dt><dd class="min-w-0">{{ $pb->city?->name ?? '—' }}</dd></div>@endif
                        @if($prov->phone ?? null)<div class="flex flex-nowrap gap-x-2 items-center"><span class="{{ $iconClass }}">@svg('heroicon-o-phone', 'h-4 w-4')</span><dt class="shrink-0 font-medium text-gray-500 dark:text-gray-400">Phone:</dt><dd class="min-w-0">{{ $prov->phone }}</dd></div>@endif
                    </dl>
                @else
                    <p class="text-sm text-gray-500 dark:text-gray-400">No provider assigned</p>
                @endif
            </div>
        </div>
        {{-- Row 3, Col 1: Summary (colspan 1) --}}
        <div class="min-w-0 sm:col-start-1 sm:row-start-3 sm:min-w-[200px] rounded-lg border border-gray-200 bg-gray-50/50 p-4 dark:border-white/10 dark:bg-gray-500/5">
            <h3 class="mb-3 text-base font-semibold text-gray-900 dark:text-white">Summary</h3>
            <hr class="my-4 mx-2 border-gray-200 dark:border-white/10 sm:mx-4" />
            <ul class="list-inside list-disc space-y-1 text-sm text-gray-600 dark:text-gray-400">
                @foreach(array_filter(explode("\n", $summaryText)) as $line)
                    <li>{{ $line }}</li>
                @endforeach
                <li>Please Note: We only cover the initial consultation and the issuance of the prescription.</li>
                <li>Please send us the Medical report and the invoice after the appointment.</li>
            </ul>
        </div>
        {{-- Row 3, Col 2-3: Tasks (colspan 2) --}}
        <div class="min-w-0 sm:col-start-2 sm:row-start-3 sm:col-span-2 sm:min-w-0 rounded-lg border border-gray-200 bg-gray-50/50 p-4 dark:border-white/10 dark:bg-gray-500/5">
            <h3 class="mb-3 text-base font-semibold text-gray-900 dark:text-white">Tasks</h3>
            <hr class="my-4 mx-2 border-gray-200 dark:border-white/10 sm:mx-4" />
            <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-white/10">
                <table class="w-full min-w-[32rem] border-collapse text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 bg-gray-100 text-left text-xs font-semibold uppercase tracking-wider text-gray-600 dark:border-white/10 dark:bg-gray-500/10 dark:text-gray-400">
                            <th class="px-3 py-2">Task</th>
                            <th class="px-3 py-2">Status</th>
                            <th class="px-3 py-2">Details</th>
                            <th class="px-3 py-2">Assigned</th>
                            <th class="px-3 py-2">Date assigned</th>
                            <th class="px-3 py-2 text-right">View</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($compactTasks as $t)
                            <tr class="border-b border-gray-200 bg-white last:border-b-0 dark:border-white/10 dark:bg-white/5">
                                <td class="px-3 py-2 font-medium text-gray-900 dark:text-white">{{ $t['name'] }}</td>
                                <td class="px-3 py-2 align-middle">
                                    @php
                                        $statusColor = match($t['status'] ?? '') {
                                            'Done' => 'success',
                                            'Pending' => 'warning',
                                            'Unassigned' => 'gray',
                                            default => 'gray',
                                        };
                                    @endphp
                                    <span class="inline-block w-fit"><x-filament::badge :color="$statusColor">{{ $t['status'] }}</x-filament::badge></span>
                                </td>
                                <td class="px-3 py-2 text-gray-600 dark:text-gray-400">{{ $t['details'] ?? 'Pending' }}</td>
                                <td class="px-3 py-2 text-gray-600 dark:text-gray-400">{{ $t['assignee'] }}</td>
                                <td class="px-3 py-2 text-gray-600 dark:text-gray-400">{{ $t['date_assigned'] ?? '—' }}</td>
                                <td class="px-3 py-2 text-right">
                                    @if(!empty($t['view_url']))
                                        <a href="{{ $t['view_url'] }}" target="_blank" rel="noopener" class="fi-btn relative inline-flex items-center justify-center gap-1.5 rounded-lg px-2 py-1.5 text-xs font-semibold text-primary-600 hover:bg-primary-50 dark:text-primary-400 dark:hover:bg-primary-500/10">
                                            View
                                        </a>
                                    @else
                                        <span class="text-gray-400 dark:text-gray-500 text-xs">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
