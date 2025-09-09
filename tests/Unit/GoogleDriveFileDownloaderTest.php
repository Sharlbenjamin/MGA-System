<?php

namespace Tests\Unit;

use App\Services\GoogleDriveFileDownloader;
use Google\Client;
use Google\Service\Drive;
use Google\Service\Drive\DriveFile;
use Google\Service\Exception as GoogleServiceException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

class GoogleDriveFileDownloaderTest extends TestCase
{
    use RefreshDatabase;

    private GoogleDriveFileDownloader $downloader;
    private $mockDriveService;
    private $mockClient;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Mock the Google Client and Drive Service
        $this->mockClient = Mockery::mock(Client::class);
        $this->mockDriveService = Mockery::mock(Drive::class);
        
        // Create a partial mock of the downloader to inject our mocked service
        $this->downloader = Mockery::mock(GoogleDriveFileDownloader::class)->makePartial();
        $this->downloader->shouldAllowMockingProtectedMethods();
        
        // Use reflection to set the private service property
        $reflection = new \ReflectionClass($this->downloader);
        $serviceProperty = $reflection->getProperty('service');
        $serviceProperty->setAccessible(true);
        $serviceProperty->setValue($this->downloader, $this->mockDriveService);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_successfully_downloads_pdf_file()
    {
        $fileId = 'test-file-id-123';
        $filename = 'test-document.pdf';
        $contentType = 'application/pdf';
        $fileContents = 'PDF file contents here';

        // Mock file metadata
        $mockFile = Mockery::mock(DriveFile::class);
        $mockFile->shouldReceive('getName')->andReturn($filename);
        $mockFile->shouldReceive('getMimeType')->andReturn($contentType);
        $mockFile->shouldReceive('getSize')->andReturn(1024);

        // Mock files->get for metadata
        $this->mockDriveService->files = Mockery::mock();
        $this->mockDriveService->files->shouldReceive('get')
            ->with($fileId, ['fields' => 'id,name,mimeType,size'])
            ->andReturn($mockFile);

        // Mock files->get for content download
        $mockResponse = Mockery::mock();
        $mockBody = Mockery::mock();
        $mockBody->shouldReceive('getContents')->andReturn($fileContents);
        $mockResponse->shouldReceive('getBody')->andReturn($mockBody);
        
        $this->mockDriveService->files->shouldReceive('get')
            ->with($fileId, ['alt' => 'media'])
            ->andReturn($mockResponse);

        $result = $this->downloader->downloadByFileId($fileId);

        $this->assertTrue($result['ok']);
        $this->assertEquals($filename, $result['filename']);
        $this->assertEquals($fileContents, $result['contents']);
        $this->assertEquals('pdf', $result['extension']);
        $this->assertEquals($contentType, $result['contentType']);
        $this->assertArrayNotHasKey('error', $result);
    }

    /** @test */
    public function it_successfully_downloads_image_file()
    {
        $fileId = 'test-image-id-456';
        $filename = 'test-image.jpg';
        $contentType = 'image/jpeg';
        $fileContents = 'JPEG image contents here';

        // Mock file metadata
        $mockFile = Mockery::mock(DriveFile::class);
        $mockFile->shouldReceive('getName')->andReturn($filename);
        $mockFile->shouldReceive('getMimeType')->andReturn($contentType);
        $mockFile->shouldReceive('getSize')->andReturn(2048);

        // Mock files->get for metadata
        $this->mockDriveService->files = Mockery::mock();
        $this->mockDriveService->files->shouldReceive('get')
            ->with($fileId, ['fields' => 'id,name,mimeType,size'])
            ->andReturn($mockFile);

        // Mock files->get for content download
        $mockResponse = Mockery::mock();
        $mockBody = Mockery::mock();
        $mockBody->shouldReceive('getContents')->andReturn($fileContents);
        $mockResponse->shouldReceive('getBody')->andReturn($mockBody);
        
        $this->mockDriveService->files->shouldReceive('get')
            ->with($fileId, ['alt' => 'media'])
            ->andReturn($mockResponse);

        $result = $this->downloader->downloadByFileId($fileId);

        $this->assertTrue($result['ok']);
        $this->assertEquals($filename, $result['filename']);
        $this->assertEquals($fileContents, $result['contents']);
        $this->assertEquals('jpg', $result['extension']);
        $this->assertEquals($contentType, $result['contentType']);
    }

