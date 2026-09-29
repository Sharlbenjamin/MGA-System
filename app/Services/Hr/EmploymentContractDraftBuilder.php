<?php

namespace App\Services\Hr;

use App\Data\Hr\EmploymentContractExportChoices;
use App\Models\Employee;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;

class EmploymentContractDraftBuilder
{
    /**
     * @return list<string>
     */
    public function missingFields(Employee $employee, EmploymentContractExportChoices $choices): array
    {
        $missing = [];

        if (blank($employee->name)) {
            $missing[] = 'Employee name';
        }

        if (blank($employee->social_insurance_number)) {
            $missing[] = 'Social insurance number';
        }

        if (! $this->positiveAmount($employee->full_salary)) {
            $missing[] = 'Full salary';
        }

        if (! $this->positiveAmount($employee->social_insurance_salary)) {
            $missing[] = 'Social insurance salary';
        }

        if (blank(config('hr.salary_currency'))) {
            $missing[] = 'Salary currency (HR_SALARY_CURRENCY)';
        }

        $startDate = $this->resolveStartDate($employee, $choices);
        if ($startDate === null) {
            $missing[] = 'Contract start date';
        }

        if (blank(config('hr.employer.legal_name'))) {
            $missing[] = 'Employer legal name (HR_EMPLOYER_LEGAL_NAME)';
        }

        if (blank(config('hr.employer.address'))) {
            $missing[] = 'Employer address (HR_EMPLOYER_ADDRESS)';
        }

        if (blank(config('hr.employer.signatory_name'))) {
            $missing[] = 'Signatory name (HR_SIGNATORY_NAME)';
        }

        if (blank(config('hr.employer.signatory_title'))) {
            $missing[] = 'Signatory title (HR_SIGNATORY_TITLE)';
        }

        if (! $this->logoExists()) {
            $missing[] = 'Company logo file ('.config('hr.logo_path').')';
        }

        if ($choices->isFixedTerm()) {
            if (! in_array($choices->fixedTermMonths, [3, 6, 12], true)) {
                $missing[] = 'Fixed-term duration (3, 6, or 12 months)';
            }
            if (blank($choices->fixedTermReason)) {
                $missing[] = 'Fixed-term reason (nature of work requiring fixed term)';
            }
        }

        return $missing;
    }

    /**
     * @return array<string, mixed>
     */
    public function buildPayload(Employee $employee, EmploymentContractExportChoices $choices): array
    {
        $missing = $this->missingFields($employee, $choices);
        if ($missing !== []) {
            throw new EmploymentContractDraftException('Missing required data for contract export.', $missing);
        }

        $employee->loadMissing('jobTitle');

        $startDate = $this->resolveStartDate($employee, $choices);
        assert($startDate instanceof Carbon);

        $draftDate = Carbon::now();
        $currency = (string) config('hr.salary_currency');
        $logoPath = $this->resolveLogoPath();

        $payload = [
            'is_draft' => true,
            'draft_date' => $draftDate,
            'draft_date_formatted' => $draftDate->format('Y-m-d'),
            'employee_name' => $employee->name,
            'employee_job_title' => $employee->jobTitle?->name ?? '',
            'employee_national_id' => filled($employee->national_id) ? $employee->national_id : null,
            'social_insurance_number' => $employee->social_insurance_number,
            'full_salary' => $this->formatMoney($employee->full_salary),
            'full_salary_raw' => (float) $employee->full_salary,
            'social_insurance_salary' => $this->formatMoney($employee->social_insurance_salary),
            'social_insurance_salary_raw' => (float) $employee->social_insurance_salary,
            'salary_currency' => $currency,
            'start_date' => $startDate,
            'start_date_formatted' => $startDate->format('Y-m-d'),
            'contract_type' => $choices->contractType,
            'is_fixed_term' => $choices->isFixedTerm(),
            'fixed_term_months' => $choices->fixedTermMonths,
            'fixed_term_reason' => $choices->fixedTermReason,
            'employer_legal_name' => config('hr.employer.legal_name'),
            'employer_address' => config('hr.employer.address'),
            'employer_registration' => config('hr.employer.registration'),
            'signatory_name' => config('hr.employer.signatory_name'),
            'signatory_title' => config('hr.employer.signatory_title'),
            'logo_path' => $logoPath,
            'review_notices' => $this->reviewNotices($employee),
        ];

        if ($choices->isFixedTerm()) {
            $endDate = $this->calculateFixedTermEndDate($startDate, (int) $choices->fixedTermMonths);
            $payload['end_date'] = $endDate;
            $payload['end_date_formatted'] = $endDate->format('Y-m-d');
        }

        return $payload;
    }

