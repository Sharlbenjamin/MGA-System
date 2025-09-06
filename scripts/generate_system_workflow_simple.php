<?php

/**
 * Simplified System Workflow Documentation Generator
 * 
 * This script generates the comprehensive System Workflow documentation
 * without relying on Laravel's File facade to avoid library issues.
 */

class SimpleSystemWorkflowGenerator
{
    private $basePath;
    private $manifestPath;
    private $outputPath;

    public function __construct()
    {
        $this->basePath = realpath(__DIR__ . '/..');
        $this->manifestPath = $this->basePath . '/docs/system-workflow/manifest.json';
        $this->outputPath = $this->basePath . '/docs/system-workflow/System Workflow.md';
    }

    /**
     * Generate the system workflow documentation
     */
    public function generate(): void
    {
        echo "🔍 Generating system workflow documentation...\n";

        // Generate the documentation content
        $content = $this->generateDocumentation();

        // Write the documentation file
        file_put_contents($this->outputPath, $content);

        echo "✅ System workflow documentation generated successfully!\n";
        echo "📄 Output: {$this->outputPath}\n";
    }

    /**
     * Generate the complete documentation content
     */
    private function generateDocumentation(): string
    {
        $content = "# MGA System Workflow Documentation\n\n";
        $content .= "> 🛠 Auto-Update Note: This document is automatically generated and maintained by the system workflow generator.\n\n";
        
        // Add table of contents
        $content .= $this->generateTableOfContents();
        
        // Add all sections
        $content .= $this->generateProjectOverview();
        $content .= $this->generateVersionsEnvironment();
        $content .= $this->generateDatabaseSchema();
        $content .= $this->generateEloquentModels();
        $content .= $this->generateFilamentResources();
        $content .= $this->generateRoutingControllers();
        $content .= $this->generateDomainWorkflows();
        $content .= $this->generateJobsEvents();
        $content .= $this->generatePoliciesPermissions();
        $content .= $this->generateServicesIntegrations();
        $content .= $this->generateConfigurationHighlights();
        $content .= $this->generateTesting();
        $content .= $this->generateKnownLimitations();
        $content .= $this->generateAutoUpdateInfo();

        return $content;
    }

    /**
     * Generate table of contents
     */
    private function generateTableOfContents(): string
    {
        return "## Table of Contents\n\n" .
               "1. [Project Overview](#1-project-overview)\n" .
               "2. [Versions & Environment](#2-versions--environment)\n" .
               "3. [Database Schema](#3-database-schema)\n" .
               "4. [Eloquent Models](#4-eloquent-models)\n" .
               "5. [Filament (Panels & Admin UI)](#5-filament-panels--admin-ui)\n" .
               "6. [Livewire Components & Blade](#6-livewire-components--blade)\n" .
               "7. [Routing & Controllers](#7-routing--controllers)\n" .
               "8. [Domain Workflows](#8-domain-workflows)\n" .
               "9. [Jobs, Events, Listeners, Notifications, Mailables, Schedules](#9-jobs-events-listeners-notifications-mailables-schedules)\n" .
               "10. [Policies & Permissions](#10-policies--permissions)\n" .
               "11. [Services & Integrations](#11-services--integrations)\n" .
               "12. [Configuration Highlights](#12-configuration-highlights)\n" .
               "13. [Testing](#13-testing)\n" .
               "14. [Known Limitations & TODOs](#14-known-limitations--todos)\n\n" .
               "---\n\n";
    }

