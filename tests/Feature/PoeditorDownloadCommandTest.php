<?php

namespace WebHappens\LaravelPoSync\Tests\Feature;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use WebHappens\LaravelPoSync\Tests\TestCase;

class PoeditorDownloadCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Configure POEditor settings for tests
        config([
            'po-sync.poeditor.enabled' => true,
            'po-sync.poeditor.api_token' => 'test-api-token',
            'po-sync.poeditor.project_id' => '12345',
        ]);
    }

    /** @test */
    public function it_fails_when_poeditor_integration_is_not_enabled()
    {
        config(['po-sync.poeditor.enabled' => false]);

        $this->artisan('po-sync:download', ['--all' => true])
            ->assertFailed()
            ->expectsOutputToContain('POEditor integration is not enabled');
    }

    /** @test */
    public function it_fails_when_api_credentials_are_missing()
    {
        config([
            'po-sync.poeditor.api_token' => null,
            'po-sync.poeditor.project_id' => null,
        ]);

        $this->artisan('po-sync:download', ['--all' => true])
            ->assertFailed()
            ->expectsOutputToContain('POEditor API credentials not configured');
    }

    /** @test */
    public function it_fails_when_no_languages_specified()
    {
        $this->artisan('po-sync:download')
            ->assertFailed()
            ->expectsOutputToContain('No languages to download');
    }

    /** @test */
    public function it_downloads_po_file_from_poeditor()
    {
        Http::fake([
            'https://api.poeditor.com/v2/projects/export' => Http::response([
                'response' => ['status' => 'success', 'message' => 'OK'],
                'result' => ['url' => 'https://download.poeditor.com/test.po'],
            ], 200),
            'https://download.poeditor.com/test.po' => Http::response('PO file content here', 200),
        ]);

        config(['po-sync.languages' => [
            'fr' => ['label' => 'French', 'enabled' => true],
        ]]);

        $this->artisan('po-sync:download', ['--all' => true])->assertSuccessful();

        // Assert file was saved
        $this->assertTrue(File::exists($this->tempImportPath.'/fr.po'));
        $this->assertEquals('PO file content here', File::get($this->tempImportPath.'/fr.po'));

        // Assert correct API calls were made
        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.poeditor.com/v2/projects/export'
                && $request['api_token'] === 'test-api-token'
                && $request['id'] === '12345'
                && $request['language'] === 'fr'
                && $request['type'] === 'po';
        });
    }

    /** @test */
    public function it_downloads_specific_languages_when_provided_as_arguments()
    {
        Http::fake([
            'https://api.poeditor.com/v2/projects/export' => Http::response([
                'response' => ['status' => 'success', 'message' => 'OK'],
                'result' => ['url' => 'https://download.poeditor.com/test.po'],
            ], 200),
            'https://download.poeditor.com/test.po' => Http::response('PO content', 200),
        ]);

        config(['po-sync.languages' => [
            'fr' => ['label' => 'French', 'enabled' => true],
            'de' => ['label' => 'German', 'enabled' => true],
            'es' => ['label' => 'Spanish', 'enabled' => true],
        ]]);

        // Download only French and German
        $this->artisan('po-sync:download', ['lang' => ['fr', 'de']])->assertSuccessful();

        $this->assertTrue(File::exists($this->tempImportPath.'/fr.po'));
        $this->assertTrue(File::exists($this->tempImportPath.'/de.po'));
        $this->assertFalse(File::exists($this->tempImportPath.'/es.po'));
    }

    /** @test */
    public function it_downloads_all_enabled_languages_with_all_flag()
    {
        Http::fake([
            'https://api.poeditor.com/v2/projects/export' => Http::response([
                'response' => ['status' => 'success', 'message' => 'OK'],
                'result' => ['url' => 'https://download.poeditor.com/test.po'],
            ], 200),
            'https://download.poeditor.com/test.po' => Http::response('PO content', 200),
        ]);

        config(['po-sync.languages' => [
            'fr' => ['label' => 'French', 'enabled' => true],
            'de' => ['label' => 'German', 'enabled' => true],
        ]]);

        $this->artisan('po-sync:download', ['--all' => true])->assertSuccessful();

        $this->assertTrue(File::exists($this->tempImportPath.'/fr.po'));
        $this->assertTrue(File::exists($this->tempImportPath.'/de.po'));
    }

    /** @test */
    public function it_handles_api_error_responses()
    {
        Http::fake([
            'https://api.poeditor.com/v2/projects/export' => Http::response([
                'response' => ['status' => 'fail', 'message' => 'Invalid API token'],
            ], 200),
        ]);

        config(['po-sync.languages' => [
            'fr' => ['label' => 'French', 'enabled' => true],
        ]]);

        $this->artisan('po-sync:download', ['--all' => true])->assertFailed();
    }

    /** @test */
    public function it_handles_http_request_failures()
    {
        Http::fake([
            'https://api.poeditor.com/v2/projects/export' => Http::response('Server Error', 500),
        ]);

        config(['po-sync.languages' => [
            'fr' => ['label' => 'French', 'enabled' => true],
        ]]);

        $this->artisan('po-sync:download', ['--all' => true])->assertFailed();

        // File should not be created
        $this->assertFalse(File::exists($this->tempImportPath.'/fr.po'));
    }

    /** @test */
    public function it_handles_missing_download_url_in_response()
    {
        Http::fake([
            'https://api.poeditor.com/v2/projects/export' => Http::response([
                'response' => ['status' => 'success', 'message' => 'OK'],
                'result' => [], // No URL in result
            ], 200),
        ]);

        config(['po-sync.languages' => [
            'fr' => ['label' => 'French', 'enabled' => true],
        ]]);

        $this->artisan('po-sync:download', ['--all' => true])->assertFailed();
    }

    /** @test */
    public function it_creates_import_directory_if_not_exists()
    {
        File::deleteDirectory($this->tempImportPath);
        $this->assertFalse(File::exists($this->tempImportPath));

        Http::fake([
            'https://api.poeditor.com/v2/projects/export' => Http::response([
                'response' => ['status' => 'success', 'message' => 'OK'],
                'result' => ['url' => 'https://download.poeditor.com/test.po'],
            ], 200),
            'https://download.poeditor.com/test.po' => Http::response('PO content', 200),
        ]);

        config(['po-sync.languages' => [
            'fr' => ['label' => 'French', 'enabled' => true],
        ]]);

        $this->artisan('po-sync:download', ['--all' => true])->assertSuccessful();

        // Directory should be created
        $this->assertTrue(File::exists($this->tempImportPath));
        $this->assertTrue(File::exists($this->tempImportPath.'/fr.po'));
    }

    /** @test */
    public function it_auto_detects_languages_when_not_configured()
    {
        config(['po-sync.languages' => []]);

        // Create language directories for auto-detection
        File::makeDirectory($this->tempLangPath.'/fr', 0755, true);
        File::makeDirectory($this->tempLangPath.'/de', 0755, true);

        Http::fake([
            'https://api.poeditor.com/v2/projects/export' => Http::response([
                'response' => ['status' => 'success', 'message' => 'OK'],
                'result' => ['url' => 'https://download.poeditor.com/test.po'],
            ], 200),
            'https://download.poeditor.com/test.po' => Http::response('PO content', 200),
        ]);

        $this->artisan('po-sync:download', ['lang' => ['fr']])->assertSuccessful();

        $this->assertTrue(File::exists($this->tempImportPath.'/fr.po'));
    }

    /** @test */
    public function it_suggests_running_import_command_after_successful_download()
    {
        Http::fake([
            'https://api.poeditor.com/v2/projects/export' => Http::response([
                'response' => ['status' => 'success', 'message' => 'OK'],
                'result' => ['url' => 'https://download.poeditor.com/test.po'],
            ], 200),
            'https://download.poeditor.com/test.po' => Http::response('PO content', 200),
        ]);

        config(['po-sync.languages' => [
            'fr' => ['label' => 'French', 'enabled' => true],
        ]]);

        $this->artisan('po-sync:download', ['--all' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('php artisan po-sync:import');
    }
}
