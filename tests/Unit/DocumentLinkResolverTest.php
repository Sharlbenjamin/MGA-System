<?php

namespace Tests\Unit;

use App\Services\DocumentLinkResolver;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class DocumentLinkResolverTest extends TestCase
{
    private DocumentLinkResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resolver = new DocumentLinkResolver;
    }

    #[Test]
    public function primary_export_link_returns_first_available_candidate(): void
    {
        $google = 'https://drive.google.com/file/d/abc123/view';
        $signed = 'https://mga.example/docs/invoice/1?signature=abc&expires=123';

        $this->assertSame($google, $this->resolver->primaryExportLink([$google, $signed]));
        $this->assertSame($signed, $this->resolver->primaryExportLink([$signed]));
        $this->assertSame('', $this->resolver->primaryExportLink([]));
    }
}
