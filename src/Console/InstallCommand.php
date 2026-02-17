<?php

namespace AuditDiff\Laravel\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class InstallCommand extends Command
{
    protected $signature = 'audit-diff:install {--migrate : Run migrations after publishing}';

    protected $description = 'Install laravel-audit-diff (publish config/migrations, optionally run migrate).';

    public function handle(): int
    {
        $this->info('Installing laravel-audit-diff...');

        $publishedConfig = $this->publishConfigIfMissing();
        $publishedMigrations = $this->publishMigrationsIfMissing();

        if ($this->option('migrate')) {
            $this->line('');
            $this->info('Running migrations...');
            // 明示オプション時のみ実行
            Artisan::call('migrate', [], $this->output);
        }

        $this->line('');
        $this->info('Done.');

        // 何をしたか分かるように表示
        $this->comment('Summary:');
        $this->line('- config: ' . ($publishedConfig ? 'published' : 'skipped (already exists)'));
        $this->line('- migrations: ' . ($publishedMigrations ? 'published' : 'skipped (already exists)'));
        if ($this->option('migrate')) {
            $this->line('- migrate: executed');
        }

        return self::SUCCESS;
    }

    private function publishConfigIfMissing(): bool
    {
        $path = config_path('audit-diff.php');

        if (file_exists($path)) {
            $this->warn("Config already exists: {$path}");
            return false;
        }

        $this->info('Publishing config...');
        Artisan::call('vendor:publish', [
            '--tag' => 'audit-diff-config',
        ], $this->output);

        return true;
    }

    private function publishMigrationsIfMissing(): bool
    {
        $migrationDir = database_path('migrations');
        $pattern = $migrationDir . '/*_create_audit_logs_table.php';

        $existing = glob($pattern) ?: [];

        if (!empty($existing)) {
            $this->warn('Migration already exists:');
            foreach ($existing as $file) {
                $this->line(" - {$file}");
            }
            return false;
        }

        $this->info('Publishing migrations...');
        Artisan::call('vendor:publish', [
            '--tag' => 'audit-diff-migrations',
        ], $this->output);

        return true;
    }
}