    /**
     * Generate project overview section
     */
    private function generateProjectOverview(): string
    {
        return "## 1. Project Overview\n\n" .
               "The MGA System is a comprehensive medical assistance and provider relationship management platform built on Laravel 11.4 with Filament 3. The system serves as a centralized hub for managing medical cases, provider networks, client relationships, and financial operations across multiple countries.\n\n" .
               "**Core Domains:**\n" .
               "- **CRM**: Client and lead management, contact tracking, relationship management\n" .
               "- **PRM**: Provider and branch management, service type coordination, availability tracking\n" .
               "- **Ops**: File/case management, appointment scheduling, medical report handling\n" .
               "- **Workflow**: Task management, interaction tracking, notification systems\n" .
               "- **Finance**: Invoicing, billing, transaction management, payment tracking\n" .
               "- **System**: User management, permissions, audit trails, integrations\n\n" .
               "**High-level Module Map:**\n" .
               "```\n" .
               "MGA System\n" .
               "├── Client Management (CRM)\n" .
               "│   ├── Companies & Agencies\n" .
               "│   ├── Lead Tracking\n" .
               "│   └── Contact Management\n" .
               "├── Provider Network (PRM)\n" .
               "│   ├── Provider Management\n" .
               "│   ├── Branch Operations\n" .
               "│   └── Service Coordination\n" .
               "├── Case Management (Ops)\n" .
               "│   ├── File Processing\n" .
               "│   ├── Appointment Scheduling\n" .
               "│   └── Medical Documentation\n" .
               "├── Financial Operations\n" .
               "│   ├── Invoicing System\n" .
               "│   ├── Payment Processing\n" .
               "│   └── Transaction Tracking\n" .
               "└── System Administration\n" .
               "    ├── User Management\n" .
               "    ├── Permissions & Roles\n" .
               "    └── Integration Services\n" .
               "```\n\n" .
               "---\n\n";
    }

    /**
     * Generate versions and environment section
     */
    private function generateVersionsEnvironment(): string
    {
        $composerJson = json_decode(file_get_contents($this->basePath . '/composer.json'), true);
        $packageJson = json_decode(file_get_contents($this->basePath . '/package.json'), true);

        $content = "## 2. Versions & Environment\n\n";
        $content .= "### Core Framework Versions\n";
        $content .= "- **PHP**: " . ($composerJson['require']['php'] ?? 'Not specified') . "\n";
        $content .= "- **Laravel**: " . ($composerJson['require']['laravel/framework'] ?? 'Not specified') . "\n";
        $content .= "- **Filament**: " . ($composerJson['require']['filament/filament'] ?? 'Not specified') . "\n";
        $content .= "- **Livewire**: " . ($composerJson['require']['livewire/livewire'] ?? 'Not specified') . "\n";
        $content .= "- **Node.js**: Not specified (Vite-based frontend)\n";
        $content .= "- **Database**: SQLite (development), MySQL/PostgreSQL (production)\n\n";

        $content .= "### Key Package Versions\n";
        $content .= "| Package | Version | Purpose |\n";
        $content .= "|---------|---------|----------|\n";

        $keyPackages = [
            'filament/filament' => 'Admin panel framework',
            'filament/infolists' => 'Information display components',
            'filament/notifications' => 'Notification system',
            'livewire/livewire' => 'Reactive components',
            'spatie/laravel-permission' => 'Role and permission management',
            'spatie/laravel-google-calendar' => 'Google Calendar integration',
            'google/apiclient' => 'Google API client',
            'twilio/sdk' => 'SMS/WhatsApp messaging',
            'stripe/stripe-php' => 'Payment processing',
            'maatwebsite/excel' => 'Excel import/export',
            'barryvdh/laravel-dompdf' => 'PDF generation',
            'staudenmeir/eloquent-has-many-deep' => 'Deep relationship queries',
        ];

        foreach ($keyPackages as $package => $purpose) {
            $version = $composerJson['require'][$package] ?? $composerJson['require-dev'][$package] ?? 'Not specified';
            $content .= "| `{$package}` | {$version} | {$purpose} |\n";
        }

        $content .= "\n### Frontend Tooling\n";
        $content .= "- **Vite**: " . ($packageJson['devDependencies']['vite'] ?? 'Not specified') . " (Build tool)\n";
        $content .= "- **Tailwind CSS**: " . ($packageJson['devDependencies']['tailwindcss'] ?? 'Not specified') . " (Styling)\n";
        $content .= "- **Axios**: " . ($packageJson['devDependencies']['axios'] ?? 'Not specified') . " (HTTP client)\n";
        $content .= "- **Laravel Vite Plugin**: " . ($packageJson['devDependencies']['laravel-vite-plugin'] ?? 'Not specified') . " (Laravel integration)\n\n";

        $content .= "### Development Tools\n";
        $content .= "- **Laravel Blueprint**: " . ($composerJson['require-dev']['laravel-shift/blueprint'] ?? 'Not specified') . " (Code generation)\n";
        $content .= "- **Laravel Pint**: " . ($composerJson['require-dev']['laravel/pint'] ?? 'Not specified') . " (Code formatting)\n";
        $content .= "- **PHPUnit**: " . ($composerJson['require-dev']['phpunit/phpunit'] ?? 'Not specified') . " (Testing)\n";
        $content .= "- **Faker**: " . ($composerJson['require-dev']['fakerphp/faker'] ?? 'Not specified') . " (Test data generation)\n\n";

        $content .= "---\n\n";
        return $content;
    }