    /** @test */
    public function it_rejects_html_content_type()
    {
        $fileId = 'test-html-id-789';
        $filename = 'test-page.html';
        $contentType = 'text/html';

        // Mock file metadata
        $mockFile = Mockery::mock(DriveFile::class);
        $mockFile->shouldReceive('getName')->andReturn($filename);
        $mockFile->shouldReceive('getMimeType')->andReturn($contentType);
        $mockFile->shouldReceive('getSize')->andReturn(512);

        // Mock files->get for metadata only (should not proceed to download)
        $this->mockDriveService->files = Mockery::mock();
        $this->mockDriveService->files->shouldReceive('get')
            ->with($fileId, ['fields' => 'id,name,mimeType,size'])
            ->andReturn($mockFile);

        $result = $this->downloader->downloadByFileId($fileId);

        $this->assertFalse($result['ok']);
        $this->assertStringContains('not allowed for download', $result['error']);
        $this->assertArrayNotHasKey('contents', $result);
    }

    /** @test */
    public function it_rejects_text_content_type()
    {
        $fileId = 'test-text-id-101';
        $filename = 'test-file.txt';
        $contentType = 'text/plain';

        // Mock file metadata
        $mockFile = Mockery::mock(DriveFile::class);
        $mockFile->shouldReceive('getName')->andReturn($filename);
        $mockFile->shouldReceive('getMimeType')->andReturn($contentType);
        $mockFile->shouldReceive('getSize')->andReturn(256);

        // Mock files->get for metadata only
        $this->mockDriveService->files = Mockery::mock();
        $this->mockDriveService->files->shouldReceive('get')
            ->with($fileId, ['fields' => 'id,name,mimeType,size'])
            ->andReturn($mockFile);

        $result = $this->downloader->downloadByFileId($fileId);

        $this->assertFalse($result['ok']);
        $this->assertStringContains('not allowed for download', $result['error']);
    }

    /** @test */
    public function it_rejects_unsupported_content_type()
    {
        $fileId = 'test-unsupported-id-202';
        $filename = 'test-file.xyz';
        $contentType = 'application/xyz';

        // Mock file metadata
        $mockFile = Mockery::mock(DriveFile::class);
        $mockFile->shouldReceive('getName')->andReturn($filename);
        $mockFile->shouldReceive('getMimeType')->andReturn($contentType);
        $mockFile->shouldReceive('getSize')->andReturn(128);

        // Mock files->get for metadata only
        $this->mockDriveService->files = Mockery::mock();
        $this->mockDriveService->files->shouldReceive('get')
            ->with($fileId, ['fields' => 'id,name,mimeType,size'])
            ->andReturn($mockFile);

        $result = $this->downloader->downloadByFileId($fileId);

        $this->assertFalse($result['ok']);
        $this->assertStringContains('not supported', $result['error']);
        $this->assertStringContains('Only PDF and image files are allowed', $result['error']);
    }

    /** @test */
    public function it_retries_on_google_service_exception()
    {
        $fileId = 'test-retry-id-303';
        $filename = 'test-document.pdf';
        $contentType = 'application/pdf';
        $fileContents = 'PDF file contents here';

        // Mock file metadata
        $mockFile = Mockery::mock(DriveFile::class);
        $mockFile->shouldReceive('getName')->andReturn($filename);
        $mockFile->shouldReceive('getMimeType')->andReturn($contentType);
        $mockFile->shouldReceive('getSize')->andReturn(1024);

        // Mock files->get for metadata
        $this->mockDriveService->files = Mockery::mock();
        $this->mockDriveService->files->shouldReceive('get')
            ->with($fileId, ['fields' => 'id,name,mimeType,size'])
            ->andReturn($mockFile);

        // Mock first download attempt to fail, second to succeed
        $mockResponse = Mockery::mock();
        $mockBody = Mockery::mock();
        $mockBody->shouldReceive('getContents')->andReturn($fileContents);
        $mockResponse->shouldReceive('getBody')->andReturn($mockBody);

        $googleException = new GoogleServiceException('Rate limit exceeded');
        
        $this->mockDriveService->files->shouldReceive('get')
            ->with($fileId, ['alt' => 'media'])
            ->once()
            ->andThrow($googleException);

        $this->mockDriveService->files->shouldReceive('get')
            ->with($fileId, ['alt' => 'media'])
            ->once()
            ->andReturn($mockResponse);

        $result = $this->downloader->downloadByFileId($fileId);

        $this->assertTrue($result['ok']);
        $this->assertEquals($fileContents, $result['contents']);
    }

    /** @test */
    public function it_fails_after_max_retries()
    {
        $fileId = 'test-max-retry-id-404';
        $filename = 'test-document.pdf';
        $contentType = 'application/pdf';

        // Mock file metadata
        $mockFile = Mockery::mock(DriveFile::class);
        $mockFile->shouldReceive('getName')->andReturn($filename);
        $mockFile->shouldReceive('getMimeType')->andReturn($contentType);
        $mockFile->shouldReceive('getSize')->andReturn(1024);

        // Mock files->get for metadata
        $this->mockDriveService->files = Mockery::mock();
        $this->mockDriveService->files->shouldReceive('get')
            ->with($fileId, ['fields' => 'id,name,mimeType,size'])
            ->andReturn($mockFile);

        // Mock all download attempts to fail
        $googleException = new GoogleServiceException('Service unavailable');
        
        $this->mockDriveService->files->shouldReceive('get')
            ->with($fileId, ['alt' => 'media'])
            ->times(3) // Initial attempt + 2 retries
            ->andThrow($googleException);

        $result = $this->downloader->downloadByFileId($fileId);

        $this->assertFalse($result['ok']);
        $this->assertStringContains('Failed to download file after 3 attempts', $result['error']);
    }

