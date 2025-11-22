<?php

namespace WebHappens\LaravelPo\Tests\Feature;

use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Test;
use WebHappens\LaravelPo\Tests\TestCase;

class SortCommandTest extends TestCase
{
    #[Test]
    public function it_sorts_flat_translations_alphabetically()
    {
        // Create unsorted translations
        $this->createTranslationFile('fr', 'messages', [
            'zebra' => 'Zèbre',
            'apple' => 'Pomme',
            'monkey' => 'Singe',
            'banana' => 'Banane',
        ]);

        config(['po.languages' => [
            'fr' => ['label' => 'French', 'enabled' => true],
        ]]);

        $this->artisan('po:sort')->assertSuccessful();

        $translations = $this->getGeneratedTranslations('fr', 'messages');

        // Check keys are sorted
        $keys = array_keys($translations);
        $this->assertEquals(['apple', 'banana', 'monkey', 'zebra'], $keys);
    }

    #[Test]
    public function it_sorts_nested_translations_alphabetically()
    {
        // Create unsorted nested translations
        $this->createTranslationFile('fr', 'user', [
            'zebra' => 'Zèbre',
            'profile' => [
                'zoo' => 'Zoo',
                'ant' => 'Fourmi',
            ],
            'apple' => 'Pomme',
            'settings' => [
                'zebra' => 'Zèbre',
                'apple' => 'Pomme',
            ],
        ]);

        config([
            'po.languages' => ['fr' => ['label' => 'French', 'enabled' => true]],
            'po.structure' => 'nested',
        ]);

        $this->artisan('po:sort')->assertSuccessful();

        $translations = $this->getGeneratedTranslations('fr', 'user');

        // Check top-level keys are sorted
        $topKeys = array_keys($translations);
        $this->assertEquals(['apple', 'profile', 'settings', 'zebra'], $topKeys);

        // Check nested keys are sorted
        $profileKeys = array_keys($translations['profile']);
        $this->assertEquals(['ant', 'zoo'], $profileKeys);

        $settingsKeys = array_keys($translations['settings']);
        $this->assertEquals(['apple', 'zebra'], $settingsKeys);
    }

    #[Test]
    public function it_sorts_specific_language_when_argument_provided()
    {
        // Create translations for multiple languages
        $this->createTranslationFile('fr', 'messages', [
            'zebra' => 'Zèbre',
            'apple' => 'Pomme',
        ]);

        $this->createTranslationFile('de', 'messages', [
            'zebra' => 'Zebra',
            'apple' => 'Apfel',
        ]);

        config(['po.languages' => [
            'fr' => ['label' => 'French', 'enabled' => true],
            'de' => ['label' => 'German', 'enabled' => true],
        ]]);

        // Sort only French
        $this->artisan('po:sort', ['lang' => ['fr']])->assertSuccessful();

        // French should be sorted
        $frTranslations = $this->getGeneratedTranslations('fr', 'messages');
        $frKeys = array_keys($frTranslations);
        $this->assertEquals(['apple', 'zebra'], $frKeys);

        // German should remain unsorted (original order maintained)
        $deTranslations = $this->getGeneratedTranslations('de', 'messages');
        $deKeys = array_keys($deTranslations);
        $this->assertEquals(['zebra', 'apple'], $deKeys);
    }

    #[Test]
    public function it_sorts_all_enabled_languages_when_no_argument_provided()
    {
        $this->createTranslationFile('fr', 'messages', [
            'zebra' => 'Zèbre',
            'apple' => 'Pomme',
        ]);

        $this->createTranslationFile('de', 'messages', [
            'zebra' => 'Zebra',
            'apple' => 'Apfel',
        ]);

        config(['po.languages' => [
            'fr' => ['label' => 'French', 'enabled' => true],
            'de' => ['label' => 'German', 'enabled' => true],
            'es' => ['label' => 'Spanish', 'enabled' => false],
        ]]);

        $this->artisan('po:sort')->assertSuccessful();

        // Both French and German should be sorted
        $frKeys = array_keys($this->getGeneratedTranslations('fr', 'messages'));
        $this->assertEquals(['apple', 'zebra'], $frKeys);

        $deKeys = array_keys($this->getGeneratedTranslations('de', 'messages'));
        $this->assertEquals(['apple', 'zebra'], $deKeys);
    }

