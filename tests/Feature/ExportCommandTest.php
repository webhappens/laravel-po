<?php

namespace WebHappens\LaravelPo\Tests\Feature;

use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Test;
use WebHappens\LaravelPo\Tests\TestCase;

class ExportCommandTest extends TestCase
{
    #[Test]
    public function it_exports_translations_to_po_file()
    {
        // Create translation files
        $this->createTranslationFile('en', 'actions', [
            'save' => 'Save',
            'cancel' => 'Cancel',
            'delete' => 'Delete',
        ]);

        // Run export command
        $this->artisan('po:export')->assertSuccessful();

        // Assert PO file was created
        $this->assertTrue(File::exists($this->tempExportPath.'/en.po'));

        // Assert PO file contains translations
        $content = $this->getExportedPoFile('en');
        $this->assertStringContainsString('msgctxt "actions.save"', $content);
        $this->assertStringContainsString('msgid "Save"', $content);
    }

    #[Test]
    public function it_converts_laravel_placeholders_to_perl_brace_format()
    {
        $this->createTranslationFile('en', 'messages', [
            'welcome' => 'Hello :name, you have :count messages',
        ]);

        $this->artisan('po:export')->assertSuccessful();

        $content = $this->getExportedPoFile('en');
        // Should convert :name to {name} and :count to {count}
        $this->assertStringContainsString('Hello {name}, you have {count} messages', $content);
        $this->assertStringNotContainsString(':name', $content);
        $this->assertStringNotContainsString(':count', $content);
    }

    #[Test]
    public function it_excludes_configured_translation_groups()
    {
        // Configure excluded groups
        config(['po.excluded_groups' => ['auth', 'validation']]);

        $this->createTranslationFile('en', 'actions', ['save' => 'Save']);
        $this->createTranslationFile('en', 'auth', ['failed' => 'These credentials do not match']);
        $this->createTranslationFile('en', 'validation', ['required' => 'The :attribute field is required']);

        $this->artisan('po:export')->assertSuccessful();

        $content = $this->getExportedPoFile('en');

        // Should include actions
        $this->assertStringContainsString('actions.save', $content);

        // Should exclude auth and validation
        $this->assertStringNotContainsString('auth.failed', $content);
        $this->assertStringNotContainsString('validation.required', $content);
    }

    #[Test]
    public function it_handles_nested_translations()
    {
        $this->createTranslationFile('en', 'messages', [
            'welcome' => 'Welcome',
            'nested' => [
                'hello' => 'Hello World',
                'goodbye' => 'Goodbye',
            ],
        ]);

        $this->artisan('po:export')->assertSuccessful();

        $content = $this->getExportedPoFile('en');

        // Should flatten nested translations with dot notation
        $this->assertStringContainsString('msgctxt "messages.welcome"', $content);
        $this->assertStringContainsString('msgctxt "messages.nested.hello"', $content);
        $this->assertStringContainsString('msgctxt "messages.nested.goodbye"', $content);
    }

    #[Test]
    public function it_exports_specific_language_when_argument_provided()
    {
        // Create translations for multiple languages
        $this->createTranslationFile('en', 'actions', ['save' => 'Save']);
        $this->createTranslationFile('fr', 'actions', ['save' => 'Enregistrer']);

        // Configure languages
        config(['po.languages' => [
            'en' => ['label' => 'English', 'enabled' => true],
            'fr' => ['label' => 'French', 'enabled' => true],
        ]]);

        // Export only French
        $this->artisan('po:export', ['lang' => ['fr']])->assertSuccessful();

        // Should only create French PO file
        $this->assertFalse(File::exists($this->tempExportPath.'/en.po'));
        $this->assertTrue(File::exists($this->tempExportPath.'/fr.po'));
    }

    #[Test]
    public function it_creates_export_directory_if_not_exists()
    {
        // Delete export directory
        File::deleteDirectory($this->tempExportPath);

        $this->assertFalse(File::exists($this->tempExportPath));

        $this->createTranslationFile('en', 'actions', ['save' => 'Save']);

        $this->artisan('po:export')->assertSuccessful();

        // Directory should be created
        $this->assertTrue(File::exists($this->tempExportPath));
        $this->assertTrue(File::exists($this->tempExportPath.'/en.po'));
    }

    #[Test]
    public function it_auto_detects_languages_when_not_configured()
    {
        // Don't configure languages, let it auto-detect
        config(['po.languages' => []]);

        // Create translation directories
        $this->createTranslationFile('en', 'actions', ['save' => 'Save']);
        $this->createTranslationFile('fr', 'actions', ['save' => 'Enregistrer']);
        $this->createTranslationFile('de', 'actions', ['save' => 'Speichern']);

        // Export should work with all detected languages
        $this->artisan('po:export', ['lang' => ['fr']])->assertSuccessful();

        $this->assertTrue(File::exists($this->tempExportPath.'/fr.po'));
    }