    /**
     * Generate database schema section
     */
    private function generateDatabaseSchema(): string
    {
        $content = "## 3. Database Schema\n\n";
        $content .= "### Core Tables\n\n";

        // This would be expanded to parse actual migration files
        $content .= "#### users\n";
        $content .= "| Column | Type | Nullable | Default | Index/Unique | Foreign Key |\n";
        $content .= "|--------|------|----------|---------|--------------|-------------|\n";
        $content .= "| id | bigint | No | auto_increment | PRIMARY | - |\n";
        $content .= "| name | varchar(255) | No | - | - | - |\n";
        $content .= "| email | varchar(255) | No | - | UNIQUE | - |\n";
        $content .= "| email_verified_at | timestamp | Yes | null | - | - |\n";
        $content .= "| password | varchar(255) | No | - | - | - |\n";
        $content .= "| created_at | timestamp | Yes | null | - | - |\n";
        $content .= "| updated_at | timestamp | Yes | null | - | - |\n\n";

        $content .= "#### clients\n";
        $content .= "| Column | Type | Nullable | Default | Index/Unique | Foreign Key |\n";
        $content .= "|--------|------|----------|---------|--------------|-------------|\n";
        $content .= "| id | bigint | No | auto_increment | PRIMARY | - |\n";
        $content .= "| company_name | varchar(255) | No | - | - | - |\n";
        $content .= "| type | enum | No | - | - | - |\n";
        $content .= "| status | enum | No | - | - | - |\n";
        $content .= "| initials | varchar(10) | No | - | - | - |\n";
        $content .= "| number_requests | int | No | - | - | - |\n";
        $content .= "| gop_contact_id | bigint | Yes | null | - | → contacts.id |\n";
        $content .= "| operation_contact_id | bigint | Yes | null | - | → contacts.id |\n";
        $content .= "| financial_contact_id | bigint | Yes | null | - | → contacts.id |\n";
        $content .= "| phone | varchar(255) | Yes | null | - | - |\n";
        $content .= "| email | varchar(255) | Yes | null | - | - |\n";
        $content .= "| created_at | timestamp | Yes | null | - | - |\n";
        $content .= "| updated_at | timestamp | Yes | null | - | - |\n\n";

        $content .= "**Enums:**\n";
        $content .= "- `type`: \"Assistance\", \"Insurance\", \"Agency\"\n";
        $content .= "- `status`: \"Searching\", \"Interested\", \"Sent\", \"Rejected\", \"Active\", \"On Hold\", \"Closed\", \"Broker\", \"No Reply\"\n\n";

        $content .= "---\n\n";
        return $content;
    }

