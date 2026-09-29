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
            $missing[] = 'Social insurance number (employee record)';
        }

        if (! $this->positiveAmount($employee->social_insurance_salary)) {
            $missing[] = 'Social insurance salary (employee record)';
        }

        if (blank(config('hr.salary_currency'))) {
            $missing[] = 'Salary currency';
        }

        $startDate = $this->resolveStartDate($employee, $choices);
        if ($startDate === null) {
            $missing[] = 'Contract start date';
        }

        if (blank($choices->employerLegalName)) {
            $missing[] = 'Employer legal name';
        }

        if (blank($choices->employerAddress)) {
            $missing[] = 'Employer address';
        }

        if (blank($choices->signatoryName)) {
            $missing[] = 'Signatory name';
        }

        if (blank($choices->signatoryTitle)) {
            $missing[] = 'Signatory title';
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
        $contractWage = (float) $employee->social_insurance_salary;

        $payload = [
            'is_draft' => true,
            'draft_date' => $draftDate,
            'draft_date_formatted' => $draftDate->format('Y-m-d'),
            'employee_name' => $employee->name,
            'employee_job_title' => $employee->jobTitle?->name ?? '',
            'employee_national_id' => filled($employee->national_id) ? $employee->national_id : null,
            'social_insurance_number' => $employee->social_insurance_number,
            'full_salary' => $this->formatMoney($contractWage),
            'full_salary_raw' => $contractWage,
            'social_insurance_salary' => $this->formatMoney($employee->social_insurance_salary),
            'social_insurance_salary_raw' => (float) $employee->social_insurance_salary,
            'salary_currency' => $currency,
            'start_date' => $startDate,
            'start_date_formatted' => $startDate->format('Y-m-d'),
            'contract_type' => $choices->contractType,
            'is_fixed_term' => $choices->isFixedTerm(),
            'fixed_term_months' => $choices->fixedTermMonths,
            'fixed_term_reason' => $choices->fixedTermReason,
            'employer_legal_name' => $choices->employerLegalName,
            'employer_address' => $choices->employerAddress,
            'employer_registration' => $choices->employerRegistration,
            'signatory_name' => $choices->signatoryName,
            'signatory_title' => $choices->signatoryTitle,
            'logo_path' => $logoPath,
            'review_notices' => $this->reviewNotices($employee, $choices),
            'review_notices_ar' => $this->reviewNoticesAr($employee, $choices),
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

        return app(EmploymentContractArabicPdfRenderer::class)->render($payload);
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
    protected function reviewNotices(Employee $employee, EmploymentContractExportChoices $choices): array
    {
        $notices = [];

        if (blank($choices->employerRegistration)) {
            $notices[] = 'Employer registration was not provided for this export. Confirm the legal entity, Egyptian registration (if applicable), and social insurance establishment subscription with counsel before use.';
        }

        $notices[] = 'Minimum wage compliance has not been verified by this system. Review the social insurance salary against applicable Egyptian minimum wage rules.';

        if ($this->positiveAmount($employee->full_salary)
            && (float) $employee->full_salary !== (float) $employee->social_insurance_salary) {
            $notices[] = 'Employee full salary on file differs from social insurance salary used in this contract. Confirm both amounts with counsel and payroll.';
        }

        $notices[] = 'This document is a draft only. Obtain review by an Egyptian employment lawyer before first use. Prepare four Arabic originals and required filings per Egyptian labour and social insurance law.';

        return $notices;
    }

    /**
     * @return list<string>
     */
    protected function reviewNoticesAr(Employee $employee, EmploymentContractExportChoices $choices): array
    {
        $notices = [];

        if (blank($choices->employerRegistration)) {
            $notices[] = 'لم يُذكر رقم تسجيل صاحب العمل في هذا التصدير. يُرجى التحقق من الكيان القانوني والتسجيل المصري (إن وجد) وملف اشتراك المنشأة لدى التأمينات مع المستشار القانوني قبل الاستخدام.';
        }

        $notices[] = 'لم يَتحقق النظام من مطابقة الأجر للحد الأدنى للأجور في مصر. راجع أجر التأمين الاجتماعي المستخدم في هذا العقد.';

        if ($this->positiveAmount($employee->full_salary)
            && (float) $employee->full_salary !== (float) $employee->social_insurance_salary) {
            $notices[] = 'الراتب الكامل المسجل للموظف يختلف عن أجر التأمين المستخدم في هذا العقد. يُرجى مراجعة المبالغ مع المستشار ومسؤول الرواتب.';
        }

        $notices[] = 'هذه الوثيقة مسودة فقط. يجب مراجعتها من محامٍ مصري متخصص في العمل قبل أول استخدام، وإعداد أربع نسخ عربية أصلية والإيداعات المطلوبة وفق قانون العمل والتأمينات.';

        return $notices;
    }
}
