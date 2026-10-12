<?php

namespace WebHappens\LaravelPo\Commands\Concerns;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Symfony\Component\VarExporter\VarExporter;

trait ManagesTranslations
{
    /**
     * Read a translation file and return its contents as an array
     *
     * @return array<string, mixed>
     */
    protected function readTranslationFile(string $locale, string $group): array
    {
        $langPath = config('po.paths.lang');
        $filePath = $langPath.'/'.$locale.'/'.$group.'.php';

        if (! File::exists($filePath)) {
            return [];
        }

        return include $filePath;
    }

    /**
     * Write translations to a file
     *
     * @param  array<string, mixed>  $translations
     */
    protected function writeTranslationFile(string $locale, string $group, array $translations): void
    {
        $langPath = config('po.paths.lang');
        $directory = $langPath.'/'.$locale;
        $filePath = $directory.'/'.$group.'.php';

        // Delete file if no translations
        if (empty($translations)) {
            if (File::exists($filePath)) {
                File::delete($filePath);
            }

            return;
        }

        // Ensure directory exists
        if (! File::exists($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        // Sort translations based on configuration
        $sorted = $this->sortTranslations($translations);

        $export = VarExporter::export($sorted);
        $content = <<<"PHP"
            <?php

            return $export;

            PHP;

        file_put_contents($filePath, $content);
    }

    /**
     * Sort translations based on configuration (flat or nested)
     *
     * @param  array<string, mixed>  $translations
     * @return array<string, mixed>
     */
    protected function sortTranslations(array $translations): array
    {
        if (config('po.structure') === 'nested') {
            return $this->sortNestedArray($translations);
        }

        // For flat structure, convert to collection, sort, and return as array
        return collect($translations)->sortKeys()->toArray();
    }

    /**
     * Convert flat dot-notation array to nested array
     *
     * @param  Collection<string, string>  $translations
     * @return array<string, mixed>
     */
    protected function toNestedArray(Collection $translations): array
    {
        $result = [];

        foreach ($translations as $key => $value) {
            Arr::set($result, $key, $value);
        }

        return $this->sortNestedArray($result);
    }

    /**
     * Recursively sort a nested array
     *
     * @param  array<array-key, mixed>  $array
     * @return array<array-key, mixed>
     */
    protected function sortNestedArray(array $array): array
    {
        ksort($array);

        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $array[$key] = $this->sortNestedArray($value);
            }
        }

        return $array;
    }

    /**
     * Get the enabled locales from config, or auto-detect them from the lang directory
     *
     * @return Collection<int, string>
     */
    protected function getEnabledLocales(): Collection
    {
        $configuredLanguages = (array) config('po.languages');

        if (empty($configuredLanguages)) {
            $ignoredPaths = collect([config('po.paths.export'), config('po.paths.import')])
                ->map(fn ($path) => realpath($path))
                ->filter();

            return collect(File::directories(config('po.paths.lang')))
                ->reject(fn ($dir) => basename($dir) === 'vendor' || $ignoredPaths->contains(realpath($dir)))
                ->map(fn ($dir) => basename($dir))
                ->values();
        }

        return collect($configuredLanguages)
            ->where('enabled', true)
            ->keys();
    }

    /**
     * Get all translation files for a given locale
     *
     * @return Collection<int, string>
     */
    protected function getTranslationGroups(string $locale): Collection
    {
        $langPath = config('po.paths.lang');
        $localePath = $langPath.'/'.$locale;

        if (! File::isDirectory($localePath)) {
            return collect();
        }

        return collect(File::files($localePath))
            ->filter(fn ($file) => $file->getExtension() === 'php')
            ->map(fn ($file) => $file->getBasename('.php'));
    }
}
