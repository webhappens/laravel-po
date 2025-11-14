<?php

namespace WebHappens\LaravelPo\Tests;

use Illuminate\Support\Facades\File;
use Orchestra\Testbench\TestCase as Orchestra;
use WebHappens\LaravelPo\PoServiceProvider;

abstract class TestCase extends Orchestra
{
    protected string $tempLangPath;
    protected string $tempExportPath;
    protected string $tempImportPath;

    protected function setUp(): void
    {
        parent::setUp();

        // Create temporary directories for testing
        $this->tempLangPath = sys_get_temp_dir().'/laravel-po-test-'.uniqid();
        $this->tempExportPath = $this->tempLangPath.'/export';
        $this->tempImportPath = $this->tempLangPath.'/import';

        File::makeDirectory($this->tempLangPath, 0755, true);
        File::makeDirectory($this->tempExportPath, 0755, true);
        File::makeDirectory($this->tempImportPath, 0755, true);

        // Override config with test paths
        config([
            'po.paths.lang' => $this->tempLangPath,
            'po.paths.export' => $this->tempExportPath,
            'po.paths.import' => $this->tempImportPath,
        ]);
    }

    protected function tearDown(): void
    {
        // Clean up temporary directories
        if (File::exists($this->tempLangPath)) {
            File::deleteDirectory($this->tempLangPath);
        }

        parent::tearDown();
    }

    protected function getPackageProviders($app): array
    {
        return [
            PoServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        // Set default locale
        $app->setLocale('en');
    }

    /**
     * Create a test translation file with the given translations
     */
    protected function createTranslationFile(string $locale, string $group, array $translations): void
    {
        $localeDir = $this->tempLangPath.'/'.$locale;

        if (! File::exists($localeDir)) {
            File::makeDirectory($localeDir, 0755, true);
        }

        $content = "<?php\n\nreturn ".var_export($translations, true).";\n";
        File::put($localeDir.'/'.$group.'.php', $content);
    }

    /**
     * Create a test PO file with the given content
     */
    protected function createPoFile(string $locale, string $content): void
    {
        File::put($this->tempImportPath.'/'.$locale.'.po', $content);
    }

    /**
     * Get the contents of an exported PO file
     */
    protected function getExportedPoFile(string $locale): string
    {
        return File::get($this->tempExportPath.'/'.$locale.'.po');
    }

    /**
     * Get the translations from a generated PHP file
     */
    protected function getGeneratedTranslations(string $locale, string $group): array
    {
        $filePath = $this->tempLangPath.'/'.$locale.'/'.$group.'.php';

        if (! File::exists($filePath)) {
            return [];
        }

        return include $filePath;
    }
}
