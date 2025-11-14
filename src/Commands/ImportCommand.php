<?php

namespace WebHappens\LaravelPo\Commands;

use Gettext\Languages\Language;
use Gettext\Loader\PoLoader;
use Gettext\Translation;
use Gettext\Translations;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Finder\Finder;
use Symfony\Component\VarExporter\VarExporter;

class ImportCommand extends Command
{
    protected $signature = 'po:import
        {lang?* : Provide the language codes for generation, or leave blank for all }
        {--fuzzy : Include fuzzy translations }
        {--only=* : Limit keys to only those that match a specific pattern }
        {--replace : Replace existing files }';

    protected $description = 'Load PO files to update translations';

    public function handle(): int
    {
        foreach ($this->getLanguageFiles() as $locale => $translations) {
            $language = Language::getById($locale);
            $this->components->info('Loading translations for '.$language->name.' ['.$locale.']');

            collect($translations)
                ->lazy()
                ->reject([$this, 'isFuzzy'])
                ->filter([$this, 'matchesPattern'])
                ->groupBy(fn ($translation) => Str::before($translation->getContext(), '.'))
                ->each(function ($translations, $group) use ($locale) {
                    $langPath = config('po.paths.lang');
                    $groupFile = $langPath.'/'.$locale.DIRECTORY_SEPARATOR."$group.php";
                    $nonMatchingTranslations = collect();

                    $this->components->task('Generating file: '.$groupFile, function () use ($locale, $group, $groupFile, $translations, $nonMatchingTranslations) {
                        if (app()->getLocale() == $locale) {
                            $translations
                                ->filter(fn ($translation) => $translation->getOriginal() !== $translation->getTranslation())
                                ->each(fn ($translation) => $nonMatchingTranslations->push($translation));
                        }

                        $translations = $translations
                            ->keyBy(fn ($translation) => Str::after($translation->getContext(), '.'))
                            ->map(fn ($translation) => $translation->getTranslation())
                            ->filter()
                            ->map([$this, 'formatPlaceholdersFromPerlBrace']);

                        if (! $this->option('replace')) {
                            // Load existing translations by reading the file directly
                            if (File::exists($groupFile)) {
                                $existing = include $groupFile;
                                $translations = collect($existing)->merge($translations);
                            }
                        }

                        if ($translations->isEmpty()) {
                            File::delete($groupFile);

                            return;
                        }

                        $export = VarExporter::export($translations->sortKeys()->toArray());
                        $content = <<<"PHP"
                            <?php

                            return $export;

                            PHP;

                        // Ensure directory exists
                        $directory = dirname($groupFile);
                        if (! File::exists($directory)) {
                            File::makeDirectory($directory, 0755, true);
                        }

                        file_put_contents($groupFile, $content);
                    });

                    if ($nonMatchingTranslations->isNotEmpty()) {
                        $this->newLine();
                        $this->components->warn($nonMatchingTranslations->count().' non-matching terms for default language');
                        $this->table(
                            ['  Key', 'Original', 'Translation'],
                            $nonMatchingTranslations->map(fn ($translation) => [
                                '  '.$translation->getContext(), $translation->getOriginal(), $translation->getTranslation(),
                            ]),
                            'compact',
                        );
                        $this->newLine();
                    }
                });

            // Call optional cache clearing callback if configured
            if ($callback = config('po.cache.clear_callback')) {
                $callback($locale);
            }

            $this->newLine();
        }

        return Command::SUCCESS;
    }

    public function isFuzzy(Translation $translation): bool
    {
        if ($this->option('fuzzy')) {
            return false;
        }

        return $translation->getFlags()->has('fuzzy');
    }

    public function matchesPattern(Translation $translation): bool
    {
        if (! $only = $this->option('only')) {
            return true;
        }

        return collect($only)
            ->first(fn ($pattern) => str($translation->getContext())->is($pattern.'*'))
            ?: false;
    }

    public function formatPlaceholdersFromPerlBrace(string $translation): string
    {
        return Str::of($translation)
            ->matchAll('/{(\w+)}/')
            ->reduce(fn ($translation, $placeholder) => str_replace('{'.$placeholder.'}', ':'.$placeholder, $translation), $translation);
    }

    protected function getLanguageFiles(): Collection
    {
        $importPath = config('po.paths.import');
        $configuredLanguages = config('po.languages', []);

        // Auto-detect enabled languages if not configured
        if (empty($configuredLanguages)) {
            $langPath = config('po.paths.lang');
            $directories = File::directories($langPath);

            $enabledLocales = collect($directories)
                ->map(fn ($dir) => basename($dir));
        } else {
            $enabledLocales = collect($configuredLanguages)
                ->where('enabled', true)
                ->keys();
        }

        return collect(Finder::create()->files()->name('*.po')->in($importPath))
            ->map(fn ($file) => (new PoLoader)->loadFile($file->getRealPath()))
            ->keyBy(fn (Translations $translations) => $translations->getLanguage())
            ->only($enabledLocales)
            ->when($this->argument('lang'), fn ($locales, $only) => $locales->only($only));
    }
}
