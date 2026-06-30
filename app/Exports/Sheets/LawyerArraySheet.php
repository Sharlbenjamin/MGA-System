<?php

namespace App\Exports\Sheets;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\Hyperlink;
use PhpOffice\PhpSpreadsheet\Style\Font;

class LawyerArraySheet implements FromArray, WithHeadings, WithTitle, ShouldAutoSize, WithEvents
{
    public function __construct(
        protected string $title,
        protected array $headings,
        protected array $rows,
    ) {}

    public function array(): array
    {
        return $this->rows;
    }

    public function headings(): array
    {
        return $this->headings;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();
                $highestRow = $sheet->getHighestDataRow();
                $highestColumnIndex = Coordinate::columnIndexFromString($sheet->getHighestDataColumn());

                for ($row = 2; $row <= $highestRow; $row++) {
                    for ($columnIndex = 1; $columnIndex <= $highestColumnIndex; $columnIndex++) {
                        $cell = $sheet->getCellByColumnAndRow($columnIndex, $row);
                        $value = $cell->getValue();

                        if (! is_string($value) || ! filled($value)) {
                            continue;
                        }

                        $url = $this->extractUrl($value);

                        if ($url === null) {
                            continue;
                        }

                        $cell->setHyperlink(new Hyperlink($url));
                        $cell->getStyle()->getFont()
                            ->setUnderline(Font::UNDERLINE_SINGLE)
                            ->getColor()
                            ->setARGB('FF0563C1');
                    }
                }
            },
        ];
    }

    protected function extractUrl(string $value): ?string
    {
        $parts = preg_split('/\s*\|\s*/', $value) ?: [$value];

        foreach ($parts as $part) {
            $part = trim($part);

            if (filter_var($part, FILTER_VALIDATE_URL)) {
                return $part;
            }
        }

        return null;
    }
}
