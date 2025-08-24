# Upload Screenshot Feature

## Overview

The Upload Screenshot feature allows users to upload an image of patient information and automatically extract data using OCR (Optical Character Recognition) to create new files in the system.

## Features

- **Two-Step Workflow**: Separate buttons for processing and creating
- **Image Upload**: Support for JPG, PNG, JPEG formats up to 10MB
- **OCR Processing**: Automatic text extraction from uploaded images
- **Data Extraction**: Extracts up to 7 fields:
  - Patient Name
  - Date of Birth
  - Client Reference
  - Service Type
  - Patient Address
  - Symptoms
  - Additional Information (optional)
- **Gender Detection**: Automatically determines gender from patient name (defaults to Female)
- **Duplicate Prevention**: Checks for existing patients before creating new ones
- **Data Review**: Allows editing of extracted data before file creation
- **Smart Button Visibility**: Buttons appear only when appropriate actions are available

## How to Use

### Two-Step Process:

**Step 1: Upload and Process**
1. Navigate to the FileResource list view
2. Click the "Upload Screenshot" button in the header actions
3. Select a client from the dropdown
4. Upload a screenshot of the patient information
5. Click the **"Process Image"** button to extract data using OCR
6. The extracted data will populate the form fields automatically

**Step 2: Review and Create**
7. Review and edit the extracted data in the form fields
8. Fill in any missing required fields (Service Type is required)
9. Click the **"Create File"** button to create the new file

## Implementation Details

### Files Created/Modified

1. **OCR Service** (`app/Services/OcrService.php`)
   - Handles image processing and text extraction
   - Includes gender detection logic
   - Data cleaning and validation

2. **FileResource List Page** (`app/Filament/Resources/FileResource/Pages/ListFiles.php`)
   - Added upload screenshot action
   - Form handling and file creation logic

3. **Dependencies**
   - Added `spatie/image` package for image processing

### Current OCR Implementation

The current implementation uses a simulated OCR approach that returns sample data. To integrate with real OCR services, you have several options:

#### Option 1: Google Vision API (Recommended)

1. Install the Google Cloud Vision package:
```bash
composer require google/cloud-vision
```

2. Set up Google Cloud credentials in your `.env`:
```
GOOGLE_CLOUD_PROJECT_ID=your-project-id
GOOGLE_CLOUD_STORAGE_BUCKET=your-bucket
GOOGLE_APPLICATION_CREDENTIALS=path/to/service-account-key.json
```

3. Update the `simulateOcrExtraction` method in `OcrService.php`:
```php
private function simulateOcrExtraction($imagePath): array
{
    $client = new \Google\Cloud\Vision\V1\ImageAnnotatorClient();
    $image = file_get_contents($imagePath);
    $response = $client->documentTextDetection($image);
    $text = $response->getFullTextAnnotation()->getText();
    
    return $this->parseExtractedText($text);
}
```

#### Option 2: AWS Textract

1. Install the AWS SDK:
```bash
composer require aws/aws-sdk-php
```

2. Configure AWS credentials in your `.env`:
```
AWS_ACCESS_KEY_ID=your-access-key
AWS_SECRET_ACCESS_KEY=your-secret-key
AWS_DEFAULT_REGION=us-east-1
```

3. Update the OCR service to use AWS Textract:
```php
use Aws\Textract\TextractClient;

private function simulateOcrExtraction($imagePath): array
{
    $client = new TextractClient([
        'version' => 'latest',
        'region'  => env('AWS_DEFAULT_REGION'),
    ]);
    
    $result = $client->detectDocumentText([
        'Document' => [
            'Bytes' => file_get_contents($imagePath)
        ]
    ]);
    
    $text = '';
    foreach ($result['Blocks'] as $block) {
        if ($block['BlockType'] === 'LINE') {
            $text .= $block['Text'] . "\n";
        }
    }
    
    return $this->parseExtractedText($text);
}
```

#### Option 3: Tesseract OCR

1. Install Tesseract on your server:
```bash
# Ubuntu/Debian
sudo apt-get install tesseract-ocr

# macOS
brew install tesseract

# Windows
# Download from https://github.com/UB-Mannheim/tesseract/wiki
```

2. Install the PHP wrapper:
```bash
composer require thiagoalessio/tesseract-ocr-for-php
```

3. Update the OCR service:
```php
use Thiagoalessio\TesseractOCR\TesseractOCR;

private function simulateOcrExtraction($imagePath): array
{
    $ocr = new TesseractOCR($imagePath);
    $text = $ocr->run();
    
    return $this->parseExtractedText($text);
}
```

## Customization

### Text Parsing Patterns

The `parseExtractedText` method in `OcrService.php` contains regex patterns for extracting specific fields. You can customize these patterns based on your document format:

```php
// Example: Custom pattern for patient name
if (preg_match('/(?:patient|name|full name)[\s:]+(.+)/i', $line, $matches)) {
    $data['patient_name'] = trim($matches[1]);
}
```

### Gender Detection

The gender detection logic can be customized in the `determineGenderFromName` method:

```php
public function determineGenderFromName($name): string
{
    // Add your custom logic here
    $maleNames = ['john', 'michael', 'david', ...];
    $femaleNames = ['mary', 'patricia', 'jennifer', ...];
    
    // Your detection logic
}
```

### File Storage

Uploaded screenshots are stored in the `storage/app/public/screenshots` directory. You can customize the storage location by modifying the `FileUpload` component configuration.

## Error Handling

The feature includes comprehensive error handling:

- Image upload validation
- OCR processing errors
- Database transaction rollback on errors
- User-friendly error notifications

## Security Considerations

- File upload validation (type, size, content)
- Secure file storage
- Input sanitization
- Database transaction safety

## Performance Optimization

- Image compression before OCR processing
- Temporary file cleanup
- Database transaction optimization
- Caching for repeated OCR requests

## Testing

To test the feature:

1. Upload a test image with clear text
2. Verify extracted data accuracy
3. Test with various image formats and sizes
4. Test error scenarios (invalid files, network issues)

## Future Enhancements

- Batch processing for multiple images
- Machine learning for improved text extraction
- Integration with document templates
- Advanced data validation rules
- Export extracted data to various formats
