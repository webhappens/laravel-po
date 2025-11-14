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
        {--all : Download all active languages }';

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

        $locales = $this->getLocales();

        if ($locales->isEmpty()) {
            $this->components->warn('No languages to download. Use --all flag or specify language codes.');

            return Command::FAILURE;
        }

        $success = true;

        foreach ($locales as $locale) {
            $success = $this->downloadLanguage($locale, $apiToken, $projectId) && $success;
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

    protected function downloadLanguage(string $locale, string $apiToken, string $projectId): bool
    {
        $success = false;

        $this->components->task("Downloading $locale.po", function () use ($locale, $apiToken, $projectId, &$success) {
            // Step 1: Request export from POEditor
            $response = Http::asForm()->post('https://api.poeditor.com/v2/projects/export', [
                'api_token' => $apiToken,
                'id' => $projectId,
                'language' => $locale,
                'type' => 'po',
            ]);

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

    protected function getLocales(): Collection
    {
        $configuredLanguages = config('po.languages', []);

        // Auto-detect enabled languages if not configured
        if (empty($configuredLanguages)) {
            $langPath = config('po.paths.lang');
            $directories = File::directories($langPath);

            $languages = collect($directories)
                ->map(fn ($dir) => basename($dir));
        } else {
            $languages = collect($configuredLanguages)
                ->where('enabled', true)
                ->keys();
        }

        // If specific languages are provided as arguments, use those
        if ($langs = $this->argument('lang')) {
            return collect($langs)->intersect($languages);
        }

        // If --all flag is set, return all active languages
        if ($this->option('all')) {
            return $languages;
        }

        // Otherwise, return empty collection (user must specify langs or --all)
        return collect();
    }
}
