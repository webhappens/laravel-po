<?php

namespace WebHappens\LaravelPo\Commands\Concerns;

use Illuminate\Support\Facades\File;

trait ClearsDirectories
{
    protected function clearDirectory(string $path, string $type): bool
    {
        $files = File::files($path);

        if (empty($files)) {
            $this->components->info("The {$type} directory is already empty.");

            return true;
        }

        $fileList = collect($files)
            ->map(fn ($file) => basename($file))
            ->join(', ');

        $this->components->warn("The following files will be deleted from {$path}:");
        $this->line("  {$fileList}");
        $this->newLine();

        if (! $this->option('force')) {
            if (! $this->confirm('Do you want to continue?', false)) {
                $this->components->info('Operation cancelled.');

                return false;
            }
        }

        foreach ($files as $file) {
            File::delete($file);
        }

        $this->components->info("Cleared {$type} directory.");
        $this->newLine();

        return true;
    }
}
