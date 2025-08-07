<?php

namespace App\Filament\Resources\TransactionResource\Pages;

use App\Filament\Resources\TransactionResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Log;

class CreateTransaction extends CreateRecord
{
    protected static string $resource = TransactionResource::class;
    
    protected array $billsToAttach = [];
    protected array $invoicesToAttach = [];

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Debug: Log the incoming data
        Log::info('CreateTransaction mutateFormDataBeforeCreate:', $data);
        
        // Store the bills and invoices data for afterCreate processing
        $this->billsToAttach = $data['bills'] ?? [];
        $this->invoicesToAttach = $data['invoices'] ?? [];
        
        // Debug: Log what we're storing
        Log::info('Bills to attach:', $this->billsToAttach);
        Log::info('Invoices to attach:', $this->invoicesToAttach);
        
        // Remove these fields from the data since we handle them manually
        unset($data['bills']);
        unset($data['invoices']);
        
        return $data;
    }

    protected function afterCreate(): void
    {
        // Get the created transaction
        $transaction = $this->record;
        
        // Debug: Log the transaction
        Log::info('Transaction created:', ['id' => $transaction->id, 'name' => $transaction->name]);
        
        // Attach bills from form data
        if (!empty($this->billsToAttach)) {
            Log::info('Attaching bills from form data:', $this->billsToAttach);
            $transaction->attachBills($this->billsToAttach);
        }
        
        // Attach invoices from form data
        if (!empty($this->invoicesToAttach)) {
            Log::info('Attaching invoices from form data:', $this->invoicesToAttach);
            $transaction->attachInvoices($this->invoicesToAttach);
        }
        
        // Also check for bill_id or invoice_id from the request (for pay bill/invoice buttons)
        $billId = request()->get('bill_id');
        $invoiceId = request()->get('invoice_id');
        
        // Debug: Log request parameters
        Log::info('Request parameters:', ['bill_id' => $billId, 'invoice_id' => $invoiceId]);
        
        // Attach the bill if bill_id is provided (from pay bill button)
        if ($billId && !$transaction->bills()->where('bill_id', $billId)->exists()) {
            Log::info('Attaching bill from request:', $billId);
            $transaction->attachBills([$billId]);
        }
        
        // Attach the invoice if invoice_id is provided (from pay invoice button)
        if ($invoiceId && !$transaction->invoices()->where('invoice_id', $invoiceId)->exists()) {
            Log::info('Attaching invoice from request:', $invoiceId);
            $transaction->attachInvoices([$invoiceId]);
        }
        
        // Debug: Log final state
        Log::info('Final transaction state:', [
            'bills_count' => $transaction->bills()->count(),
            'invoices_count' => $transaction->invoices()->count()
        ]);
    }
}
