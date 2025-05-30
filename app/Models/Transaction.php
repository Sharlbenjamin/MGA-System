<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Transaction extends Model
{
    protected $fillable = [
        'name',
        'bank_account_id',
        'related_type',
        'related_id',
        'amount',
        'type',
        'date',
        'notes',
        'attachment_path',
        'bank_charges',
        'charges_covered_by_client',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'date' => 'date',
        'charges_covered_by_client' => 'boolean',
        'bank_charges' => 'decimal:2',
    ];



    public static function boot()
    {
        parent::boot();


        static::updated(function ($transaction) {
            $transaction->bankAccount->calculateBalance();
        });

        static::created(function ($transaction) {
            $transaction->bankAccount->calculateBalance();
        });


        static::deleting(function ($transaction) {
            // un pay all the invoices or bills related to this transaction
            if ($transaction->related_type === 'Invoice') {
                $transaction->related->update(['status' => 'Unpaid']);

            }
            if ($transaction->related_type === 'Bill') {
                $transaction->related->update(['status' => 'Unpaid']);
            }
        });
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function invoices(): BelongsToMany
    {
        return $this->belongsToMany(Invoice::class)->withPivot('amount_paid');
    }

    public function bills(): BelongsToMany
    {
        return $this->belongsToMany(Bill::class)->withPivot('amount_paid');
    }

    public function calculateBankCharges()
    {
        // Calculate total paid amount from all attached invoices
        $totalPaidAmount = $this->invoices()
            ->withPivot('amount_paid')
            ->get()
            ->sum('pivot.amount_paid');

        // Update each invoice's paid_amount
        $this->invoices()->each(function ($invoice) {
            $paidAmount = $invoice->pivot->amount_paid ?? 0;
            $invoice->paid_amount = $paidAmount;
            $invoice->save();

            // Update invoice status based on new paid amount
            $invoice->checkStatus();
        });

        // Bank charges are the difference between transaction amount and total paid
        $this->bank_charges = abs($this->amount - $totalPaidAmount);
        $this->save();
    }

    public function attachInvoices(array $invoiceIds)
    {
        foreach ($invoiceIds as $invoiceId) {
            $invoice = Invoice::find($invoiceId);
            if ($invoice) {
                // Check if the invoice is already attached
                if (!$this->invoices()->where('invoice_id', $invoice->id)->exists()) {
                    // Attach with the full invoice amount as paid_amount
                    $this->invoices()->attach($invoice->id, [
                        'amount_paid' => $invoice->total_amount
                    ]);

                    // Update invoice paid_amount
                    $invoice->paid_amount = $invoice->total_amount;
                    $invoice->save();

                    // Update invoice status
                    $invoice->checkStatus();
                }
            }
        }

        // Recalculate bank charges after attaching invoices
        $this->calculateBankCharges();
    }

    public function updateInvoicePaidAmount(Invoice $invoice, float $amount)
    {
        // Update the pivot table
        $this->invoices()->updateExistingPivot($invoice->id, [
            'amount_paid' => $amount
        ]);

        // Update the invoice's paid_amount
        $invoice->paid_amount = $amount;
        $invoice->save();

        // Update invoice status
        $invoice->checkStatus();

        // Recalculate bank charges
        $this->calculateBankCharges();
    }

    public function attachBills(array $billIds)
    {
        foreach ($billIds as $billId) {
            $bill = Bill::find($billId);
            if ($bill) {
                // Check if the bill is already attached
                if (!$this->bills()->where('bill_id', $bill->id)->exists()) {
                    // Attach with the full bill amount as paid_amount
                    $this->bills()->attach($bill->id, [
                        'amount_paid' => $bill->total_amount
                    ]);

                    // Update bill paid_amount
                    $bill->paid_amount = $bill->total_amount;
                    $bill->save();

                    // Update bill status
                    $bill->checkStatus();
                }
            }
        }

        // Recalculate bank charges after attaching bills
        $this->calculateBankCharges();
    }
}