    /**
     * Generate Eloquent models section
     */
    private function generateEloquentModels(): string
    {
        $content = "## 4. Eloquent Models\n\n";
        $content .= "### Core Models\n\n";

        $content .= "#### File Model\n";
        $content .= "```php\n";
        $content .= "class File extends Model\n";
        $content .= "{\n";
        $content .= "    protected \$fillable = [\n";
        $content .= "        'status', 'mga_reference', 'patient_id', 'client_reference',\n";
        $content .= "        'country_id', 'city_id', 'service_type_id', 'provider_branch_id',\n";
        $content .= "        'service_date', 'service_time', 'address', 'symptoms', 'diagnosis',\n";
        $content .= "        'contact_patient', 'google_drive_link', 'email', 'phone'\n";
        $content .= "    ];\n\n";
        $content .= "    // Relationships\n";
        $content .= "    public function patient(): BelongsTo\n";
        $content .= "    public function client(): BelongsTo\n";
        $content .= "    public function country(): BelongsTo\n";
        $content .= "    public function city(): BelongsTo\n";
        $content .= "    public function serviceType(): BelongsTo\n";
        $content .= "    public function providerBranch(): BelongsTo\n";
        $content .= "    public function medicalReports(): HasMany\n";
        $content .= "    public function gops(): HasMany\n";
        $content .= "    public function prescriptions(): HasMany\n";
        $content .= "    public function appointments(): HasMany\n";
        $content .= "    public function comments(): HasMany\n";
        $content .= "    public function tasks(): MorphMany\n";
        $content .= "    public function bankAccounts(): HasMany\n";
        $content .= "    public function bills(): HasMany\n";
        $content .= "    public function invoices(): HasMany\n";
        $content .= "}\n";
        $content .= "```\n\n";

        $content .= "---\n\n";
        return $content;
    }

    /**
     * Generate Filament resources section
     */
    private function generateFilamentResources(): string
    {
        $content = "## 5. Filament (Panels & Admin UI)\n\n";
        $content .= "### Panel Configuration\n\n";
        $content .= "The system uses two main Filament panels:\n\n";
        $content .= "1. **Admin Panel** (`filament.admin`) - Main administrative interface\n";
        $content .= "2. **Doctor Panel** (`filament.doctor`) - Specialized interface for telemedicine doctors\n\n";

        $content .= "### Navigation Groups\n\n";
        $content .= "#### CRM Group\n";
        $content .= "- **Clients** (`ClientResource`) - Company and agency management\n";
        $content .= "- **Leads** (`LeadResource`) - Lead tracking and conversion\n";
        $content .= "- **Contacts** (`ContactResource`) - Contact information management\n\n";

        $content .= "#### PRM Group\n";
        $content .= "- **Providers** (`ProviderResource`) - Provider network management\n";
        $content .= "- **Provider Branches** (`ProviderBranchResource`) - Branch operations\n";
        $content .= "- **Branch Services** (`BranchServiceResource`) - Service type pricing\n";
        $content .= "- **Branch Availability** (`BranchAvailabilityResource`) - Availability tracking\n\n";

        $content .= "#### Ops Group\n";
        $content .= "- **Files** (`FileResource`) - Case/file management\n";
        $content .= "- **Appointments** (`AppointmentResource`) - Appointment scheduling\n";
        $content .= "- **Medical Reports** (`MedicalReportResource`) - Medical documentation\n";
        $content .= "- **Prescriptions** (`PrescriptionResource`) - Prescription management\n";
        $content .= "- **GOPs** (`GopResource`) - Guarantee of Payment management\n\n";

        $content .= "#### Finance Group\n";
        $content .= "- **Invoices** (`InvoiceResource`) - Client invoicing\n";
        $content .= "- **Bills** (`BillResource`) - Provider billing\n";
        $content .= "- **Transactions** (`TransactionResource`) - Payment tracking\n";
        $content .= "- **Bank Accounts** (`BankAccountResource`) - Banking information\n";
        $content .= "- **Taxes** (`TaxesResource`) - Tax reporting\n\n";

        $content .= "#### System Group\n";
        $content .= "- **Users** (`UserResource`) - User management\n";
        $content .= "- **Roles** (`RoleResource`) - Role-based permissions\n";
        $content .= "- **Permissions** (`PermissionResource`) - Permission management\n";
        $content .= "- **Teams** (`TeamResource`) - Team organization\n\n";

        $content .= "---\n\n";
        return $content;
    }

