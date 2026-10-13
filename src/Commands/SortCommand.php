<?php

namespace WebHappens\LaravelPo\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use WebHappens\LaravelPo\Commands\Concerns\ManagesTranslations;

class SortCommand extends Command
{
    use ManagesTranslations;

    protected $signature = 'po:sort
        {lang?* : Provide the language codes to sort, or leave blank for all }';

    protected $description = 'Sort translation files alphabetically';

    public function handle(): int
    {
        $locales = $this->getLocales();

        if ($locales->isEmpty()) {
            $this->components->warn('No languages found to sort.');

            return Command::FAILURE;
        }

        foreach ($locales as $locale) {
            $this->components->info('Sorting translations for '.$this->getLanguageName($locale).' ['.$locale.']');

            $groups = $this->getTranslationGroups($locale);

            if ($groups->isEmpty()) {
                $this->components->warn("No translation files found for {$locale}");

                continue;
            }

            foreach ($groups as $group) {
                $this->components->task("Sorting {$group}.php", function () use ($locale, $group) {
                    $translations = $this->readTranslationFile($locale, $group);

                    if (empty($translations)) {
                        return;
                    }

                    $this->writeTranslationFile($locale, $group, $translations);
                });
            }

            $this->newLine();
        }

        return Command::SUCCESS;
    }

    /**
     * @return Collection<int, string>
     */
    protected function getLocales(): Collection
    {
        if ($langs = $this->argument('lang')) {
            return collect($langs);
        }

        return $this->getEnabledLocales();
    }
}
