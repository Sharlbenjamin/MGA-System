<?php

namespace Tests\Unit;

use App\Models\File;
use App\Services\DocumentPathResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentPathResolverTest extends TestCase
{
    use RefreshDatabase;

    private DocumentPathResolver $resolver;
    private File $file;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->resolver = new DocumentPathResolver();
        
        // Create a test file with mga_reference
        $this->file = new File([
            'mga_reference' => 'MG001AB',
            'status' => 'Active',
            'patient_id' => 1,
        ]);
        $this->file->save();
    }

    /** @test */
    public function it_returns_correct_directory_path_for_valid_categories()
    {
        $testCases = [
            'gops' => 'files/MG001AB/gops',
            'medical_reports' => 'files/MG001AB/medical_reports',
            'prescriptions' => 'files/MG001AB/prescriptions',
            'bills' => 'files/MG001AB/bills',
            'invoices' => 'files/MG001AB/invoices',
            'transactions/in' => 'files/MG001AB/transactions/in',
            'transactions/out' => 'files/MG001AB/transactions/out',
        ];

        foreach ($testCases as $category => $expectedPath) {
            $this->assertEquals($expectedPath, $this->resolver->dirFor($this->file, $category));
        }
    }

    /** @test */
    public function it_throws_exception_for_invalid_category()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid category 'invalid_category'. Valid categories are: gops, medical_reports, prescriptions, bills, invoices, transactions/in, transactions/out");

        $this->resolver->dirFor($this->file, 'invalid_category');
    }

    /** @test */
    public function it_throws_exception_when_file_has_no_mga_reference()
    {
        $fileWithoutReference = new File([
            'status' => 'Active',
            'patient_id' => 1,
        ]);
        $fileWithoutReference->save();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('File must have a mga_reference to create document paths');

        $this->resolver->dirFor($fileWithoutReference, 'gops');
    }

    /** @test */
    public function it_throws_exception_when_file_mga_reference_is_empty()
    {
        $fileWithEmptyReference = new File([
            'mga_reference' => '',
            'status' => 'Active',
            'patient_id' => 1,
        ]);
        $fileWithEmptyReference->save();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('File must have a mga_reference to create document paths');

        $this->resolver->dirFor($fileWithEmptyReference, 'gops');
    }

    /** @test */
    public function it_creates_directory_when_ensuring_path()
    {
        Storage::fake('public');

        $directory = $this->resolver->ensure($this->file, 'gops');

        $this->assertEquals('files/MG001AB/gops', $directory);
        Storage::disk('public')->assertExists('files/MG001AB/gops');
    }

    /** @test */
    public function it_creates_nested_directories_for_transactions()
    {
        Storage::fake('public');

        $directory = $this->resolver->ensure($this->file, 'transactions/in');

        $this->assertEquals('files/MG001AB/transactions/in', $directory);
        Storage::disk('public')->assertExists('files/MG001AB/transactions/in');
    }

    /** @test */
    public function it_returns_existing_directory_when_ensuring_path()
    {
        Storage::fake('public');
        
        // Create directory first
        Storage::disk('public')->makeDirectory('files/MG001AB/gops');

        $directory = $this->resolver->ensure($this->file, 'gops');

        $this->assertEquals('files/MG001AB/gops', $directory);
        Storage::disk('public')->assertExists('files/MG001AB/gops');
    }

    /** @test */
    public function it_returns_correct_path_for_document()
    {
        $path = $this->resolver->pathFor($this->file, 'gops', 'document.pdf');

        $this->assertEquals('files/MG001AB/gops/document.pdf', $path);
    }

    /** @test */
    public function it_ensures_directory_and_returns_full_path()
    {
        Storage::fake('public');

        $path = $this->resolver->ensurePathFor($this->file, 'medical_reports', 'report.pdf');

        $this->assertEquals('files/MG001AB/medical_reports/report.pdf', $path);
        Storage::disk('public')->assertExists('files/MG001AB/medical_reports');
    }

    /** @test */
    public function it_returns_absolute_path_for_directory()
    {
        $absolutePath = $this->resolver->absolutePathFor($this->file, 'bills');

        $expectedPath = storage_path('app/public/files/MG001AB/bills');
        $this->assertEquals($expectedPath, $absolutePath);
    }

    /** @test */
    public function it_checks_directory_existence_correctly()
    {
        Storage::fake('public');

        // Directory doesn't exist initially
        $this->assertFalse($this->resolver->directoryExists($this->file, 'invoices'));

        // Create directory
        Storage::disk('public')->makeDirectory('files/MG001AB/invoices');

        // Directory now exists
        $this->assertTrue($this->resolver->directoryExists($this->file, 'invoices'));
    }

    /** @test */
    public function it_returns_empty_array_when_directory_does_not_exist()
    {
        Storage::fake('public');

        $files = $this->resolver->getFilesInDirectory($this->file, 'prescriptions');

        $this->assertIsArray($files);
        $this->assertEmpty($files);
    }

    /** @test */
    public function it_returns_files_in_existing_directory()
    {
        Storage::fake('public');

        // Create directory and files
        Storage::disk('public')->makeDirectory('files/MG001AB/prescriptions');
        Storage::disk('public')->put('files/MG001AB/prescriptions/prescription1.pdf', 'content1');
        Storage::disk('public')->put('files/MG001AB/prescriptions/prescription2.pdf', 'content2');

        $files = $this->resolver->getFilesInDirectory($this->file, 'prescriptions');

        $this->assertCount(2, $files);
        $this->assertContains('files/MG001AB/prescriptions/prescription1.pdf', $files);
        $this->assertContains('files/MG001AB/prescriptions/prescription2.pdf', $files);
    }

    /** @test */
    public function it_returns_valid_categories()
    {
        $categories = DocumentPathResolver::getValidCategories();

        $expectedCategories = [
            'gops',
            'medical_reports',
            'prescriptions',
            'bills',
            'invoices',
            'transactions/in',
            'transactions/out',
        ];

        $this->assertEquals($expectedCategories, $categories);
    }

    /** @test */
    public function it_validates_categories_correctly()
    {
        $this->assertTrue(DocumentPathResolver::isValidCategory('gops'));
        $this->assertTrue(DocumentPathResolver::isValidCategory('medical_reports'));
        $this->assertTrue(DocumentPathResolver::isValidCategory('transactions/in'));
        $this->assertTrue(DocumentPathResolver::isValidCategory('transactions/out'));

        $this->assertFalse(DocumentPathResolver::isValidCategory('invalid'));
        $this->assertFalse(DocumentPathResolver::isValidCategory(''));
        $this->assertFalse(DocumentPathResolver::isValidCategory('transactions'));
    }

    /** @test */
    public function it_handles_special_characters_in_mga_reference()
    {
        $fileWithSpecialChars = new File([
            'mga_reference' => 'MG001-AB_Test',
            'status' => 'Active',
            'patient_id' => 1,
        ]);
        $fileWithSpecialChars->save();

        $directory = $this->resolver->dirFor($fileWithSpecialChars, 'gops');

        $this->assertEquals('files/MG001-AB_Test/gops', $directory);
    }

    /** @test */
    public function it_handles_different_file_instances()
    {
        $file2 = new File([
            'mga_reference' => 'MG002CD',
            'status' => 'Active',
            'patient_id' => 2,
        ]);
        $file2->save();

        $directory1 = $this->resolver->dirFor($this->file, 'bills');
        $directory2 = $this->resolver->dirFor($file2, 'bills');

        $this->assertEquals('files/MG001AB/bills', $directory1);
        $this->assertEquals('files/MG002CD/bills', $directory2);
        $this->assertNotEquals($directory1, $directory2);
    }
}
