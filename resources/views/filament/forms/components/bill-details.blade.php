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
    
    if ($record && $record->file) {
        // Eager load bills with their items
        $bills = $record->file->bills()->with('items')->get();
        
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
        
        $total = $record->file->billsTotal();
    } else {
        $total = 0;
    }
@endphp

<div class="space-y-2">
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
        
        <div class="pt-2 border-t border-gray-200">
            <div class="text-sm font-semibold">
                Total {{ number_format($total, 2) }}€
            </div>
        </div>
        
        @if($billWithDocument)
            <div class="pt-2">
                <a href="{{ asset('storage/' . $billWithDocument->bill_document_path) }}" 
                   target="_blank"
                   class="text-sm text-blue-600 hover:text-blue-800 underline font-medium transition-colors duration-200">
                    View Bill
                </a>
            </div>
        @endif
    @endif
</div>

