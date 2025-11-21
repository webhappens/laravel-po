<?php

namespace WebHappens\LaravelPo\Tests\Feature;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Test;
use WebHappens\LaravelPo\Events\TranslationsImported;
use WebHappens\LaravelPo\Tests\TestCase;

class ImportCommandTest extends TestCase
{
    #[Test]
    public function it_imports_po_file_to_php_translations()
    {
        $poContent = <<<'PO'
msgid ""
msgstr ""
"Language: fr\n"

msgctxt "actions.save"
msgid "Save"
msgstr "Enregistrer"

msgctxt "actions.cancel"
msgid "Cancel"
msgstr "Annuler"
PO;

        $this->createPoFile('fr', $poContent);

        config(['po.languages' => [
            'fr' => ['label' => 'French', 'enabled' => true],
        ]]);

        $this->artisan('po:import')->assertSuccessful();

        // Assert PHP file was created
        $translations = $this->getGeneratedTranslations('fr', 'actions');

        $this->assertEquals('Enregistrer', $translations['save']);
        $this->assertEquals('Annuler', $translations['cancel']);
    }

    #[Test]
    public function it_converts_perl_brace_placeholders_to_laravel_format()
    {
        $poContent = <<<'PO'
msgid ""
msgstr ""
"Language: fr\n"

msgctxt "messages.welcome"
msgid "Hello {name}, you have {count} messages"
msgstr "Bonjour {name}, vous avez {count} messages"
PO;

        $this->createPoFile('fr', $poContent);

        config(['po.languages' => [
            'fr' => ['label' => 'French', 'enabled' => true],
        ]]);

        $this->artisan('po:import')->assertSuccessful();

        $translations = $this->getGeneratedTranslations('fr', 'messages');

        // Should convert {name} to :name and {count} to :count
        $this->assertEquals('Bonjour :name, vous avez :count messages', $translations['welcome']);
    }

    #[Test]
    public function it_groups_translations_by_first_part_of_key()
    {
        $poContent = <<<'PO'
msgid ""
msgstr ""
"Language: fr\n"

msgctxt "actions.save"
msgid "Save"
msgstr "Enregistrer"

msgctxt "actions.delete"
msgid "Delete"
msgstr "Supprimer"

msgctxt "messages.welcome"
msgid "Welcome"
msgstr "Bienvenue"

msgctxt "messages.goodbye"
msgid "Goodbye"
msgstr "Au revoir"
PO;

        $this->createPoFile('fr', $poContent);

        config(['po.languages' => [
            'fr' => ['label' => 'French', 'enabled' => true],
        ]]);

        $this->artisan('po:import')->assertSuccessful();

        // Should create separate files for actions and messages
        $this->assertTrue(File::exists($this->tempLangPath.'/fr/actions.php'));
        $this->assertTrue(File::exists($this->tempLangPath.'/fr/messages.php'));

        $actions = $this->getGeneratedTranslations('fr', 'actions');
        $messages = $this->getGeneratedTranslations('fr', 'messages');

        $this->assertCount(2, $actions);
        $this->assertCount(2, $messages);
    }

    #[Test]
    public function it_merges_with_existing_translations_by_default()
    {
        // Create existing translation file
        $this->createTranslationFile('fr', 'actions', [
            'save' => 'Enregistrer',
            'edit' => 'Modifier',
            'delete' => 'Old Translation',
        ]);

        $poContent = <<<'PO'
msgid ""
msgstr ""
"Language: fr\n"

msgctxt "actions.delete"
msgid "Delete"
msgstr "Supprimer"

msgctxt "actions.cancel"
msgid "Cancel"
msgstr "Annuler"
PO;

        $this->createPoFile('fr', $poContent);

        config(['po.languages' => [
            'fr' => ['label' => 'French', 'enabled' => true],
        ]]);

        $this->artisan('po:import')->assertSuccessful();

        $translations = $this->getGeneratedTranslations('fr', 'actions');

        // Should keep existing 'save' and 'edit'
        $this->assertEquals('Enregistrer', $translations['save']);
        $this->assertEquals('Modifier', $translations['edit']);

        // Should update 'delete' with new value
        $this->assertEquals('Supprimer', $translations['delete']);

        // Should add new 'cancel'
        $this->assertEquals('Annuler', $translations['cancel']);
    }

