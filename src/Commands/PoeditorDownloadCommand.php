<?php

namespace WebHappens\LaravelPo\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

class PoeditorDownloadCommand extends Command
{
    protected $signature = 'po:download
        {lang?* : A list of language codes to download }
        {--all : Download all languages available in POEditor project }
        {--active : Download all active languages from app config }
        {--fallback= : Fallback language code to use for untranslated strings }';

    protected $description = 'Download PO files from POEditor API to import directory';

    public function handle(): int
    {
        if (! config('po.poeditor.enabled')) {
            $this->components->error('POEditor integration is not enabled. Set POEDITOR_ENABLED=true in your .env file.');

            return Command::FAILURE;
        }

        $apiToken = config('po.poeditor.api_token');
        $projectId = config('po.poeditor.project_id');

        if (! $apiToken || ! $projectId) {
            $this->components->error('POEditor API credentials not configured. Please set POEDITOR_API_TOKEN and POEDITOR_PROJECT_ID in your .env file.');

            return Command::FAILURE;
        }

        // Ensure import directory exists
        $importPath = config('po.paths.import');
        if (! File::isDirectory($importPath)) {
            File::makeDirectory($importPath, 0755, true);
        }

        $this->components->info('Downloading translations from POEditor');
        $this->newLine();

        $locales = $this->getLocales($apiToken, $projectId);

        if ($locales->isEmpty()) {
            $this->components->warn('No languages to download. Use --all, --active flag or specify language codes.');

            return Command::FAILURE;
        }

        $success = true;

        $fallback = $this->option('fallback');

        foreach ($locales as $locale) {
            $success = $this->downloadLanguage($locale, $apiToken, $projectId, $fallback) && $success;
        }

        $this->newLine();

        if ($success) {
            $importPath = config('po.paths.import');
            $this->components->info("All translations downloaded successfully to $importPath/");
            $this->components->info('Run "php artisan po:import" to import them.');

            return Command::SUCCESS;
        }

        return Command::FAILURE;
    }

    protected function downloadLanguage(string $locale, string $apiToken, string $projectId, ?string $fallback = null): bool
    {
        $success = false;

        $this->components->task("Downloading $locale.po", function () use ($locale, $apiToken, $projectId, $fallback, &$success) {
            // Step 1: Request export from POEditor
            $params = [
                'api_token' => $apiToken,
                'id' => $projectId,
                'language' => $locale,
                'type' => 'po',
            ];

            if ($fallback) {
                $params['fallback_language'] = $fallback;
            }

            $response = Http::asForm()->post('https://api.poeditor.com/v2/projects/export', $params);

            if (! $response->successful()) {
                $this->components->error("Failed to request export for $locale: ".$response->body());
                $success = false;

                return;
            }

            $data = $response->json();

            if (! isset($data['response']['status']) || $data['response']['status'] !== 'success') {
                $message = $data['response']['message'] ?? 'Unknown error';
                $this->components->error("POEditor API error for $locale: $message");
                $success = false;

                return;
            }

            if (! isset($data['result']['url'])) {
                $this->components->error("No download URL returned for $locale");
                $success = false;

                return;
            }

            $downloadUrl = $data['result']['url'];

            // Step 2: Download the file from the temporary URL
            $fileResponse = Http::get($downloadUrl);

            if (! $fileResponse->successful()) {
                $this->components->error("Failed to download file for $locale");
                $success = false;

                return;
            }

            // Step 3: Save to import directory
            $importPath = config('po.paths.import');
            $filePath = $importPath."/$locale.po";
            File::put($filePath, $fileResponse->body());

            $success = true;
        });

        return $success;
    }

    protected function getLocales(string $apiToken, string $projectId): Collection
    {
        // If specific languages are provided as arguments, use those (no filtering)
        if ($langs = $this->argument('lang')) {
            return collect($langs);
        }

        // If --all flag is set, fetch all languages from POEditor
        if ($this->option('all')) {
            return $this->fetchPoeditorLanguages($apiToken, $projectId);
        }

        // If --active flag is set, return active languages from config
        if ($this->option('active')) {
            return $this->getActiveLanguages();
        }

        // Otherwise, return empty collection (user must specify langs, --all, or --active)
        return collect();
    }

    protected function getActiveLanguages(): Collection
    {
        $configuredLanguages = config('po.languages', []);

        // Auto-detect enabled languages if not configured
        if (empty($configuredLanguages)) {
            $langPath = config('po.paths.lang');
            $directories = File::directories($langPath);

            return collect($directories)
                ->map(fn ($dir) => basename($dir));
        }

        return collect($configuredLanguages)
            ->where('enabled', true)
            ->keys();
    }

    protected function fetchPoeditorLanguages(string $apiToken, string $projectId): Collection
    {
        $response = Http::asForm()->post('https://api.poeditor.com/v2/languages/list', [
            'api_token' => $apiToken,
            'id' => $projectId,
        ]);

        if (! $response->successful()) {
            $this->components->error('Failed to fetch languages from POEditor: '.$response->body());

            return collect();
        }

        $data = $response->json();

        if (! isset($data['response']['status']) || $data['response']['status'] !== 'success') {
            $message = $data['response']['message'] ?? 'Unknown error';
            $this->components->error("POEditor API error: $message");

            return collect();
        }

        return collect($data['result']['languages'] ?? [])
            ->pluck('code');
    }
}
