@php
    // Access the record from the Livewire component
    // In Filament forms, View components have access to the Livewire component
    try {
        $livewire = $getLivewire();
        $record = $livewire->record ?? null;
    } catch (\Exception $e) {
        $record = null;
    }
    
    $allBillItems = collect();
    $billWithDocument = null;
    $medicalReportWithDocument = null;
    $file = $record->file ?? null;
    
    if ($record && $file) {
        $file->load(['providerBranch.provider', 'serviceType']);
        // Eager load bills with their items
        $bills = $file->bills()->with('items')->get();
        
        foreach ($bills as $bill) {
            foreach ($bill->items as $item) {
                $allBillItems->push([
                    'description' => $item->description,
                    'amount' => $item->amount,
                ]);
            }
            
            // Find first bill with document for PDF link
            if (!$billWithDocument && $bill->hasLocalDocument()) {
                $billWithDocument = $bill;
            }
        }
        
        $total = $file->billsTotal();
        $medicalReportWithDocument = $file->medicalReports()->whereNotNull('document_path')->latest()->first();
    } else {
        $total = 0;
    }
@endphp

<div class="space-y-2">
    @if($file)
        <div class="space-y-1 text-sm text-gray-700 dark:text-gray-300 pb-2 border-b border-gray-200 dark:border-gray-600">
            <div><span class="font-medium text-gray-500 dark:text-gray-400">Provider:</span> {{ $file->providerBranch?->provider?->name ?? '—' }}</div>
            <div><span class="font-medium text-gray-500 dark:text-gray-400">Service type:</span> {{ $file->serviceType?->name ?? '—' }}</div>
            <div><span class="font-medium text-gray-500 dark:text-gray-400">Date:</span> {{ $file->service_date?->format('d/m/Y') ?? '—' }}</div>
            <div><span class="font-medium text-gray-500 dark:text-gray-400">Time:</span> {{ $file->service_time ?? '—' }}</div>
        </div>
    @endif

    <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Bill Details</h3>
    
    @if($allBillItems->isEmpty())
        <p class="text-sm text-gray-500">No bill items found</p>
    @else
        <div class="space-y-1">
            @foreach($allBillItems as $index => $item)
                <div class="text-sm">
                    <span>{{ $index + 1 }})</span>
                    <span> {{ $item['description'] }} </span>
                    <span class="font-bold">{{ number_format($item['amount'], 2) }}€</span>
                </div>
            @endforeach
        </div>
        
        <div class="pt-2 border-t border-gray-200 dark:border-gray-600">
            <div class="text-sm font-semibold">
                Total {{ number_format($total, 2) }}€
            </div>
        </div>
    @endif

    @if($billWithDocument || $medicalReportWithDocument)
        <div class="pt-2 space-y-1 border-t border-gray-200 dark:border-gray-600">
            @if($billWithDocument)
                <div>
                    <a href="{{ asset('storage/' . $billWithDocument->bill_document_path) }}" 
                       target="_blank"
                       class="text-sm text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300 underline font-medium transition-colors duration-200">
                        View Bill
                    </a>
                </div>
            @endif
            @if($medicalReportWithDocument)
                <div>
                    <a href="{{ $medicalReportWithDocument->getDocumentSignedUrl() }}" 
                       target="_blank"
                       class="text-sm text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300 underline font-medium transition-colors duration-200">
                        View MR
                    </a>
                </div>
            @endif
        </div>
    @endif
</div>