    #[Test]
    public function it_replaces_existing_translations_with_replace_flag()
    {
        $this->createTranslationFile('fr', 'actions', [
            'save' => 'Enregistrer',
            'edit' => 'Modifier',
            'old_key' => 'This should be removed',
        ]);

        $poContent = <<<'PO'
msgid ""
msgstr ""
"Language: fr\n"

msgctxt "actions.save"
msgid "Save"
msgstr "Sauvegarder"

msgctxt "actions.cancel"
msgid "Cancel"
msgstr "Annuler"
PO;

        $this->createPoFile('fr', $poContent);

        config(['po.languages' => [
            'fr' => ['label' => 'French', 'enabled' => true],
        ]]);

        $this->artisan('po:import', ['--replace' => true])->assertSuccessful();

        $translations = $this->getGeneratedTranslations('fr', 'actions');

        // Should only contain imported translations
        $this->assertCount(2, $translations);
        $this->assertEquals('Sauvegarder', $translations['save']);
        $this->assertEquals('Annuler', $translations['cancel']);

        // Should not contain old keys
        $this->assertArrayNotHasKey('edit', $translations);
        $this->assertArrayNotHasKey('old_key', $translations);
    }

    #[Test]
    public function it_excludes_fuzzy_translations_by_default()
    {
        $poContent = <<<'PO'
msgid ""
msgstr ""
"Language: fr\n"

msgctxt "actions.save"
msgid "Save"
msgstr "Enregistrer"

#, fuzzy
msgctxt "actions.delete"
msgid "Delete"
msgstr "Supprimer (unsure)"
PO;

        $this->createPoFile('fr', $poContent);

        config(['po.languages' => [
            'fr' => ['label' => 'French', 'enabled' => true],
        ]]);

        $this->artisan('po:import')->assertSuccessful();

        $translations = $this->getGeneratedTranslations('fr', 'actions');

        // Should include non-fuzzy translation
        $this->assertArrayHasKey('save', $translations);

        // Should exclude fuzzy translation
        $this->assertArrayNotHasKey('delete', $translations);
    }

    #[Test]
    public function it_includes_fuzzy_translations_with_fuzzy_flag()
    {
        $poContent = <<<'PO'
msgid ""
msgstr ""
"Language: fr\n"

msgctxt "actions.save"
msgid "Save"
msgstr "Enregistrer"

#, fuzzy
msgctxt "actions.delete"
msgid "Delete"
msgstr "Supprimer (unsure)"
PO;

        $this->createPoFile('fr', $poContent);

        config(['po.languages' => [
            'fr' => ['label' => 'French', 'enabled' => true],
        ]]);

        $this->artisan('po:import', ['--fuzzy' => true])->assertSuccessful();

        $translations = $this->getGeneratedTranslations('fr', 'actions');

        // Should include both translations
        $this->assertArrayHasKey('save', $translations);
        $this->assertArrayHasKey('delete', $translations);
        $this->assertEquals('Supprimer (unsure)', $translations['delete']);
    }

    #[Test]
    public function it_filters_translations_with_only_option()
    {
        $poContent = <<<'PO'
msgid ""
msgstr ""
"Language: fr\n"

msgctxt "actions.save"
msgid "Save"
msgstr "Enregistrer"

msgctxt "actions.delete"
msgid "Delete"
msgstr "Supprimer"

msgctxt "messages.welcome"
msgid "Welcome"
msgstr "Bienvenue"
PO;

        $this->createPoFile('fr', $poContent);

        config(['po.languages' => [
            'fr' => ['label' => 'French', 'enabled' => true],
        ]]);

        $this->artisan('po:import', ['--only' => ['actions.*']])->assertSuccessful();

        // Should only import actions
        $this->assertTrue(File::exists($this->tempLangPath.'/fr/actions.php'));
        $this->assertFalse(File::exists($this->tempLangPath.'/fr/messages.php'));

        $translations = $this->getGeneratedTranslations('fr', 'actions');
        $this->assertCount(2, $translations);
    }