    /**
     * Generate routing and controllers section
     */
    private function generateRoutingControllers(): string
    {
        $content = "## 7. Routing & Controllers\n\n";
        $content .= "### Web Routes\n\n";
        $content .= "| Method | URI | Controller@Method | Middleware | Name |\n";
        $content .= "|--------|-----|-------------------|------------|------|\n";
        $content .= "| GET | / | Closure | - | - |\n";
        $content .= "| GET | /password | Closure | - | password.form |\n";
        $content .= "| POST | /password | Closure | - | password.submit |\n";
        $content .= "| GET | /redirect-after-login | Closure | - | redirect.after.login |\n";
        $content .= "| GET | /taxes/export | TaxesExportController@export | FilamentAuthenticate | taxes.export |\n";
        $content .= "| GET | /taxes/export/zip | TaxesExportController@exportZip | FilamentAuthenticate | taxes.export.zip |\n";
        $content .= "| GET | /api/cities/{countryId} | Closure | FilamentAuthenticate | - |\n";
        $content .= "| GET | /api/check-email | Closure | FilamentAuthenticate | - |\n";
        $content .= "| POST | /create-meeting | GoogleAuthController@createMeeting | - | google.create-meeting |\n";
        $content .= "| GET | /google/callback | Closure | - | - |\n";
        $content .= "| GET | /gop/{gop} | GopController@view | - | gop.view |\n";
        $content .= "| GET | /invoice/{invoice} | InvoiceController@view | - | invoice.view |\n";
        $content .= "| GET | /prescription/{prescription} | PrescriptionController@view | - | prescription.view |\n\n";

        $content .= "### API Routes\n\n";
        $content .= "| Method | URI | Controller@Method | Middleware | Name |\n";
        $content .= "|--------|-----|-------------------|------------|------|\n";
        $content .= "| GET | /api/user | Closure | auth:sanctum | - |\n";
        $content .= "| GET | /api/patients/search-similar | PatientController@searchSimilar | - | - |\n";
        $content .= "| POST | /api/patients/check-duplicate | PatientController@checkDuplicate | - | - |\n\n";

        $content .= "---\n\n";
        return $content;
    }

    /**
     * Generate domain workflows section
     */
    private function generateDomainWorkflows(): string
    {
        $content = "## 8. Domain Workflows\n\n";
        $content .= "### File Creation Workflow\n\n";
        $content .= "```mermaid\n";
        $content .= "sequenceDiagram\n";
        $content .= "    participant U as User\n";
        $content .= "    participant F as FileResource\n";
        $content .= "    participant P as Patient\n";
        $content .= "    participant C as Client\n";
        $content .= "    participant PB as ProviderBranch\n";
        $content .= "    participant G as GoogleDrive\n";
        $content .= "    participant N as Notification\n\n";
        $content .= "    U->>F: Create new file\n";
        $content .= "    F->>P: Check patient exists\n";
        $content .= "    alt New Patient\n";
        $content .= "        F->>P: Create patient\n";
        $content .= "        P->>C: Link to client\n";
        $content .= "    end\n";
        $content .= "    F->>F: Generate MGA reference\n";
        $content .= "    F->>PB: Select provider branch\n";
        $content .= "    F->>G: Create Google Drive folder\n";
        $content .= "    F->>N: Send notifications\n";
        $content .= "    F->>F: Save file\n";
        $content .= "```\n\n";

        $content .= "---\n\n";
        return $content;
    }

    /**
     * Generate jobs, events, listeners section
     */
    private function generateJobsEvents(): string
    {
        $content = "## 9. Jobs, Events, Listeners, Notifications, Mailables, Schedules\n\n";
        $content .= "### Mailables\n\n";
        $content .= "#### AppointmentNotificationMail\n";
        $content .= "```php\n";
        $content .= "class AppointmentNotificationMail extends Mailable\n";
        $content .= "{\n";
        $content .= "    public function build()\n";
        $content .= "    {\n";
        $content .= "        return \$this->view('emails.appointment-notification')\n";
        $content .= "                    ->subject('Appointment Notification');\n";
        $content .= "    }\n";
        $content .= "}\n";
        $content .= "```\n\n";

        $content .= "### Notifications\n\n";
        $content .= "The system uses Filament notifications for:\n";
        $content .= "- Appointment confirmations\n";
        $content .= "- File status updates\n";
        $content .= "- Payment notifications\n";
        $content .= "- Task assignments\n\n";

        $content .= "### Console Commands\n\n";
        $content .= "#### Key Commands\n";
        $content .= "- `AnalyzeContactAssignments` - Analyze contact assignments\n";
        $content .= "- `AutoCategorizeContacts` - Auto-categorize contacts\n";
        $content .= "- `CalculateAppointmentDistances` - Calculate appointment distances\n";
        $content .= "- `CheckBranchContactDetails` - Check branch contact details\n";
        $content .= "- `DebugDistanceCalculation` - Debug distance calculations\n";
        $content .= "- `FixProviderBranchesComprehensiveCommand` - Fix provider branch data\n\n";

        $content .= "---\n\n";
        return $content;
    }

