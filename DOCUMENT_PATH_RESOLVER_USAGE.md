# DocumentPathResolver Usage Examples

## Basic Usage

```php
use App\Services\DocumentPathResolver;
use App\Models\File;

$resolver = new DocumentPathResolver();
$file = File::find(1); // File with mga_reference = 'MG001AB'

// Get directory path
$directory = $resolver->dirFor($file, 'gops');
// Returns: "files/MG001AB/gops"

// Ensure directory exists and get path
$directory = $resolver->ensure($file, 'medical_reports');
// Returns: "files/MG001AB/medical_reports"
// Creates directory on public disk if it doesn't exist

// Get full path for a document
$documentPath = $resolver->pathFor($file, 'bills', 'bill-001.pdf');
// Returns: "files/MG001AB/bills/bill-001.pdf"

// Ensure directory exists and get full document path
$documentPath = $resolver->ensurePathFor($file, 'invoices', 'invoice-001.pdf');
// Returns: "files/MG001AB/invoices/invoice-001.pdf"
// Creates directory on public disk if it doesn't exist
```

## Valid Categories

- `gops`
- `medical_reports`
- `prescriptions`
- `bills`
- `invoices`
- `transactions/in`
- `transactions/out`

## Error Handling

```php
// Invalid category throws InvalidArgumentException
try {
    $resolver->dirFor($file, 'invalid_category');
} catch (InvalidArgumentException $e) {
    // Handle error
}

// File without mga_reference throws InvalidArgumentException
$fileWithoutReference = new File(['status' => 'Active']);
try {
    $resolver->dirFor($fileWithoutReference, 'gops');
} catch (InvalidArgumentException $e) {
    // Handle error
}
```

## Integration with Models

```php
// In your model or controller
public function storeDocument(File $file, string $category, UploadedFile $uploadedFile)
{
    $resolver = new DocumentPathResolver();
    
    // Ensure directory exists and get full path
    $documentPath = $resolver->ensurePathFor($file, $category, $uploadedFile->getClientOriginalName());
    
    // Store the file
    $uploadedFile->storeAs('', $documentPath, 'public');
    
    return $documentPath;
}
```

## Utility Methods

```php
// Check if directory exists
$exists = $resolver->directoryExists($file, 'gops');

// Get absolute storage path
$absolutePath = $resolver->absolutePathFor($file, 'bills');

// Get all files in directory
$files = $resolver->getFilesInDirectory($file, 'prescriptions');

// Check if category is valid
$isValid = DocumentPathResolver::isValidCategory('gops');

// Get all valid categories
$categories = DocumentPathResolver::getValidCategories();
```