    #[Test]
    public function it_imports_specific_language_when_argument_provided()
    {
        // Create PO files for multiple languages
        $frPoContent = <<<'PO'
msgid ""
msgstr ""
"Language: fr\n"

msgctxt "actions.save"
msgid "Save"
msgstr "Enregistrer"
PO;

        $dePoContent = <<<'PO'
msgid ""
msgstr ""
"Language: de\n"

msgctxt "actions.save"
msgid "Save"
msgstr "Speichern"
PO;

        $this->createPoFile('fr', $frPoContent);
        $this->createPoFile('de', $dePoContent);

        config(['po.languages' => [
            'fr' => ['label' => 'French', 'enabled' => true],
            'de' => ['label' => 'German', 'enabled' => true],
        ]]);

        // Import only French
        $this->artisan('po:import', ['lang' => ['fr']])->assertSuccessful();

        // Should only import French
        $this->assertTrue(File::exists($this->tempLangPath.'/fr/actions.php'));
        $this->assertFalse(File::exists($this->tempLangPath.'/de/actions.php'));
    }

    #[Test]
    public function it_deletes_file_if_all_translations_are_empty()
    {
        // Create existing file
        $this->createTranslationFile('fr', 'actions', ['save' => 'Enregistrer']);

        $poContent = <<<'PO'
msgid ""
msgstr ""
"Language: fr\n"

msgctxt "actions.save"
msgid "Save"
msgstr ""
PO;

        $this->createPoFile('fr', $poContent);

        config(['po.languages' => [
            'fr' => ['label' => 'French', 'enabled' => true],
        ]]);

        $this->artisan('po:import', ['--replace' => true])->assertSuccessful();

        // File should be deleted since all translations are empty
        $this->assertFalse(File::exists($this->tempLangPath.'/fr/actions.php'));
    }

    #[Test]
    public function it_auto_detects_enabled_languages_when_not_configured()
    {
        config(['po.languages' => []]);

        // Create language directories
        File::makeDirectory($this->tempLangPath.'/fr', 0755, true);
        File::makeDirectory($this->tempLangPath.'/de', 0755, true);

        $poContent = <<<'PO'
msgid ""
msgstr ""
"Language: fr\n"

msgctxt "actions.save"
msgid "Save"
msgstr "Enregistrer"
PO;

        $this->createPoFile('fr', $poContent);

        $this->artisan('po:import')->assertSuccessful();

        $this->assertTrue(File::exists($this->tempLangPath.'/fr/actions.php'));
    }

    #[Test]
    public function it_creates_flat_structure_by_default()
    {
        $poContent = <<<'PO'
msgid ""
msgstr ""
"Language: fr\n"

msgctxt "user.name"
msgid "Name"
msgstr "Nom"

msgctxt "user.email"
msgid "Email"
msgstr "Email"

msgctxt "user.profile.bio"
msgid "Biography"
msgstr "Biographie"
PO;

        $this->createPoFile('fr', $poContent);

        config([
            'po.languages' => ['fr' => ['label' => 'French', 'enabled' => true]],
            'po.structure' => 'flat',
        ]);

        $this->artisan('po:import')->assertSuccessful();

        $translations = $this->getGeneratedTranslations('fr', 'user');

        // Should use dot notation keys
        $this->assertEquals('Nom', $translations['name']);
        $this->assertEquals('Email', $translations['email']);
        $this->assertEquals('Biographie', $translations['profile.bio']);
    }

    #[Test]
    public function it_creates_nested_structure_when_configured()
    {
        $poContent = <<<'PO'
msgid ""
msgstr ""
"Language: fr\n"

msgctxt "user.name"
msgid "Name"
msgstr "Nom"

msgctxt "user.email"
msgid "Email"
msgstr "Email"

msgctxt "user.profile.bio"
msgid "Biography"
msgstr "Biographie"

msgctxt "user.profile.avatar"
msgid "Avatar"
msgstr "Avatar"
PO;

        $this->createPoFile('fr', $poContent);

        config([
            'po.languages' => ['fr' => ['label' => 'French', 'enabled' => true]],
            'po.structure' => 'nested',
        ]);

        $this->artisan('po:import')->assertSuccessful();

        $translations = $this->getGeneratedTranslations('fr', 'user');

        // Should create nested arrays
        $this->assertEquals('Nom', $translations['name']);
        $this->assertEquals('Email', $translations['email']);
        $this->assertIsArray($translations['profile']);
        $this->assertEquals('Biographie', $translations['profile']['bio']);
        $this->assertEquals('Avatar', $translations['profile']['avatar']);
    }

