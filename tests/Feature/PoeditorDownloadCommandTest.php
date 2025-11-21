<?php

namespace WebHappens\LaravelPo\Tests\Feature;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use WebHappens\LaravelPo\Tests\TestCase;

class PoeditorDownloadCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Configure POEditor settings for tests
        config([
            'po.poeditor.enabled' => true,
            'po.poeditor.api_token' => 'test-api-token',
            'po.poeditor.project_id' => '12345',
        ]);
    }

    #[Test]
    public function it_fails_when_poeditor_integration_is_not_enabled()
    {
        config(['po.poeditor.enabled' => false]);

        $this->artisan('po:download', ['--all' => true])
            ->assertFailed()
            ->expectsOutputToContain('POEditor integration is not enabled');
    }

    #[Test]
    public function it_fails_when_api_credentials_are_missing()
    {
        config([
            'po.poeditor.api_token' => null,
            'po.poeditor.project_id' => null,
        ]);

        $this->artisan('po:download', ['--all' => true])
            ->assertFailed()
            ->expectsOutputToContain('POEditor API credentials not configured');
    }

    #[Test]
    public function it_fails_when_no_languages_specified()
    {
        $this->artisan('po:download')
            ->assertFailed()
            ->expectsOutputToContain('No languages to download');
    }

    #[Test]
    public function it_downloads_po_file_from_poeditor()
    {
        Http::fake([
            'https://api.poeditor.com/v2/projects/export' => Http::response([
                'response' => ['status' => 'success', 'message' => 'OK'],
                'result' => ['url' => 'https://download.poeditor.com/test.po'],
            ], 200),
            'https://download.poeditor.com/test.po' => Http::response('PO file content here', 200),
        ]);

        config(['po.languages' => [
            'fr' => ['label' => 'French', 'enabled' => true],
        ]]);

        $this->artisan('po:download', ['--active' => true])->assertSuccessful();

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

    #[Test]
    public function it_downloads_specific_languages_when_provided_as_arguments()
    {
        Http::fake([
            'https://api.poeditor.com/v2/projects/export' => Http::response([
                'response' => ['status' => 'success', 'message' => 'OK'],
                'result' => ['url' => 'https://download.poeditor.com/test.po'],
            ], 200),
            'https://download.poeditor.com/test.po' => Http::response('PO content', 200),
        ]);

        config(['po.languages' => [
            'fr' => ['label' => 'French', 'enabled' => true],
            'de' => ['label' => 'German', 'enabled' => true],
            'es' => ['label' => 'Spanish', 'enabled' => true],
        ]]);

        // Download only French and German
        $this->artisan('po:download', ['lang' => ['fr', 'de']])->assertSuccessful();

        $this->assertTrue(File::exists($this->tempImportPath.'/fr.po'));
        $this->assertTrue(File::exists($this->tempImportPath.'/de.po'));
        $this->assertFalse(File::exists($this->tempImportPath.'/es.po'));
    }

    #[Test]
    public function it_downloads_new_language_not_in_app_config()
    {
        Http::fake([
            'https://api.poeditor.com/v2/projects/export' => Http::response([
                'response' => ['status' => 'success', 'message' => 'OK'],
                'result' => ['url' => 'https://download.poeditor.com/test.po'],
            ], 200),
            'https://download.poeditor.com/test.po' => Http::response('PO content', 200),
        ]);

        config(['po.languages' => [
            'fr' => ['label' => 'French', 'enabled' => true],
        ]]);

        // Download Japanese which is not in the config
        $this->artisan('po:download', ['lang' => ['ja']])->assertSuccessful();

        $this->assertTrue(File::exists($this->tempImportPath.'/ja.po'));
    }

    #[Test]
    public function it_downloads_all_languages_from_poeditor_with_all_flag()
    {
        Http::fake([
            'https://api.poeditor.com/v2/languages/list' => Http::response([
                'response' => ['status' => 'success', 'message' => 'OK'],
                'result' => [
                    'languages' => [
                        ['code' => 'fr', 'name' => 'French'],
                        ['code' => 'de', 'name' => 'German'],
                        ['code' => 'es', 'name' => 'Spanish'],
                    ],
                ],
            ], 200),
            'https://api.poeditor.com/v2/projects/export' => Http::response([
                'response' => ['status' => 'success', 'message' => 'OK'],
                'result' => ['url' => 'https://download.poeditor.com/test.po'],
            ], 200),
            'https://download.poeditor.com/test.po' => Http::response('PO content', 200),
        ]);

        config(['po.languages' => [
            'fr' => ['label' => 'French', 'enabled' => true],
        ]]);

        $this->artisan('po:download', ['--all' => true])->assertSuccessful();

        // Should download all languages from POEditor, not just enabled ones
        $this->assertTrue(File::exists($this->tempImportPath.'/fr.po'));
        $this->assertTrue(File::exists($this->tempImportPath.'/de.po'));
        $this->assertTrue(File::exists($this->tempImportPath.'/es.po'));

        // Verify languages list API was called
        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.poeditor.com/v2/languages/list'
                && $request['api_token'] === 'test-api-token'
                && $request['id'] === '12345';
        });
    }

    #[Test]
    public function it_downloads_active_languages_with_active_flag()
    {
        Http::fake([
            'https://api.poeditor.com/v2/projects/export' => Http::response([
                'response' => ['status' => 'success', 'message' => 'OK'],
                'result' => ['url' => 'https://download.poeditor.com/test.po'],
            ], 200),
            'https://download.poeditor.com/test.po' => Http::response('PO content', 200),
        ]);

        config(['po.languages' => [
            'fr' => ['label' => 'French', 'enabled' => true],
            'de' => ['label' => 'German', 'enabled' => true],
            'es' => ['label' => 'Spanish', 'enabled' => false],
        ]]);

        $this->artisan('po:download', ['--active' => true])->assertSuccessful();

        // Should only download enabled languages from config
        $this->assertTrue(File::exists($this->tempImportPath.'/fr.po'));
        $this->assertTrue(File::exists($this->tempImportPath.'/de.po'));
        $this->assertFalse(File::exists($this->tempImportPath.'/es.po'));

        // Should NOT call languages list API
        Http::assertNotSent(function ($request) {
            return $request->url() === 'https://api.poeditor.com/v2/languages/list';
        });
    }

    #[Test]
    public function it_handles_api_error_responses()
    {
        Http::fake([
            'https://api.poeditor.com/v2/projects/export' => Http::response([
                'response' => ['status' => 'fail', 'message' => 'Invalid API token'],
            ], 200),
        ]);

        config(['po.languages' => [
            'fr' => ['label' => 'French', 'enabled' => true],
        ]]);

        $this->artisan('po:download', ['--active' => true])->assertFailed();
    }

    #[Test]
    public function it_handles_http_request_failures()
    {
        Http::fake([
            'https://api.poeditor.com/v2/projects/export' => Http::response('Server Error', 500),
        ]);

        config(['po.languages' => [
            'fr' => ['label' => 'French', 'enabled' => true],
        ]]);

        $this->artisan('po:download', ['--active' => true])->assertFailed();

        // File should not be created
        $this->assertFalse(File::exists($this->tempImportPath.'/fr.po'));
    }

    #[Test]
    public function it_handles_missing_download_url_in_response()
    {
        Http::fake([
            'https://api.poeditor.com/v2/projects/export' => Http::response([
                'response' => ['status' => 'success', 'message' => 'OK'],
                'result' => [], // No URL in result
            ], 200),
        ]);

        config(['po.languages' => [
            'fr' => ['label' => 'French', 'enabled' => true],
        ]]);

        $this->artisan('po:download', ['--active' => true])->assertFailed();
    }

    #[Test]
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

        config(['po.languages' => [
            'fr' => ['label' => 'French', 'enabled' => true],
        ]]);

        $this->artisan('po:download', ['--active' => true])->assertSuccessful();

        // Directory should be created
        $this->assertTrue(File::exists($this->tempImportPath));
        $this->assertTrue(File::exists($this->tempImportPath.'/fr.po'));
    }

    #[Test]
    public function it_auto_detects_languages_when_not_configured()
    {
        config(['po.languages' => []]);

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

        $this->artisan('po:download', ['lang' => ['fr']])->assertSuccessful();

        $this->assertTrue(File::exists($this->tempImportPath.'/fr.po'));
    }

    #[Test]
    public function it_suggests_running_import_command_after_successful_download()
    {
        Http::fake([
            'https://api.poeditor.com/v2/projects/export' => Http::response([
                'response' => ['status' => 'success', 'message' => 'OK'],
                'result' => ['url' => 'https://download.poeditor.com/test.po'],
            ], 200),
            'https://download.poeditor.com/test.po' => Http::response('PO content', 200),
        ]);

        config(['po.languages' => [
            'fr' => ['label' => 'French', 'enabled' => true],
        ]]);

        $this->artisan('po:download', ['--active' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('php artisan po:import');
    }

    #[Test]
    public function it_handles_api_error_when_fetching_languages_list()
    {
        Http::fake([
            'https://api.poeditor.com/v2/languages/list' => Http::response([
                'response' => ['status' => 'fail', 'message' => 'Invalid project ID'],
            ], 200),
        ]);

        $this->artisan('po:download', ['--all' => true])
            ->assertFailed();
    }

    #[Test]
    public function it_auto_detects_active_languages_when_not_configured()
    {
        config(['po.languages' => []]);

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

        $this->artisan('po:download', ['--active' => true])->assertSuccessful();

        // Should download auto-detected languages
        $this->assertTrue(File::exists($this->tempImportPath.'/fr.po'));
        $this->assertTrue(File::exists($this->tempImportPath.'/de.po'));
    }
}