    /** @test */
    public function it_infers_extension_from_filename()
    {
        $fileId = 'test-extension-id-505';
        $filename = 'document-with-extension.pdf';
        $contentType = 'application/pdf';
        $fileContents = 'PDF contents';

        // Mock file metadata
        $mockFile = Mockery::mock(DriveFile::class);
        $mockFile->shouldReceive('getName')->andReturn($filename);
        $mockFile->shouldReceive('getMimeType')->andReturn($contentType);
        $mockFile->shouldReceive('getSize')->andReturn(1024);

        // Mock files->get for metadata
        $this->mockDriveService->files = Mockery::mock();
        $this->mockDriveService->files->shouldReceive('get')
            ->with($fileId, ['fields' => 'id,name,mimeType,size'])
            ->andReturn($mockFile);

        // Mock files->get for content download
        $mockResponse = Mockery::mock();
        $mockBody = Mockery::mock();
        $mockBody->shouldReceive('getContents')->andReturn($fileContents);
        $mockResponse->shouldReceive('getBody')->andReturn($mockBody);
        
        $this->mockDriveService->files->shouldReceive('get')
            ->with($fileId, ['alt' => 'media'])
            ->andReturn($mockResponse);

        $result = $this->downloader->downloadByFileId($fileId);

        $this->assertEquals('pdf', $result['extension']);
    }

    /** @test */
    public function it_infers_extension_from_content_type_when_filename_has_no_extension()
    {
        $fileId = 'test-no-extension-id-606';
        $filename = 'document-without-extension';
        $contentType = 'image/png';
        $fileContents = 'PNG image contents';

        // Mock file metadata
        $mockFile = Mockery::mock(DriveFile::class);
        $mockFile->shouldReceive('getName')->andReturn($filename);
        $mockFile->shouldReceive('getMimeType')->andReturn($contentType);
        $mockFile->shouldReceive('getSize')->andReturn(2048);

        // Mock files->get for metadata
        $this->mockDriveService->files = Mockery::mock();
        $this->mockDriveService->files->shouldReceive('get')
            ->with($fileId, ['fields' => 'id,name,mimeType,size'])
            ->andReturn($mockFile);

        // Mock files->get for content download
        $mockResponse = Mockery::mock();
        $mockBody = Mockery::mock();
        $mockBody->shouldReceive('getContents')->andReturn($fileContents);
        $mockResponse->shouldReceive('getBody')->andReturn($mockBody);
        
        $this->mockDriveService->files->shouldReceive('get')
            ->with($fileId, ['alt' => 'media'])
            ->andReturn($mockResponse);

        $result = $this->downloader->downloadByFileId($fileId);

        $this->assertEquals('png', $result['extension']);
    }

    /** @test */
    public function it_handles_unexpected_exceptions()
    {
        $fileId = 'test-unexpected-id-707';

        // Mock files->get to throw unexpected exception
        $this->mockDriveService->files = Mockery::mock();
        $this->mockDriveService->files->shouldReceive('get')
            ->with($fileId, ['fields' => 'id,name,mimeType,size'])
            ->andThrow(new \Exception('Unexpected error'));

        $result = $this->downloader->downloadByFileId($fileId);

        $this->assertFalse($result['ok']);
        $this->assertStringContains('Unexpected error', $result['error']);
    }

