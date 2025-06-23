<?php

namespace App\Exports;

use App\Models\Client;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class ClientBalanceExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithColumnWidths
{
    protected $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    public function collection()
    {
        return $this->client->invoices()->where('status', 'Unpaid')->get();
    }

    public function headings(): array
    {
        return [
            'Invoice Number',
            'Patient Name',
            'Date',
            'Due Date',
            'MGA Reference',
            'Client Reference',
            'Amount (€)'
        ];
    }

    public function map($invoice): array
    {
        return [
            $invoice->name,
            $invoice->patient->name,
            $invoice->created_at?->format('d/m/Y'),
            $invoice->due_date?->format('d/m/Y'),
            $invoice->file->mga_reference,
            $invoice->file->client_reference,
            number_format($invoice->total_amount, 2)
        ];
    }

    public function styles(Worksheet $sheet)
    {
        // Style the header row
        $sheet->getStyle('A1:G1')->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '253551'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);

        // Style all data rows
        $lastRow = $sheet->getHighestRow();
        if ($lastRow > 1) {
            $sheet->getStyle('A2:G' . $lastRow)->applyFromArray([
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                        'color' => ['rgb' => 'DDDDDD'],
                    ],
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_LEFT,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
            ]);
        }

        // Add total row
        $totalRow = $lastRow + 2;
        $totalAmount = $this->client->invoices()->where('status', 'Unpaid')->sum('total_amount');
        
        $sheet->setCellValue('A' . $totalRow, 'Total Outstanding Amount:');
        $sheet->setCellValue('G' . $totalRow, '€' . number_format($totalAmount, 2));
        
        $sheet->getStyle('A' . $totalRow . ':G' . $totalRow)->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => '191970'],
            ],
        ]);

        // Add client information at the top
        $financialContact = $this->client->financialContact;
        $billTo = $financialContact ? $financialContact->title : $this->client->company_name;
        
        $sheet->insertNewRowBefore(1, 3);
        $sheet->setCellValue('A1', 'BALANCE STATEMENT');
        $sheet->setCellValue('A2', 'Invoice To: ' . $billTo);
        $sheet->setCellValue('A3', 'Please note that prompt payment of these outstanding invoices would be greatly appreciated.');
        
        $sheet->getStyle('A1')->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 16,
                'color' => ['rgb' => '191970'],
            ],
        ]);
        
        $sheet->getStyle('A2')->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => '191970'],
            ],
        ]);
        
        $sheet->getStyle('A3')->applyFromArray([
            'font' => [
                'italic' => true,
                'color' => ['rgb' => '191970'],
            ],
        ]);

        // Merge cells for title
        $sheet->mergeCells('A1:G1');
        $sheet->mergeCells('A2:G2');
        $sheet->mergeCells('A3:G3');
    }

    public function columnWidths(): array
    {
        return [
            'A' => 20, // Invoice Number
            'B' => 25, // Patient Name
            'C' => 15, // Date
            'D' => 15, // Due Date
            'E' => 20, // MGA Reference
            'F' => 20, // Client Reference
            'G' => 15, // Amount
        ];
    }
} 