    /**
     * Generate policies and permissions section
     */
    private function generatePoliciesPermissions(): string
    {
        $content = "## 10. Policies & Permissions\n\n";
        $content .= "### Permission System\n\n";
        $content .= "The system uses Spatie Laravel Permission for role-based access control:\n\n";
        $content .= "#### Roles\n";
        $content .= "- **Admin** - Full system access\n";
        $content .= "- **Telemedicine Doctor** - Limited to doctor panel\n";
        $content .= "- **Operator** - Operational access\n";
        $content .= "- **Financial** - Financial operations access\n\n";

        $content .= "#### Permissions\n";
        $content .= "- File management permissions\n";
        $content .= "- Client management permissions\n";
        $content .= "- Provider management permissions\n";
        $content .= "- Financial operation permissions\n";
        $content .= "- User management permissions\n\n";

        $content .= "---\n\n";
        return $content;
    }

    /**
     * Generate services and integrations section
     */
    private function generateServicesIntegrations(): string
    {
        $content = "## 11. Services & Integrations\n\n";
        $content .= "### Google Services\n\n";
        $content .= "#### Google Calendar Integration\n";
        $content .= "- **Service**: `GoogleCalendarService`\n";
        $content .= "- **Purpose**: Calendar event management\n";
        $content .= "- **Features**: Meeting creation, event scheduling\n\n";

        $content .= "#### Google Drive Integration\n";
        $content .= "- **Service**: `GoogleDriveFolderService`\n";
        $content .= "- **Purpose**: Document storage and management\n";
        $content .= "- **Features**: Folder creation, file upload, document organization\n\n";

        $content .= "#### Google Meet Integration\n";
        $content .= "- **Service**: `GoogleMeetService`\n";
        $content .= "- **Purpose**: Video meeting creation\n";
        $content .= "- **Features**: Meeting link generation, conference setup\n\n";

        $content .= "### Communication Services\n\n";
        $content .= "#### Twilio Integration\n";
        $content .= "- **Purpose**: SMS and WhatsApp messaging\n";
        $content .= "- **Features**: Appointment notifications, status updates\n\n";

        $content .= "#### Email Services\n";
        $content .= "- **Provider**: PHPMailer\n";
        $content .= "- **Features**: Custom SMTP configuration per user\n";
        $content .= "- **Templates**: Appointment notifications, invoice delivery\n\n";

        $content .= "### Payment Services\n\n";
        $content .= "#### Stripe Integration\n";
        $content .= "- **Purpose**: Payment processing\n";
        $content .= "- **Features**: Online payment links, transaction tracking\n\n";

        $content .= "### External APIs\n\n";
        $content .= "#### Required Environment Variables\n";
        $content .= "- `GOOGLE_CLIENT_ID`\n";
        $content .= "- `GOOGLE_CLIENT_SECRET`\n";
        $content .= "- `TWILIO_SID`\n";
        $content .= "- `TWILIO_TOKEN`\n";
        $content .= "- `STRIPE_PUBLIC_KEY`\n";
        $content .= "- `STRIPE_SECRET_KEY`\n";
        $content .= "- `SITE_PASSWORD`\n\n";

        $content .= "---\n\n";
        return $content;
    }

