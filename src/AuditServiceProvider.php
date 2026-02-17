<?php

namespace AuditDiff\Laravel;

use Illuminate\Support\ServiceProvider;
use AuditDiff\Laravel\Console\InstallCommand;

class AuditServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/audit-diff.php', 'audit-diff');
    }

    public function boot(): void
    {
        // Publish: config
        $this->publishes([
            __DIR__ . '/../config/audit-diff.php' => config_path('audit-diff.php'),
        ], 'audit-diff-config');

        // Publish: migrations (stub)
        $this->publishes([
            __DIR__ . '/../database/migrations/create_audit_logs_table.php.stub' =>
                database_path('migrations/' . date('Y_m_d_His') . '_create_audit_logs_table.php'),
        ], 'audit-diff-migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([
                InstallCommand::class,
            ]);
        }
    }
}
