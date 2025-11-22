<?php

namespace WebHappens\LaravelPo\Commands\Concerns;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Symfony\Component\VarExporter\VarExporter;

trait ManagesTranslations
{
    /**
     * Read a translation file and return its contents as an array
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
     */
    protected function toNestedArray(Collection $translations): array
    {
        $result = [];

        foreach ($translations as $key => $value) {
            $keys = explode('.', $key);
            $current = &$result;

            foreach ($keys as $i => $k) {
                if ($i === count($keys) - 1) {
                    $current[$k] = $value;
                } else {
                    if (! isset($current[$k]) || ! is_array($current[$k])) {
                        $current[$k] = [];
                    }
                    $current = &$current[$k];
                }
            }
        }

        return $this->sortNestedArray($result);
    }

    /**
     * Recursively sort a nested array
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
     * Get all translation files for a given locale
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