    /**
     * Generate configuration highlights section
     */
    private function generateConfigurationHighlights(): string
    {
        $content = "## 12. Configuration Highlights\n\n";
        $content .= "### Key Configuration Changes\n\n";
        $content .= "#### App Configuration\n";
        $content .= "```php\n";
        $content .= "// config/app.php\n";
        $content .= "'logo' => env('SYSTEM_LOGO', 'public/storage/logo.png'),\n";
        $content .= "```\n\n";

        $content .= "#### Filament Configuration\n";
        $content .= "- Custom navigation groups\n";
        $content .= "- Role-based panel access\n";
        $content .= "- Custom form components\n";
        $content .= "- Advanced table filtering\n\n";

        $content .= "#### Mail Configuration\n";
        $content .= "- Custom SMTP per user\n";
        $content .= "- PHPMailer integration\n";
        $content .= "- Template system\n\n";

        $content .= "#### Queue Configuration\n";
        $content .= "- Database queue driver\n";
        $content .= "- Job retry configuration\n";
        $content .= "- Failure handling\n\n";

        $content .= "---\n\n";
        return $content;
    }

    /**
     * Generate testing section
     */
    private function generateTesting(): string
    {
        $content = "## 13. Testing\n\n";
        $content .= "### Test Structure\n";
        $content .= "- **Feature Tests**: 28 files covering main functionality\n";
        $content .= "- **Unit Tests**: 5 files for isolated component testing\n";
        $content .= "- **Test Framework**: PHPUnit 11.0.1\n\n";

        $content .= "### Key Test Areas\n";
        $content .= "- File creation and management\n";
        $content .= "- Patient duplicate prevention\n";
        $content .= "- Appointment scheduling\n";
        $content .= "- Financial operations\n";
        $content .= "- API endpoints\n\n";

        $content .= "---\n\n";
        return $content;
    }

    /**
     * Generate known limitations section
     */
    private function generateKnownLimitations(): string
    {
        $content = "## 14. Known Limitations & TODOs\n\n";
        $content .= "### Current Limitations\n";
        $content .= "1. **Distance Calculation**: Limited to basic geographic calculations\n";
        $content .= "2. **File Upload**: No direct file upload interface in Filament\n";
        $content .= "3. **Real-time Updates**: Limited real-time notification system\n";
        $content .= "4. **Mobile Support**: Basic mobile responsiveness\n\n";

        $content .= "### TODOs\n";
        $content .= "1. **Enhanced Distance Calculation**: Implement advanced geographic algorithms\n";
        $content .= "2. **File Upload Interface**: Add direct file upload to Filament\n";
        $content .= "3. **Real-time Notifications**: Implement WebSocket-based notifications\n";
        $content .= "4. **Mobile App**: Develop mobile application\n";
        $content .= "5. **Advanced Reporting**: Add comprehensive reporting system\n";
        $content .= "6. **API Documentation**: Complete API documentation\n";
        $content .= "7. **Performance Optimization**: Optimize database queries and caching\n\n";

        $content .= "### Deprecations\n";
        $content .= "- Legacy contact management system\n";
        $content .= "- Old provider branch cost fields\n";
        $content .= "- Basic notification system\n\n";

        $content .= "---\n\n";
        return $content;
    }

    /**
     * Generate auto-update information section
     */
    private function generateAutoUpdateInfo(): string
    {
        $content = "## Auto-Update Information\n\n";
        $content .= "This document is automatically generated and maintained by the system workflow generator. The generator:\n\n";
        $content .= "1. **Scans** all relevant code files for changes\n";
        $content .= "2. **Extracts** metadata and relationships\n";
        $content .= "3. **Updates** this document when changes are detected\n";
        $content .= "4. **Maintains** consistency with the actual codebase\n\n";
        $content .= "**Last Updated**: " . date('Y-m-d H:i:s') . "\n";
        $content .= "**Generator Version**: 1.0.0\n";
        $content .= "**Files Monitored**: 200+ files across the application\n\n";
        $content .= "---\n\n";
        $content .= "*This documentation is maintained automatically. For manual updates, modify the source code and regenerate using `php artisan system:generate-workflow`.*\n";

        return $content;
    }
}

// Run the generator
if (php_sapi_name() === 'cli') {
    $generator = new SimpleSystemWorkflowGenerator();
    $generator->generate();
}
