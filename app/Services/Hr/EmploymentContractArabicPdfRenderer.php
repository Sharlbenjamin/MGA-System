<?php

namespace App\Services\Hr;

use Illuminate\Support\Facades\View;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;

class EmploymentContractArabicPdfRenderer
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function render(array $payload): string
    {
        $html = View::make('pdf.employment-contract-ar', $payload)->render();

        $tempDir = storage_path('app/mpdf-temp');
        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'margin_left' => 16,
            'margin_right' => 16,
            'margin_top' => 16,
            'margin_bottom' => 16,
            'margin_header' => 0,
            'margin_footer' => 0,
            'tempDir' => $tempDir,
            'directionality' => 'rtl',
            'autoArabic' => true,
            'autoLangToFont' => true,
            'useSubstitutions' => true,
            'shrink_tables_to_fit' => 1,
        ]);

        $mpdf->SetDirectionality('rtl');
        $mpdf->WriteHTML($html);

        return $mpdf->Output('', Destination::STRING_RETURN);
    }
}
