# Taxes Export Zip Feature

## Overview
The Taxes resource now includes an "Export Zip" button that allows users to download a zip file containing all documents (Bills, Expenses, and Invoices) for a selected tax period.

## Features

### Export Zip Button
- **Location**: Taxes resource page header
- **Icon**: Archive box icon
- **Color**: Green (success color)
- **Functionality**: Downloads a zip file with organized folders

### Zip File Structure
The exported zip file contains three main folders:
1. **Invoices/** - Contains all invoice PDF documents
2. **Bills/** - Contains all bill PDF documents  
3. **Expenses/** - Contains all expense PDF documents

### File Naming Convention
- **Zip file name**: `taxes_documents_{YEAR}_Q{QUARTER}_{TIMESTAMP}.zip`
- **Example**: `taxes_documents_2024_Q1_2024-06-30_14-30-25.zip`

### Document Sources
- **Invoices**: Uses `invoice_google_link` field from Invoice model
- **Bills**: Uses `bill_google_link` field from Bill model
- **Expenses**: Uses `attachment_path` field from Transaction model (where type = 'Expense')

### Date Range Filtering
The export respects the current tax period selection:
- **Year**: Selected year (defaults to current year)
- **Quarter**: Selected quarter (1, 2, 3, 4, or 'full' for entire year)

## Technical Implementation

### Files Modified
1. **`app/Filament/Resources/TaxesResource/Pages/ListTaxes.php`**
   - Added "Export Zip" action button
   - Uses existing year/quarter selection

2. **`app/Http/Controllers/TaxesExportController.php`**
   - Added `exportZip()` method
   - Added `downloadGoogleDriveFile()` helper method
   - Added `extractFileIdFromUrl()` helper method

3. **`routes/web.php`**
   - Added route: `GET /taxes/export/zip`

### Dependencies
- **Google Drive API**: For downloading documents from Google Drive
- **ZipArchive**: PHP's built-in zip functionality
- **Carbon**: For date handling

### Error Handling
- Graceful handling of missing Google Drive files
- Logging of download failures
- Fallback content for unavailable documents

## Usage Instructions

1. Navigate to the Taxes resource page
2. Select the desired year and quarter using the period selector
3. Click the "Export Zip" button (green archive box icon)
4. The browser will download the zip file automatically
5. Extract the zip file to access the organized documents

## Security Considerations
- Only authenticated users can access the export functionality
- Google Drive API credentials are required for document downloads
- Temporary zip files are automatically deleted after download

## Troubleshooting

### Common Issues
1. **No documents in zip**: Ensure documents have Google Drive links
2. **Download fails**: Check Google Drive API credentials
3. **Empty folders**: Verify documents exist for the selected period

### Logs
Check Laravel logs for detailed error information:
```bash
tail -f storage/logs/laravel.log
``` 