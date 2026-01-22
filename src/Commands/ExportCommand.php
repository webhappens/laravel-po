<?php

namespace WebHappens\LaravelPo\Commands;

use Gettext\Generator\PoGenerator;
use Gettext\Languages\Language;
use Gettext\Translation;
use Gettext\Translations;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Translation\Translator;
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

    public function __construct(
        protected Translator $translator,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $appLocale = app()->getLocale();
        $appLanguage = Language::getById($appLocale);

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

        foreach ($this->getLocales() as $locale => $language) {
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

    public function getTranslationTerms($locale): Translations
    {
        $pot = Translations::create(language: $locale);

        $this->getTranslationGroups($locale)
            ->mapWithKeys(fn ($group) => [$group => $this->readTranslationFile($locale, $group)])
            ->dot()
            ->reject(fn ($text) => ! $text)
            ->filter([$this, 'shouldIncludeTranslation'])
            ->map([$this, 'formatPlaceholdersAsPerlBrace'])
            ->map(fn ($original, $key) => Translation::create($key, $original))
            ->each(fn ($translation) => $pot->add($translation));

        return $pot;
    }

    public function setTranslationsForLocale(Translations $translations, string $locale): Translations
    {
        $translations = (clone $translations)->setLanguage($locale);

        foreach ($translations as $translation) {
            if (! $this->translator->hasForLocale($translation->getContext(), $locale)) {
                continue;
            }

            $translation->translate(
                $this->formatPlaceholdersAsPerlBrace(
                    $this->translator->get(
                        key: $translation->getContext(),
                        locale: $locale,
                        fallback: false,
                    )
                )
            );
        }

        return $translations;
    }

    public function shouldIncludeTranslation(string $translation, string $key): bool
    {
        $exclude = config('po.excluded_groups', []);

        return ! collect($exclude)
            ->first(fn ($pattern) => str($key)->is($pattern.'*'));
    }

    public function formatPlaceholdersAsPerlBrace(string $translation): string
    {
        return Str::of($translation)
            ->matchAll('/\:(\w+)/')
            ->reduce(fn ($translation, $placeholder) => str_replace(':'.$placeholder, '{'.$placeholder.'}', $translation), $translation);
    }

    protected function getLocales(): Collection
    {
        $configuredLanguages = config('po.languages', []);

        // Auto-detect languages from directories if not configured
        if (empty($configuredLanguages)) {
            $langPath = config('po.paths.lang');
            $directories = File::directories($langPath);

            $configuredLanguages = collect($directories)
                ->mapWithKeys(function ($dir) {
                    $locale = basename($dir);
                    return [$locale => ['label' => $locale, 'enabled' => true]];
                })
                ->toArray();
        }

        $locales = collect($configuredLanguages)
            ->where('enabled', true)
            ->map(fn ($language, $locale) => Language::getById($locale))
            ->filter();

        if ($this->option('all')) {
            return $locales;
        }

        return $locales->only($this->argument('lang') ?: app()->getLocale());
    }

}