    public function calculateFixedTermEndDate(Carbon $startDate, int $months): Carbon
    {
        return $startDate->copy()->addMonths($months)->subDay();
    }

    public function renderArabicPdf(Employee $employee, EmploymentContractExportChoices $choices): string
    {
        $payload = $this->buildPayload($employee, $choices);

        return Pdf::loadView('pdf.employment-contract-ar', $payload)
            ->setPaper('a4')
            ->output();
    }

    public function renderEnglishPdf(Employee $employee, EmploymentContractExportChoices $choices): string
    {
        $payload = $this->buildPayload($employee, $choices);

        return Pdf::loadView('pdf.employment-contract-en', $payload)
            ->setPaper('a4')
            ->output();
    }

    public function renderArabicHtml(Employee $employee, EmploymentContractExportChoices $choices): string
    {
        $payload = $this->buildPayload($employee, $choices);

        return View::make('pdf.employment-contract-ar', $payload)->render();
    }

    public function renderEnglishHtml(Employee $employee, EmploymentContractExportChoices $choices): string
    {
        $payload = $this->buildPayload($employee, $choices);

        return View::make('pdf.employment-contract-en', $payload)->render();
    }

    public function suggestedDownloadBasename(Employee $employee, string $language): string
    {
        $slug = Str::slug($employee->name) ?: 'employee';

        return "employment-contract-draft-{$slug}-{$language}-".now()->format('Y-m-d');
    }

    protected function resolveStartDate(Employee $employee, EmploymentContractExportChoices $choices): ?Carbon
    {
        if ($employee->start_date) {
            return $employee->start_date instanceof Carbon
                ? $employee->start_date->copy()
                : Carbon::parse($employee->start_date);
        }

        return $choices->startDateOverride?->copy();
    }

    protected function positiveAmount(mixed $value): bool
    {
        if ($value === null || $value === '') {
            return false;
        }

        return (float) $value > 0;
    }

    protected function formatMoney(mixed $value): string
    {
        return number_format((float) $value, 2, '.', ',');
    }

    protected function logoExists(): bool
    {
        return is_file($this->resolveLogoPath());
    }

    protected function resolveLogoPath(): string
    {
        $configured = (string) config('hr.logo_path');
        if ($configured !== '' && is_file($configured)) {
            return $configured;
        }

        return public_path($configured !== '' ? $configured : 'siglogo.png');
    }

    /**
     * @return list<string>
     */
    protected function reviewNotices(Employee $employee): array
    {
        $notices = [];

        if (blank(config('hr.employer.registration'))) {
            $notices[] = 'Employer registration details are not configured. Confirm the legal entity, Egyptian registration (if applicable), and social insurance establishment subscription with counsel before use.';
        }

        $notices[] = 'Minimum wage compliance has not been verified by this system. Review the full salary against applicable Egyptian minimum wage rules.';

        if (blank($employee->social_insurance_number)) {
            $notices[] = 'Social insurance number is missing on the employee record.';
        }

        $notices[] = 'This document is a draft only. Obtain review by an Egyptian employment lawyer before first use. Prepare four Arabic originals and required filings per Egyptian labour and social insurance law.';

        return $notices;
    }
}
