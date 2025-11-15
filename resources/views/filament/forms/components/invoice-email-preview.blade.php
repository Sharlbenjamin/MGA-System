@php
    $invoice = $invoice ?? null;
    if (!$invoice) {
        try {
            $livewire = $getLivewire();
            $invoice = $livewire->record ?? null;
        } catch (\Exception $e) {
            $invoice = null;
        }
    }
    
    if ($invoice) {
        $gopTotal = $invoice->file->gops()->where('type', 'In')->sum('amount');
        
        $subject = "MGA Invoice {$invoice->name} for {$invoice->file->client_reference} | {$invoice->file->mga_reference}";
        
        $preview = "Dear team,\n\n";
        $preview .= "Find Attached the Invoice {$invoice->name}:\n\n";
        $preview .= "Your Reference : {$invoice->file->client_reference}\n";
        $preview .= "Patient Name : {$invoice->file->patient->name}\n";
        $preview .= "MGA Reference : {$invoice->file->mga_reference}\n";
        $preview .= "Issue Date : " . $invoice->invoice_date->format('d/m/Y') . "\n";
        $preview .= "Due Date : " . $invoice->due_date->format('d/m/Y') . "\n";
        $preview .= "Total : " . number_format($invoice->total_amount, 2) . "€\n";
        $preview .= "GOP Total : " . number_format($gopTotal, 2) . "€\n\n";
        $preview .= "Attachments (selected items will be shown here)";
    } else {
        $subject = '';
        $preview = 'No invoice data available';
    }
@endphp

<div class="space-y-2">
    <div class="text-sm font-semibold text-gray-700 dark:text-gray-300">Email Preview</div>
    <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4 border border-gray-200 dark:border-gray-700">
        <div class="space-y-2">
            <div>
                <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Subject:</span>
                <div class="text-sm text-gray-900 dark:text-gray-100 mt-1">{{ $subject }}</div>
            </div>
            <div class="pt-2 border-t border-gray-200 dark:border-gray-700">
                <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Body:</span>
                <div class="text-sm text-gray-900 dark:text-gray-100 mt-1 whitespace-pre-wrap font-mono">{{ $preview }}</div>
            </div>
        </div>
    </div>
</div>

