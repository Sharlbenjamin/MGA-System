# File Document Export Usage Guide

## Overview

The `FileDocumentExportController` provides a comprehensive ZIP export functionality for file documents, supporting both local storage and Google Drive integration.

## Endpoint

```
GET /files/export/zip
```

## Authentication

Requires Filament authentication (logged-in admin user).

## Query Parameters

### Date Filters
- `date_from` - Start date (YYYY-MM-DD format)
- `date_to` - End date (YYYY-MM-DD format)

### Document Type Filters
- `types[]` - Array of document types to include:
  - `invoices` - Invoice documents
  - `bills` - Bill documents  
  - `gops` - GOP documents
  - `medical_reports` - Medical report documents
  - `prescriptions` - Prescription documents
  - `transactions` - Transaction documents

### Other Filters
- `status` - File status filter
- `patient_id` - Specific patient ID
- `client_id` - Specific client ID

## Usage Examples

### Export all documents for a date range
```
GET /files/export/zip?date_from=2024-01-01&date_to=2024-12-31
```

### Export only invoices and bills for a specific patient
```
GET /files/export/zip?patient_id=123&types[]=invoices&types[]=bills
```

### Export all documents for a specific client
```
GET /files/export/zip?client_id=456
```

### Export specific document types for a date range
```
GET /files/export/zip?date_from=2024-06-01&date_to=2024-06-30&types[]=gops&types[]=medical_reports
```

## Document Processing Logic

For each document, the system follows this priority:

1. **Local Document First**: If `*_document_path` field exists and file is accessible
   - Adds file from `Storage::disk('public')->path($relative)`
   - Logs successful local document addition

2. **Google Drive Fallback**: If local document not available but `*_google_link` exists
   - Extracts file ID from Google Drive URL
   - Uses `GoogleDriveFileDownloader` service to download
   - Validates content type (PDF/images only)
   - Adds downloaded content to ZIP

3. **Missing Document**: If neither local nor Google Drive document available
   - Creates `<NAME>_MISSING.txt` file with error details
   - Includes instructions for manual retrieval

## ZIP File Structure

```
file_documents_2024-01-01_to_2024-12-31_2024-01-15_14-30-25.zip
├── Invoices/
│   ├── MGA001_John_Doe_Invoice_INV-2024-001.pdf
│   └── MGA002_Jane_Smith_Invoice_INV-2024-002_MISSING.txt
├── Bills/
│   ├── MGA001_John_Doe_Bill_Bill-2024-001.pdf
│   └── MGA002_Jane_Smith_Bill_Bill-2024-002.pdf
├── GOPs/
│   ├── MGA001_John_Doe_GOP_In_1.pdf
│   └── MGA001_John_Doe_GOP_Out_2.pdf
├── MedicalReports/
│   ├── MGA001_John_Doe_MedicalReport_1.pdf
│   └── MGA002_Jane_Smith_MedicalReport_2_MISSING.txt
├── Prescriptions/
│   └── MGA001_John_Doe_Prescription_PRESC-001.pdf
└── Transactions/
    ├── MGA001_John_Doe_Transaction_In_1.pdf
    └── MGA001_John_Doe_Transaction_Out_2.pdf
```

## Response

- **Success**: Downloads ZIP file with `Content-Disposition: attachment`
- **Error**: JSON response with error message
- **No Data**: JSON response indicating no files found

## Logging

The system provides comprehensive logging:

- **Info**: Export completion with statistics
- **Debug**: Individual file processing details
- **Warning**: Failed downloads or missing files
- **Error**: Critical failures with full context

## Statistics

Each export includes statistics:
- `files_processed` - Total files processed
- `local_documents` - Successfully added local documents
- `google_drive_documents` - Successfully downloaded from Google Drive
- `missing_documents` - Documents that couldn't be retrieved
- `errors` - Total errors encountered

## Error Handling

- **Local File Not Found**: Creates missing document placeholder
- **Google Drive Download Failed**: Creates missing document with error details
- **Invalid File Types**: Rejects non-PDF/image files from Google Drive
- **Network Issues**: Implements retry logic for Google Drive downloads
- **ZIP Creation Failed**: Returns 500 error with details

## Security

- Requires authentication
- Validates file types for Google Drive downloads
- Sanitizes filenames for ZIP safety
- Uses secure temporary file handling
- Automatic cleanup after download

## Performance

- Processes files in batches
- Uses efficient ZIP streaming
- Implements proper memory management
- Provides progress logging for large exports

## Integration

The controller integrates with:
- `DocumentPathResolver` service for local file paths
- `GoogleDriveFileDownloader` service for Google Drive downloads
- Laravel's Storage facade for file operations
- Filament authentication system
