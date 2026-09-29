<?php

namespace App\Filament\Pages;

use App\Data\Hr\EmploymentContractExportChoices;
use App\Models\Employee;
use App\Services\Hr\EmploymentContractDraftBuilder;
use App\Services\Hr\EmploymentContractDraftException;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EmploymentContractDraft extends Page
{
    use AuthorizesRequests;
    use Forms\Concerns\InteractsWithForms;

    protected static ?string $navigationGroup = 'HR';

    protected static ?int $navigationSort = 5;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationLabel = 'Employment contract draft';

    protected static ?string $title = 'Employment contract draft';

    protected static ?string $slug = 'employment-contract-draft';

    protected static string $view = 'filament.pages.employment-contract-draft';

    public ?array $data = [];

    public function mount(): void
    {
        $employeeId = request()->integer('employee') ?: null;

        $this->form->fill([
            'employee_id' => $employeeId,
            'contract_type' => EmploymentContractExportChoices::TYPE_INDEFINITE,
            'fixed_term_months' => 12,
            'fixed_term_reason' => null,
            'start_date_override' => null,
            'employer_legal_name' => EmploymentContractExportChoices::defaultEmployerLegalName(),
            'employer_address' => EmploymentContractExportChoices::defaultEmployerAddress(),
            'employer_registration' => EmploymentContractExportChoices::defaultEmployerRegistration(),
            'signatory_name' => EmploymentContractExportChoices::defaultSignatoryName(),
            'signatory_title' => EmploymentContractExportChoices::defaultSignatoryTitle(),
        ]);
    }

    public static function getNavigationLabel(): string
    {
        return 'Contract drafts';
    }

    public function getTitle(): string|Htmlable
    {
        return 'Draft employment contracts';
    }

    public static function canAccess(): bool
    {
        return Auth::check() && Auth::user()?->roles?->contains('name', 'admin');
    }

    public static function urlForEmployee(?int $employeeId = null): string
    {
        $routeName = static::getRouteName('admin');

        $base = Route::has($routeName)
            ? route($routeName)
            : url('/'.trim(Filament::getPanel('admin')->getPath(), '/').'/'.static::getSlug());

        if ($employeeId === null) {
            return $base;
        }

        return $base.(str_contains($base, '?') ? '&' : '?').'employee='.$employeeId;
    }

    public function form(Form $form): Form
    {
        return $form
            ->statePath('data')
            ->schema([
                Forms\Components\Section::make('Employee')
                    ->description('Salary, insurance, and start date are loaded from the employee record.')
                    ->schema([
                        Forms\Components\Select::make('employee_id')
                            ->label('Employee')
                            ->options(fn () => Employee::query()->orderBy('name')->pluck('name', 'id'))
                            ->searchable()
                            ->required()
                            ->live(),
                        Forms\Components\Placeholder::make('employee_summary')
                            ->label('Stored details')
                            ->content(fn (Forms\Get $get) => $this->formatEmployeeSummary($get('employee_id'))),
                    ]),
                Forms\Components\Section::make('Contract choices')
                    ->schema([
                        Forms\Components\Select::make('contract_type')
                            ->label('Contract type')
                            ->options([
                                EmploymentContractExportChoices::TYPE_INDEFINITE => 'Indefinite term',
                                EmploymentContractExportChoices::TYPE_FIXED => 'Fixed term',
                            ])
                            ->required()
                            ->live()
                            ->helperText('Egyptian Labour Law No. 14 of 2025: a fixed-term contract is permitted only where the nature of the work requires it—not for ordinary ongoing roles.'),
                        Forms\Components\Select::make('fixed_term_months')
                            ->label('Fixed-term duration')
                            ->options([
                                3 => '3 months',
                                6 => '6 months',
                                12 => '12 months',
                            ])
                            ->required(fn (Forms\Get $get) => $get('contract_type') === EmploymentContractExportChoices::TYPE_FIXED)
                            ->visible(fn (Forms\Get $get) => $get('contract_type') === EmploymentContractExportChoices::TYPE_FIXED),
                        Forms\Components\Textarea::make('fixed_term_reason')
                            ->label('Reason fixed term is required')
                            ->rows(3)
                            ->required(fn (Forms\Get $get) => $get('contract_type') === EmploymentContractExportChoices::TYPE_FIXED)
                            ->visible(fn (Forms\Get $get) => $get('contract_type') === EmploymentContractExportChoices::TYPE_FIXED)
                            ->helperText('Describe the nature of the work that justifies a fixed term.'),
                        Forms\Components\DatePicker::make('start_date_override')
                            ->label('Contract start date')
                            ->visible(fn (Forms\Get $get) => $this->employeeNeedsStartDate($get('employee_id')))
                            ->required(fn (Forms\Get $get) => $this->employeeNeedsStartDate($get('employee_id')))
                            ->helperText('Used for this export only; the employee record is not updated.'),
                    ]),
                Forms\Components\Section::make('Employer details for this export')
                    ->description('Legal name and signatory are prefilled from system defaults. Enter address and registration before downloading. Contract wage uses the employee’s social insurance salary from HR → Employees.')
                    ->schema([
                        Forms\Components\TextInput::make('employer_legal_name')
                            ->label('Legal name')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\Textarea::make('employer_address')
                            ->label('Address')
                            ->required()
                            ->rows(3)
                            ->helperText('Registered or workplace address for the employer entity on this contract.'),
                        Forms\Components\TextInput::make('employer_registration')
                            ->label('Registration')
                            ->maxLength(255)
                            ->helperText('Commercial registration or tax ID (optional; left blank adds a review notice on the draft).'),
                        Forms\Components\TextInput::make('signatory_name')
                            ->label('Signatory name')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('signatory_title')
                            ->label('Signatory title')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\Placeholder::make('salary_source')
                            ->label('Contract wage')
                            ->content(fn (Forms\Get $get) => $this->formatContractWageSummary($get('employee_id'))),
                        Forms\Components\Placeholder::make('logo_note')
                            ->label('Logo')
                            ->content(fn (): string => 'Using logo file: '.(config('hr.logo_path') ?: 'siglogo.png').' (from public/). Currency: '.(config('hr.salary_currency') ?: 'EGP')),
                    ])
                    ->columns(2),
            ]);
    }

    public function downloadArabic(): ?StreamedResponse
    {
        return $this->downloadPdf('ar');
    }

    public function downloadEnglish(): ?StreamedResponse
    {
        return $this->downloadPdf('en');
    }

    protected function downloadPdf(string $language): ?StreamedResponse
    {
        $state = $this->form->getState();
        $employee = Employee::query()->with('jobTitle')->find($state['employee_id'] ?? null);

        if (! $employee) {
            Notification::make()->title('Select an employee')->danger()->send();

            return null;
        }

        $this->authorize('generateContract', $employee);

        try {
            $choices = $this->choicesFromState($state);
            $builder = app(EmploymentContractDraftBuilder::class);

            $missing = $builder->missingFields($employee, $choices);
            if ($missing !== []) {
                Notification::make()
                    ->title('Cannot generate draft')
                    ->body(implode("\n", array_map(fn ($f) => '• '.$f, $missing)))
                    ->danger()
                    ->persistent()
                    ->send();

                return null;
            }

            $pdf = $language === 'ar'
                ? $builder->renderArabicPdf($employee, $choices)
                : $builder->renderEnglishPdf($employee, $choices);

            $basename = $builder->suggestedDownloadBasename($employee, $language);

            return response()->streamDownload(
                fn () => print ($pdf),
                $basename.'.pdf',
                ['Content-Type' => 'application/pdf'],
            );
        } catch (EmploymentContractDraftException $e) {
            Notification::make()
                ->title('Cannot generate draft')
                ->body(implode("\n", array_map(fn ($f) => '• '.$f, $e->missingFields)))
                ->danger()
                ->persistent()
                ->send();

            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $state
     */
    protected function choicesFromState(array $state): EmploymentContractExportChoices
    {
        $override = filled($state['start_date_override'] ?? null)
            ? Carbon::parse($state['start_date_override'])
            : null;

        return new EmploymentContractExportChoices(
            contractType: (string) ($state['contract_type'] ?? EmploymentContractExportChoices::TYPE_INDEFINITE),
            fixedTermMonths: ($state['contract_type'] ?? null) === EmploymentContractExportChoices::TYPE_FIXED
                ? (int) ($state['fixed_term_months'] ?? 0)
                : null,
            fixedTermReason: ($state['contract_type'] ?? null) === EmploymentContractExportChoices::TYPE_FIXED
                ? ($state['fixed_term_reason'] ?? null)
                : null,
            startDateOverride: $override,
            employerLegalName: trim((string) ($state['employer_legal_name'] ?? '')),
            employerAddress: trim((string) ($state['employer_address'] ?? '')),
            employerRegistration: filled($state['employer_registration'] ?? null)
                ? trim((string) $state['employer_registration'])
                : null,
            signatoryName: trim((string) ($state['signatory_name'] ?? '')),
            signatoryTitle: trim((string) ($state['signatory_title'] ?? '')),
        );
    }

    protected function employeeNeedsStartDate(?int $employeeId): bool
    {
        if (! $employeeId) {
            return false;
        }

        $employee = Employee::query()->find($employeeId);

        return $employee && blank($employee->start_date);
    }

    protected function formatEmployeeSummary(?int $employeeId): string
    {
        if (! $employeeId) {
            return 'Select an employee to preview stored fields.';
        }

        $employee = Employee::query()->with('jobTitle')->find($employeeId);
        if (! $employee) {
            return 'Employee not found.';
        }

        $lines = [
            'Name: '.$employee->name,
            'Job title: '.($employee->jobTitle?->name ?? '—'),
            'Social insurance salary (used in contract): '.($employee->social_insurance_salary !== null ? number_format((float) $employee->social_insurance_salary, 2).' '.config('hr.salary_currency', 'EGP') : '— missing'),
            'Full salary on file (reference only): '.($employee->full_salary !== null ? number_format((float) $employee->full_salary, 2) : '—'),
            'Social insurance number: '.($employee->social_insurance_number ?: '—'),
            'Start date: '.($employee->start_date?->format('Y-m-d') ?? '— (will ask below)'),
        ];

        return implode("\n", $lines);
    }

    protected function formatContractWageSummary(?int $employeeId): string
    {
        if (! $employeeId) {
            return 'Select an employee. The monthly wage in the PDF comes from Social insurance salary on the employee record.';
        }

        $employee = Employee::query()->find($employeeId);
        if (! $employee) {
            return 'Employee not found.';
        }

        if (! $employee->social_insurance_salary || (float) $employee->social_insurance_salary <= 0) {
            return 'Set Social insurance salary on the employee before downloading.';
        }

        return number_format((float) $employee->social_insurance_salary, 2).' '.config('hr.salary_currency', 'EGP').' per month (from employee record).';
    }
}
