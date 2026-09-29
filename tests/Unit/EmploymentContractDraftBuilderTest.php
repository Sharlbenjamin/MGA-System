<?php

namespace Tests\Unit;

use App\Data\Hr\EmploymentContractExportChoices;
use App\Models\Employee;
use App\Models\JobTitle;
use App\Services\Hr\EmploymentContractDraftBuilder;
use App\Services\Hr\EmploymentContractDraftException;
use Carbon\Carbon;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class EmploymentContractDraftBuilderTest extends TestCase
{
    private string $logoPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->logoPath = sys_get_temp_dir().'/hr-contract-test-logo.png';
        file_put_contents($this->logoPath, base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='
        ));

        Config::set('hr.employer.legal_name', 'Test Employer LLC');
        Config::set('hr.employer.address', 'Cairo, Egypt');
        Config::set('hr.employer.registration', null);
        Config::set('hr.employer.signatory_name', 'Jane Signatory');
        Config::set('hr.employer.signatory_title', 'Director');
        Config::set('hr.salary_currency', 'EGP');
        Config::set('hr.logo_path', $this->logoPath);
    }

    protected function tearDown(): void
    {
        if (is_file($this->logoPath)) {
            unlink($this->logoPath);
        }

        parent::tearDown();
    }

    public function test_build_payload_uses_employee_salary_and_insurance_fields(): void
    {
        $employee = $this->makeEmployee([
            'start_date' => Carbon::parse('2026-01-15'),
        ]);

        $choices = new EmploymentContractExportChoices(
            contractType: EmploymentContractExportChoices::TYPE_INDEFINITE,
            fixedTermMonths: null,
            fixedTermReason: null,
            startDateOverride: null,
        );

        $payload = (new EmploymentContractDraftBuilder)->buildPayload($employee, $choices);

        $this->assertSame('Ahmed Example', $payload['employee_name']);
        $this->assertSame('15,000.00', $payload['full_salary']);
        $this->assertSame('10,000.00', $payload['social_insurance_salary']);
        $this->assertSame('12345678901234', $payload['social_insurance_number']);
        $this->assertSame('2026-01-15', $payload['start_date_formatted']);
        $this->assertFalse($payload['is_fixed_term']);
    }

    public function test_fixed_term_end_date_calculation(): void
    {
        $builder = new EmploymentContractDraftBuilder;
        $start = Carbon::parse('2026-01-01');

        $this->assertSame('2026-03-31', $builder->calculateFixedTermEndDate($start, 3)->format('Y-m-d'));
        $this->assertSame('2026-06-30', $builder->calculateFixedTermEndDate($start, 6)->format('Y-m-d'));
        $this->assertSame('2026-12-31', $builder->calculateFixedTermEndDate($start, 12)->format('Y-m-d'));
    }

    public function test_fixed_term_requires_reason(): void
    {
        $employee = $this->makeEmployee();
        $choices = new EmploymentContractExportChoices(
            contractType: EmploymentContractExportChoices::TYPE_FIXED,
            fixedTermMonths: 6,
            fixedTermReason: null,
            startDateOverride: null,
        );

        $missing = (new EmploymentContractDraftBuilder)->missingFields($employee, $choices);

        $this->assertContains('Fixed-term reason (nature of work requiring fixed term)', $missing);
    }

    public function test_missing_required_data_blocks_export(): void
    {
        $employee = $this->makeEmployee([
            'full_salary' => null,
            'social_insurance_salary' => null,
            'social_insurance_number' => null,
        ]);

        $choices = new EmploymentContractExportChoices(
            contractType: EmploymentContractExportChoices::TYPE_INDEFINITE,
            fixedTermMonths: null,
            fixedTermReason: null,
            startDateOverride: null,
        );

        $this->expectException(EmploymentContractDraftException::class);
        (new EmploymentContractDraftBuilder)->buildPayload($employee, $choices);
    }

    public function test_indefinite_html_contains_three_month_notice(): void
    {
        $employee = $this->makeEmployee();
        $choices = new EmploymentContractExportChoices(
            contractType: EmploymentContractExportChoices::TYPE_INDEFINITE,
            fixedTermMonths: null,
            fixedTermReason: null,
            startDateOverride: null,
        );

        $html = (new EmploymentContractDraftBuilder)->renderEnglishHtml($employee, $choices);

        $this->assertStringContainsString('DRAFT', $html);
        $this->assertStringContainsString('three months', $html);
        $this->assertStringContainsString('Test Employer LLC', $html);
        $this->assertStringNotContainsString('nationality', strtolower($html));
    }

    public function test_arabic_html_contains_draft_mark_and_company(): void
    {
        $employee = $this->makeEmployee();
        $choices = new EmploymentContractExportChoices(
            contractType: EmploymentContractExportChoices::TYPE_INDEFINITE,
            fixedTermMonths: null,
            fixedTermReason: null,
            startDateOverride: null,
        );

        $html = (new EmploymentContractDraftBuilder)->renderArabicHtml($employee, $choices);

        $this->assertStringContainsString('مسودة', $html);
        $this->assertStringContainsString('Test Employer LLC', $html);
        $this->assertStringContainsString('ثلاثة أشهر', $html);
    }

    public function test_dompdf_outputs_pdf_for_both_languages(): void
    {
        if (! class_exists(\Barryvdh\DomPDF\ServiceProvider::class)) {
            $this->markTestSkipped('DomPDF not available.');
        }

        $employee = $this->makeEmployee();
        $choices = new EmploymentContractExportChoices(
            contractType: EmploymentContractExportChoices::TYPE_INDEFINITE,
            fixedTermMonths: null,
            fixedTermReason: null,
            startDateOverride: null,
        );

        $builder = new EmploymentContractDraftBuilder;

        $this->assertStringStartsWith('%PDF', $builder->renderArabicPdf($employee, $choices));
        $this->assertStringStartsWith('%PDF', $builder->renderEnglishPdf($employee, $choices));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function makeEmployee(array $overrides = []): Employee
    {
        $employee = new Employee(array_merge([
            'name' => 'Ahmed Example',
            'full_salary' => 15000,
            'social_insurance_salary' => 10000,
            'social_insurance_number' => '12345678901234',
            'start_date' => Carbon::parse('2026-02-01'),
            'national_id' => '29801011234567',
        ], $overrides));

        $employee->setRelation('jobTitle', new JobTitle(['name' => 'Software Engineer']));

        return $employee;
    }
}
