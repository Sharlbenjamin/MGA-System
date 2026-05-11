<?php

namespace App\Exports;

use App\Models\Client;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class ActiveClientsExport implements FromCollection, WithHeadings, WithMapping
{
    public function collection()
    {
        return Client::query()
            ->with(['country', 'operationContact'])
            ->whereRaw('LOWER(status) = ?', ['active'])
            ->orderBy('company_name')
            ->get();
    }

    public function headings(): array
    {
        return [
            'Name',
            'Address',
            'Country',
            'Tax ID (NIF)',
        ];
    }

    public function map($client): array
    {
        return [
            $client->company_name ?? '',
            $client->operationContact?->address ?? '',
            $client->country?->name ?? '',
            $client->niv_number ?? '',
        ];
    }
}
