use App\Models\Invoice;
use App\Models\File;
use App\Services\UploadInvoiceToGoogleDrive;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;

$files = File::all();
echo "Found " . $files->count() . " files\n";

$totalInvoices = 0;
$processedInvoices = 0;
$failedInvoices = 0;

foreach ($files as $file) {
    $invoices = $file->invoices()->where('status', 'Draft')->get();
    $totalInvoices += $invoices->count();
    
    foreach ($invoices as $invoice) {
        try {
            echo "Processing invoice: " . $invoice->name . " for file: " . $file->mga_reference . "\n";
            
            $pdf = Pdf::loadView('pdf.invoice', ['invoice' => $invoice]);
            $content = $pdf->output();
            $fileName = $invoice->name . '.pdf';

            $uploader = app(UploadInvoiceToGoogleDrive::class);
            $result = $uploader->uploadInvoiceToGoogleDrive(
                $content,
                $fileName,
                $invoice
            );

            if ($result === false) {
                echo "Failed to upload invoice: " . $invoice->name . "\n";
                $failedInvoices++;
                continue;
            }

            $invoice->invoice_google_link = $result['webViewLink'];
            $invoice->status = 'Posted';
            $invoice->invoice_date = $invoice->created_at->format('Y-m-d');
            $invoice->save();

            echo "Successfully generated and uploaded invoice: " . $invoice->name . "\n";
            $processedInvoices++;
            
        } catch (\Exception $e) {
            echo "Error processing invoice " . $invoice->name . ": " . $e->getMessage() . "\n";
            Log::error("Failed to generate invoice: " . $invoice->name, [
                'error' => $e->getMessage(),
                'invoice_id' => $invoice->id,
                'file_id' => $file->id
            ]);
            $failedInvoices++;
        }
    }
}

echo "\n=== SUMMARY ===\n";
echo "Total invoices found: " . $totalInvoices . "\n";
echo "Successfully processed: " . $processedInvoices . "\n";
echo "Failed: " . $failedInvoices . "\n";
echo "================\n"; 