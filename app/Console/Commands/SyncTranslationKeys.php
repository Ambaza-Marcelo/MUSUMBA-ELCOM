<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class SyncTranslationKeys extends Command
{
    protected $signature = 'translations:sync {--locale=* : Locales to sync (default: en,ki)}';

    protected $description = 'Copy missing translation keys from fr/messages.php into other locale files';

    public function handle(): int
    {
        $basePath = resource_path('lang');
        $source = $this->loadMessages($basePath . '/fr/messages.php');
        if ($source === null) {
            $this->error('fr/messages.php not found or invalid.');
            return 1;
        }

        $locales = $this->option('locale') ?: ['en', 'ki'];
        foreach ($locales as $locale) {
            $targetFile = $basePath . '/' . $locale . '/messages.php';
            $target = $this->loadMessages($targetFile);
            if ($target === null) {
                $this->warn("Skipping {$locale}: file missing or invalid.");
                continue;
            }

            $added = 0;
            foreach ($source as $key => $value) {
                if (!array_key_exists($key, $target)) {
                    $target[$key] = $value;
                    $added++;
                }
            }

            if ($added > 0) {
                $this->writeMessages($targetFile, $target);
            }

            $this->info("{$locale}: {$added} key(s) added.");
        }

        return 0;
    }

    protected function loadMessages(string $path): ?array
    {
        if (!is_file($path)) {
            return null;
        }

        $messages = include $path;
        return is_array($messages) ? $messages : null;
    }

    protected function writeMessages(string $path, array $messages): void
    {
        $export = var_export($messages, true);
        $export = preg_replace('/^(\s*)array \(/m', '$1[', $export);
        $export = preg_replace('/\)$/s', '];', $export);

        $content = "<?php\n\nreturn " . substr($export, 1) . "\n";
        file_put_contents($path, $content);
    }
}
