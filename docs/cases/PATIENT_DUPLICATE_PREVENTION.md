# Patient Duplicate Prevention and Similar Patient Suggestions

## Overview

This implementation adds two key features to the file creation process:

1. **Duplicate Patient Prevention**: Automatically detects and prevents creating duplicate patients with the same name and client
2. **Similar Patient Suggestions**: Shows similar patient names while typing, with their client information and details

## Features

### 1. Duplicate Patient Prevention

When creating a new file with a new patient:
- The system checks if a patient with the same name and client already exists
- If a duplicate is found, it shows a warning notification
- The system automatically uses the existing patient instead of creating a duplicate
- Database-level unique constraint prevents duplicates at the database level

### 2. Similar Patient Suggestions

While typing a patient name:
- Real-time search for similar patient names
- Shows patient details including:
  - Patient name
  - Client name
  - Date of birth
  - Gender
  - Number of files
- Keyboard navigation support (arrow keys, enter, escape)
- Click to select a suggested patient

## Implementation Details

### Database Changes

- Added unique constraint on `patients` table: `(name, client_id)`
- Migration: `2025_08_11_100647_add_unique_constraint_to_patients_table.php`

### API Endpoints

- `GET /api/patients/search-similar` - Search for similar patients
- `POST /api/patients/check-duplicate` - Check for duplicate patients

### Model Methods

**Patient Model** (`app/Models/Patient.php`):
- `findSimilar($name, $clientId, $limit)` - Find similar patients
- `findDuplicate($name, $clientId)` - Check for exact duplicates

### Custom Form Component

**PatientNameInput** (`app/Filament/Forms/Components/PatientNameInput.php`):
- Custom Filament form component
- Real-time search and duplicate checking
- Interactive suggestions dropdown
- Duplicate warning display

### Form Integration

Updated both FileResource forms:
- `app/Filament/Resources/FileResource.php`
- `app/Filament/Doctor/Resources/FileResource.php`

### Create File Pages

Updated both CreateFile pages:
- `app/Filament/Resources/FileResource/Pages/CreateFile.php`
- `app/Filament/Doctor/Resources/FileResource/Pages/CreateFile.php`

## Usage

### For Users

1. **Creating a new file with a new patient**:
   - Check "New Patient" checkbox
   - Start typing patient name
   - See similar patients appear as suggestions
   - If typing an exact duplicate, see warning message
   - System will use existing patient if duplicate found

2. **Selecting from suggestions**:
   - Use arrow keys to navigate suggestions
   - Press Enter to select
   - Press Escape to close suggestions
   - Click on any suggestion to select it

### For Developers

#### Adding to other forms:

```php
use App\Filament\Forms\Components\PatientNameInput;

PatientNameInput::make('patient_name')
    ->label('Patient Name')
    ->required()
```

#### Using the API:

```javascript
// Search similar patients
const response = await fetch('/api/patients/search-similar?name=John&client_id=1');
const data = await response.json();

// Check for duplicates
const response = await fetch('/api/patients/check-duplicate', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrfToken
    },
    body: JSON.stringify({ name: 'John Smith', client_id: 1 })
});
const data = await response.json();
```

## Benefits

1. **Prevents Data Duplication**: No more duplicate patients in the system
2. **Improves User Experience**: Real-time suggestions help users find existing patients
3. **Reduces Errors**: Automatic duplicate detection prevents mistakes
4. **Maintains Data Integrity**: Database constraints ensure consistency
5. **Better Patient Management**: Users can see patient history and details

## Testing

The implementation includes:
- Database constraint testing
- API endpoint testing
- Form component testing
- Integration testing with existing file creation flow

## Future Enhancements

Potential improvements:
1. Fuzzy matching for patient names
2. Additional patient matching criteria (DOB, phone, email)
3. Patient merge functionality
4. Advanced search filters
5. Patient history display in suggestions 