    /** @test */
    public function it_gets_file_metadata_successfully()
    {
        $fileId = 'test-metadata-id-808';
        $filename = 'test-document.pdf';
        $contentType = 'application/pdf';
        $fileSize = 1024;
        $createdTime = '2023-01-01T00:00:00.000Z';
        $modifiedTime = '2023-01-02T00:00:00.000Z';
        $webViewLink = 'https://drive.google.com/file/d/test-metadata-id-808/view';

        // Mock file metadata
        $mockFile = Mockery::mock(DriveFile::class);
        $mockFile->shouldReceive('getId')->andReturn($fileId);
        $mockFile->shouldReceive('getName')->andReturn($filename);
        $mockFile->shouldReceive('getMimeType')->andReturn($contentType);
        $mockFile->shouldReceive('getSize')->andReturn($fileSize);
        $mockFile->shouldReceive('getCreatedTime')->andReturn($createdTime);
        $mockFile->shouldReceive('getModifiedTime')->andReturn($modifiedTime);
        $mockFile->shouldReceive('getWebViewLink')->andReturn($webViewLink);

        // Mock files->get for metadata
        $this->mockDriveService->files = Mockery::mock();
        $this->mockDriveService->files->shouldReceive('get')
            ->with($fileId, ['fields' => 'id,name,mimeType,size,createdTime,modifiedTime,webViewLink'])
            ->andReturn($mockFile);

        $result = $this->downloader->getFileMetadata($fileId);

        $this->assertTrue($result['ok']);
        $this->assertEquals($fileId, $result['metadata']['id']);
        $this->assertEquals($filename, $result['metadata']['name']);
        $this->assertEquals($contentType, $result['metadata']['mimeType']);
        $this->assertEquals($fileSize, $result['metadata']['size']);
        $this->assertEquals($createdTime, $result['metadata']['createdTime']);
        $this->assertEquals($modifiedTime, $result['metadata']['modifiedTime']);
        $this->assertEquals($webViewLink, $result['metadata']['webViewLink']);
    }

    /** @test */
    public function it_checks_if_file_is_downloadable()
    {
        $fileId = 'test-downloadable-id-909';
        $filename = 'test-document.pdf';
        $contentType = 'application/pdf';

        // Mock file metadata
        $mockFile = Mockery::mock(DriveFile::class);
        $mockFile->shouldReceive('getId')->andReturn($fileId);
        $mockFile->shouldReceive('getName')->andReturn($filename);
        $mockFile->shouldReceive('getMimeType')->andReturn($contentType);
        $mockFile->shouldReceive('getSize')->andReturn(1024);
        $mockFile->shouldReceive('getCreatedTime')->andReturn('2023-01-01T00:00:00.000Z');
        $mockFile->shouldReceive('getModifiedTime')->andReturn('2023-01-02T00:00:00.000Z');
        $mockFile->shouldReceive('getWebViewLink')->andReturn('https://drive.google.com/file/d/test-downloadable-id-909/view');

        // Mock files->get for metadata
        $this->mockDriveService->files = Mockery::mock();
        $this->mockDriveService->files->shouldReceive('get')
            ->with($fileId, ['fields' => 'id,name,mimeType,size,createdTime,modifiedTime,webViewLink'])
            ->andReturn($mockFile);

        $result = $this->downloader->isDownloadable($fileId);

        $this->assertTrue($result['downloadable']);
        $this->assertArrayNotHasKey('reason', $result);
    }

    /** @test */
    public function it_checks_if_file_is_not_downloadable()
    {
        $fileId = 'test-not-downloadable-id-101';
        $filename = 'test-page.html';
        $contentType = 'text/html';

        // Mock file metadata
        $mockFile = Mockery::mock(DriveFile::class);
        $mockFile->shouldReceive('getId')->andReturn($fileId);
        $mockFile->shouldReceive('getName')->andReturn($filename);
        $mockFile->shouldReceive('getMimeType')->andReturn($contentType);
        $mockFile->shouldReceive('getSize')->andReturn(512);
        $mockFile->shouldReceive('getCreatedTime')->andReturn('2023-01-01T00:00:00.000Z');
        $mockFile->shouldReceive('getModifiedTime')->andReturn('2023-01-02T00:00:00.000Z');
        $mockFile->shouldReceive('getWebViewLink')->andReturn('https://drive.google.com/file/d/test-not-downloadable-id-101/view');

        // Mock files->get for metadata
        $this->mockDriveService->files = Mockery::mock();
        $this->mockDriveService->files->shouldReceive('get')
            ->with($fileId, ['fields' => 'id,name,mimeType,size,createdTime,modifiedTime,webViewLink'])
            ->andReturn($mockFile);

        $result = $this->downloader->isDownloadable($fileId);

        $this->assertFalse($result['downloadable']);
        $this->assertStringContains('not allowed for download', $result['reason']);
    }

    /** @test */
    public function it_returns_allowed_content_types()
    {
        $allowedTypes = GoogleDriveFileDownloader::getAllowedContentTypes();

        $this->assertIsArray($allowedTypes);
        $this->assertArrayHasKey('pdf', $allowedTypes);
        $this->assertArrayHasKey('image', $allowedTypes);
        $this->assertContains('application/pdf', $allowedTypes['pdf']);
        $this->assertContains('image/jpeg', $allowedTypes['image']);
    }

    /** @test */
    public function it_returns_rejected_content_types()
    {
        $rejectedTypes = GoogleDriveFileDownloader::getRejectedContentTypes();

        $this->assertIsArray($rejectedTypes);
        $this->assertContains('text/html', $rejectedTypes);
        $this->assertContains('text/plain', $rejectedTypes);
        $this->assertContains('application/json', $rejectedTypes);
    }
}
