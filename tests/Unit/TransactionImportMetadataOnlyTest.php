<?php

namespace Tests\Unit;

use App\Services\TransactionImportService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class TransactionImportMetadataOnlyTest extends TestCase
{
    #[Test]
    public function import_method_accepts_metadata_only_flag_defaulting_to_false(): void
    {
        $method = new \ReflectionMethod(TransactionImportService::class, 'import');
        $param = collect($method->getParameters())->first(fn (\ReflectionParameter $p) => $p->getName() === 'metadataOnly');

        $this->assertNotNull($param);
        $this->assertTrue($param->isOptional());
        $this->assertFalse($param->getDefaultValue());
    }

    #[Test]
    public function create_transaction_from_row_accepts_metadata_only_flag(): void
    {
        $method = new \ReflectionMethod(TransactionImportService::class, 'createTransactionFromRow');
        $param = collect($method->getParameters())->first(fn (\ReflectionParameter $p) => $p->getName() === 'metadataOnly');

        $this->assertNotNull($param);
        $this->assertTrue($param->isOptional());
        $this->assertFalse($param->getDefaultValue());
    }

    #[Test]
    public function import_implementation_skips_optional_links_when_metadata_only(): void
    {
        $source = file_get_contents(
            (new \ReflectionClass(TransactionImportService::class))->getFileName()
        );

        $this->assertStringContainsString('if (! $metadataOnly)', $source);
        $this->assertStringContainsString('$this->applyOptionalLinks($transaction, $row, $statsService)', $source);
        $this->assertStringContainsString('$metadataOnly ? null : $this->resolveRelatedId($row, $relatedType)', $source);
    }
}
