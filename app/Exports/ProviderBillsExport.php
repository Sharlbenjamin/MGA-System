<?php

namespace App\Exports;

use App\Models\Bill;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class ProviderBillsExport implements FromQuery, WithHeadings, WithMapping, WithColumnWidths
{
    public function __construct(
        protected Builder $query,
    ) {}

    public function query(): Builder
    {
        return (clone $this->query)
            ->with(['file.patient', 'file.serviceType'])
            ->reorder()
            ->orderByDesc('bills.id');
    }

    public function headings(): array
    {
        return [
            'Bill',
            'MGA Reference',
            'Patient',
            'DOB',
            'Service Date',
            'Service Type',
            'Paid',
            'Payment Date',
            'Status',
            'Total',
            'Remaining',
        ];
    }

    /**
     * @param  Bill  $bill
     */
    public function map($bill): array
    {
        return [
            $bill->display_name,
            $bill->file?->mga_reference ?? '',
            $bill->file?->patient?->name ?? '',
            $bill->file?->patient?->dob?->format('d/m/Y') ?? '',
            $bill->file?->service_date?->format('d/m/Y') ?? '',
            $bill->file?->serviceType?->name ?? '',
            round((float) $bill->paid_amount, 2),
            $bill->payment_date?->format('d/m/Y') ?? '',
            $bill->status ?? '',
            round((float) $bill->total_amount, 2),
            round((float) $bill->total_amount - (float) ($bill->paid_amount ?? 0), 2),
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 28,
            'B' => 18,
            'C' => 24,
            'D' => 12,
            'E' => 14,
            'F' => 20,
            'G' => 12,
            'H' => 14,
            'I' => 12,
            'J' => 12,
            'K' => 12,
        ];
    }
}