    #[Test]
    public function it_sorts_multiple_translation_groups()
    {
        // Create multiple translation files
        $this->createTranslationFile('fr', 'messages', [
            'zebra' => 'Zèbre',
            'apple' => 'Pomme',
        ]);

        $this->createTranslationFile('fr', 'actions', [
            'save' => 'Enregistrer',
            'delete' => 'Supprimer',
            'cancel' => 'Annuler',
        ]);

        config(['po.languages' => [
            'fr' => ['label' => 'French', 'enabled' => true],
        ]]);

        $this->artisan('po:sort')->assertSuccessful();

        // Both files should be sorted
        $messagesKeys = array_keys($this->getGeneratedTranslations('fr', 'messages'));
        $this->assertEquals(['apple', 'zebra'], $messagesKeys);

        $actionsKeys = array_keys($this->getGeneratedTranslations('fr', 'actions'));
        $this->assertEquals(['cancel', 'delete', 'save'], $actionsKeys);
    }

    #[Test]
    public function it_handles_language_with_no_translation_files()
    {
        // Create empty language directory
        File::makeDirectory($this->tempLangPath.'/fr', 0755, true);

        config(['po.languages' => [
            'fr' => ['label' => 'French', 'enabled' => true],
        ]]);

        $this->artisan('po:sort')
            ->assertSuccessful()
            ->expectsOutputToContain('No translation files found for fr');
    }

    #[Test]
    public function it_auto_detects_languages_when_not_configured()
    {
        config(['po.languages' => []]);

        // Create language directories
        $this->createTranslationFile('fr', 'messages', [
            'zebra' => 'Zèbre',
            'apple' => 'Pomme',
        ]);

        $this->createTranslationFile('de', 'messages', [
            'zebra' => 'Zebra',
            'apple' => 'Apfel',
        ]);

        $this->artisan('po:sort')->assertSuccessful();

        // Both should be sorted
        $frKeys = array_keys($this->getGeneratedTranslations('fr', 'messages'));
        $this->assertEquals(['apple', 'zebra'], $frKeys);

        $deKeys = array_keys($this->getGeneratedTranslations('de', 'messages'));
        $this->assertEquals(['apple', 'zebra'], $deKeys);
    }

    #[Test]
    public function it_preserves_translation_values_while_sorting()
    {
        $this->createTranslationFile('fr', 'messages', [
            'zebra' => 'Zèbre',
            'apple' => 'Pomme',
            'banana' => 'Banane',
        ]);

        config(['po.languages' => [
            'fr' => ['label' => 'French', 'enabled' => true],
        ]]);

        $this->artisan('po:sort')->assertSuccessful();

        $translations = $this->getGeneratedTranslations('fr', 'messages');

        // Values should be preserved
        $this->assertEquals('Pomme', $translations['apple']);
        $this->assertEquals('Banane', $translations['banana']);
        $this->assertEquals('Zèbre', $translations['zebra']);
    }

    #[Test]
    public function it_handles_no_translation_files_gracefully()
    {
        // Create empty language directory
        File::makeDirectory($this->tempLangPath.'/fr', 0755, true);

        config(['po.languages' => [
            'fr' => ['label' => 'French', 'enabled' => true],
        ]]);

        // Language directory exists but has no translation files
        $this->artisan('po:sort')
            ->assertSuccessful()
            ->expectsOutputToContain('No translation files found for fr');
    }

    #[Test]
    public function it_sorts_deeply_nested_translations()
    {
        $this->createTranslationFile('fr', 'app', [
            'zebra' => 'Zèbre',
            'settings' => [
                'user' => [
                    'zebra' => 'Zèbre',
                    'profile' => [
                        'zoo' => 'Zoo',
                        'ant' => 'Fourmi',
                    ],
                    'apple' => 'Pomme',
                ],
            ],
            'apple' => 'Pomme',
        ]);

        config([
            'po.languages' => ['fr' => ['label' => 'French', 'enabled' => true]],
            'po.structure' => 'nested',
        ]);

        $this->artisan('po:sort')->assertSuccessful();

        $translations = $this->getGeneratedTranslations('fr', 'app');

        // Check all levels are sorted
        $this->assertEquals(['apple', 'settings', 'zebra'], array_keys($translations));
        $this->assertEquals(['user'], array_keys($translations['settings']));
        $this->assertEquals(['apple', 'profile', 'zebra'], array_keys($translations['settings']['user']));
        $this->assertEquals(['ant', 'zoo'], array_keys($translations['settings']['user']['profile']));
    }
}
