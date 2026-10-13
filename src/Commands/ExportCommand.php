<?php

namespace WebHappens\LaravelPo\Commands;

use Gettext\Generator\PoGenerator;
use Gettext\Languages\Language;
use Gettext\Translation;
use Gettext\Translations;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use WebHappens\LaravelPo\Commands\Concerns\ClearsDirectories;
use WebHappens\LaravelPo\Commands\Concerns\ManagesTranslations;

class ExportCommand extends Command
{
    use ClearsDirectories, ManagesTranslations;

    protected $signature = 'po:export
        {lang?* : Provide the language codes for generation, or leave blank for default language }
        {--all : Export all enabled languages }
        {--clear : Clear the export directory before generating new files }
        {--force : Force the operation without confirmation }';

    protected $description = 'Generate PO files for language translation';

    public function handle(): int
    {
        $appLocale = app()->getLocale();

        if (! $appLanguage = Language::getById($appLocale)) {
            $this->components->error("The default locale [$appLocale] is not a recognised language code.");

            return Command::FAILURE;
        }

        $this->components->info('Generating translation files using the default language: '.$appLanguage->name.' ['.$appLocale.']');

        $pot = $this->getTranslationTerms($appLocale);

        // Ensure export directory exists
        $exportPath = config('po.paths.export');
        if (! File::exists($exportPath)) {
            File::makeDirectory($exportPath, 0755, true);
        }

        // Handle --clear option
        if ($this->option('clear')) {
            if (! $this->clearDirectory($exportPath, 'export')) {
                return Command::FAILURE;
            }
        }

        foreach ($this->getLocales() as $locale) {
            if (! $language = Language::getById($locale)) {
                $this->components->warn("Skipping [$locale] as it is not a recognised language code.");

                continue;
            }

            $this->components->task('Creating PO file for '.$language->name.' ['.$locale.']', function () use ($pot, $locale, $exportPath) {
                (new PoGenerator)->generateFile(
                    $this->setTranslationsForLocale($pot, $locale),
                    $exportPath."/$locale.po",
                );
            });
        }

        $this->newLine();

        return Command::SUCCESS;
    }

    public function getTranslationTerms(string $locale): Translations
    {
        $pot = Translations::create(language: $locale);

        collect($this->getFlattenedTranslations($locale))
            ->reject(fn ($text) => ! $text)
            ->filter($this->shouldIncludeTranslation(...))
            ->map($this->formatPlaceholdersAsPerlBrace(...))
            ->map(fn ($original, $key) => Translation::create($key, $original))
            ->each(fn ($translation) => $pot->add($translation));

        return $pot;
    }

    public function setTranslationsForLocale(Translations $translations, string $locale): Translations
    {
        $translations = (clone $translations)->setLanguage($locale);
        $existing = $this->getFlattenedTranslations($locale);

        foreach ($translations as $translation) {
            $text = $existing[$translation->getContext()] ?? null;

            if (! is_string($text)) {
                continue;
            }

            $translation->translate($this->formatPlaceholdersAsPerlBrace($text));
        }

        return $translations;
    }

    /**
     * Read all translation groups for a locale, flattened to dot-notation keys
     *
     * @return array<string, mixed>
     */
    protected function getFlattenedTranslations(string $locale): array
    {
        $groups = $this->getTranslationGroups($locale)
            ->mapWithKeys(fn ($group) => [$group => $this->readTranslationFile($locale, $group)]);

        return Arr::dot($groups);
    }

    public function shouldIncludeTranslation(string $translation, string $key): bool
    {
        $exclude = (array) config('po.excluded_groups');

        return ! collect($exclude)
            ->contains(fn ($pattern) => str($key)->is([$pattern, $pattern.'.*']));
    }

    public function formatPlaceholdersAsPerlBrace(string $translation): string
    {
        return Str::of($translation)
            ->replaceMatches('/:(\w+)/', '{$1}')
            ->toString();
    }

    /**
     * @return Collection<int, string>
     */
    protected function getLocales(): Collection
    {
        $locales = $this->getEnabledLocales();

        if ($this->option('all')) {
            return $locales;
        }

        return $locales
            ->intersect($this->argument('lang') ?: [app()->getLocale()])
            ->values();
    }
}