    #[Test]
    public function it_skips_empty_translation_values()
    {
        $this->createTranslationFile('en', 'messages', [
            'filled' => 'This has content',
            'empty' => '',
            'null_value' => null,
        ]);

        $this->artisan('po:export')->assertSuccessful();

        $content = $this->getExportedPoFile('en');

        // Should include filled value
        $this->assertStringContainsString('messages.filled', $content);

        // Should not include empty or null values
        $this->assertStringNotContainsString('messages.empty', $content);
        $this->assertStringNotContainsString('messages.null_value', $content);
    }

    #[Test]
    public function it_clears_export_directory_with_clear_flag_and_force()
    {
        $this->createTranslationFile('en', 'messages', [
            'hello' => 'Hello',
        ]);

        // Create some existing files in export directory
        File::put($this->tempExportPath.'/old-file.po', 'old content');
        File::put($this->tempExportPath.'/another-old.po', 'old content');

        $this->assertTrue(File::exists($this->tempExportPath.'/old-file.po'));
        $this->assertTrue(File::exists($this->tempExportPath.'/another-old.po'));

        $this->artisan('po:export', ['--clear' => true, '--force' => true])->assertSuccessful();

        // Old files should be deleted
        $this->assertFalse(File::exists($this->tempExportPath.'/old-file.po'));
        $this->assertFalse(File::exists($this->tempExportPath.'/another-old.po'));

        // New file should be created
        $this->assertTrue(File::exists($this->tempExportPath.'/en.po'));
    }

    #[Test]
    public function it_prompts_for_confirmation_when_clearing_without_force()
    {
        $this->createTranslationFile('en', 'messages', [
            'hello' => 'Hello',
        ]);

        File::put($this->tempExportPath.'/old-file.po', 'old content');

        // Simulate user declining the confirmation
        $this->artisan('po:export', ['--clear' => true])
            ->expectsConfirmation('Do you want to continue?', 'no')
            ->expectsOutputToContain('Operation cancelled.')
            ->assertFailed();

        // Old file should still exist
        $this->assertTrue(File::exists($this->tempExportPath.'/old-file.po'));
    }

    #[Test]
    public function it_proceeds_when_user_confirms_clear()
    {
        $this->createTranslationFile('en', 'messages', [
            'hello' => 'Hello',
        ]);

        File::put($this->tempExportPath.'/old-file.po', 'old content');

        // Simulate user accepting the confirmation
        $this->artisan('po:export', ['--clear' => true])
            ->expectsConfirmation('Do you want to continue?', 'yes')
            ->assertSuccessful();

        // Old file should be deleted
        $this->assertFalse(File::exists($this->tempExportPath.'/old-file.po'));

        // New file should be created
        $this->assertTrue(File::exists($this->tempExportPath.'/en.po'));
    }

    #[Test]
    public function it_handles_clear_flag_when_directory_is_already_empty()
    {
        $this->createTranslationFile('en', 'messages', [
            'hello' => 'Hello',
        ]);

        // Export directory is already empty
        $this->assertEmpty(File::files($this->tempExportPath));

        $this->artisan('po:export', ['--clear' => true, '--force' => true])
            ->expectsOutputToContain('The export directory is already empty.')
            ->assertSuccessful();

        // New file should be created
        $this->assertTrue(File::exists($this->tempExportPath.'/en.po'));
    }

    #[Test]
    public function it_exports_all_enabled_languages_with_all_flag()
    {
        // Create translations for multiple languages
        $this->createTranslationFile('en', 'actions', ['save' => 'Save']);
        $this->createTranslationFile('fr', 'actions', ['save' => 'Enregistrer']);
        $this->createTranslationFile('de', 'actions', ['save' => 'Speichern']);

        // Configure languages
        config(['po.languages' => [
            'en' => ['label' => 'English', 'enabled' => true],
            'fr' => ['label' => 'French', 'enabled' => true],
            'de' => ['label' => 'German', 'enabled' => true],
        ]]);

        // Export all languages
        $this->artisan('po:export', ['--all' => true])->assertSuccessful();

        // All PO files should be created
        $this->assertTrue(File::exists($this->tempExportPath.'/en.po'));
        $this->assertTrue(File::exists($this->tempExportPath.'/fr.po'));
        $this->assertTrue(File::exists($this->tempExportPath.'/de.po'));
    }

    #[Test]
    public function it_only_exports_enabled_languages_with_all_flag()
    {
        // Create translations for multiple languages
        $this->createTranslationFile('en', 'actions', ['save' => 'Save']);
        $this->createTranslationFile('fr', 'actions', ['save' => 'Enregistrer']);
        $this->createTranslationFile('de', 'actions', ['save' => 'Speichern']);

        // Configure languages with one disabled
        config(['po.languages' => [
            'en' => ['label' => 'English', 'enabled' => true],
            'fr' => ['label' => 'French', 'enabled' => true],
            'de' => ['label' => 'German', 'enabled' => false],
        ]]);

        // Export all enabled languages
        $this->artisan('po:export', ['--all' => true])->assertSuccessful();

        // Only enabled languages should be exported
        $this->assertTrue(File::exists($this->tempExportPath.'/en.po'));
        $this->assertTrue(File::exists($this->tempExportPath.'/fr.po'));
        $this->assertFalse(File::exists($this->tempExportPath.'/de.po'));
    }
}