    #[Test]
    public function it_creates_deeply_nested_structure()
    {
        $poContent = <<<'PO'
msgid ""
msgstr ""
"Language: fr\n"

msgctxt "app.settings.user.profile.name"
msgid "Name"
msgstr "Nom"

msgctxt "app.settings.user.profile.email"
msgid "Email"
msgstr "Email"

msgctxt "app.settings.user.security.password"
msgid "Password"
msgstr "Mot de passe"
PO;

        $this->createPoFile('fr', $poContent);

        config([
            'po.languages' => ['fr' => ['label' => 'French', 'enabled' => true]],
            'po.structure' => 'nested',
        ]);

        $this->artisan('po:import')->assertSuccessful();

        $translations = $this->getGeneratedTranslations('fr', 'app');

        // Should create deeply nested arrays
        $this->assertIsArray($translations['settings']);
        $this->assertIsArray($translations['settings']['user']);
        $this->assertIsArray($translations['settings']['user']['profile']);
        $this->assertIsArray($translations['settings']['user']['security']);
        $this->assertEquals('Nom', $translations['settings']['user']['profile']['name']);
        $this->assertEquals('Email', $translations['settings']['user']['profile']['email']);
        $this->assertEquals('Mot de passe', $translations['settings']['user']['security']['password']);
    }

    #[Test]
    public function it_sorts_nested_arrays_alphabetically()
    {
        $poContent = <<<'PO'
msgid ""
msgstr ""
"Language: fr\n"

msgctxt "user.zebra"
msgid "Zebra"
msgstr "Zèbre"

msgctxt "user.apple"
msgid "Apple"
msgstr "Pomme"

msgctxt "user.middle.zoo"
msgid "Zoo"
msgstr "Zoo"

msgctxt "user.middle.ant"
msgid "Ant"
msgstr "Fourmi"
PO;

        $this->createPoFile('fr', $poContent);

        config([
            'po.languages' => ['fr' => ['label' => 'French', 'enabled' => true]],
            'po.structure' => 'nested',
        ]);

        $this->artisan('po:import')->assertSuccessful();

        $translations = $this->getGeneratedTranslations('fr', 'user');

        // Check keys are sorted alphabetically
        $keys = array_keys($translations);
        $this->assertEquals(['apple', 'middle', 'zebra'], $keys);

        // Check nested keys are also sorted
        $nestedKeys = array_keys($translations['middle']);
        $this->assertEquals(['ant', 'zoo'], $nestedKeys);
    }

    #[Test]
    public function it_merges_nested_structure_with_existing_translations()
    {
        // Create existing nested translation file
        $this->createTranslationFile('fr', 'user', [
            'name' => 'Nom',
            'profile' => [
                'bio' => 'Biographie existante',
                'location' => 'Localisation',
            ],
        ]);

        $poContent = <<<'PO'
msgid ""
msgstr ""
"Language: fr\n"

msgctxt "user.email"
msgid "Email"
msgstr "Email"

msgctxt "user.profile.bio"
msgid "Biography"
msgstr "Biographie mise à jour"

msgctxt "user.profile.website"
msgid "Website"
msgstr "Site web"
PO;

        $this->createPoFile('fr', $poContent);

        config([
            'po.languages' => ['fr' => ['label' => 'French', 'enabled' => true]],
            'po.structure' => 'nested',
        ]);

        $this->artisan('po:import')->assertSuccessful();

        $translations = $this->getGeneratedTranslations('fr', 'user');

        // Should preserve existing translations
        $this->assertEquals('Nom', $translations['name']);
        $this->assertEquals('Localisation', $translations['profile']['location']);

        // Should update with new translations
        $this->assertEquals('Email', $translations['email']);
        $this->assertEquals('Biographie mise à jour', $translations['profile']['bio']);
        $this->assertEquals('Site web', $translations['profile']['website']);
    }
}
