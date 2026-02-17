<?php

namespace AuditDiff\Laravel\Tests;

use AuditDiff\Laravel\AuditServiceProvider;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [AuditServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        // sqlite in-memory
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        // package config
        $app['config']->set('audit-diff.enabled', true);
        $app['config']->set('audit-diff.events', ['updated']);
        $app['config']->set('audit-diff.null_equals_empty_string', true);
        $app['config']->set('audit-diff.skip_if_only_timestamps_changed', true);
        $app['config']->set('audit-diff.store_full_snapshot', false);
        $app['config']->set('audit-diff.mask_keys', ['password', 'token', 'secret', 'api_key', 'authorization']);
        $app['config']->set('audit-diff.actor_resolver', null);
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Create audit_logs table (same as stub, simplified for sqlite testing)
        Schema::create('audit_logs', function ($table) {
            $table->id();
            $table->string('auditable_type');
            $table->string('auditable_id');
            $table->string('event');
            $table->string('actor_id')->nullable();
            $table->string('actor_type')->nullable();
            $table->json('diff')->nullable();
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->string('url')->nullable();
            $table->string('method')->nullable();
            $table->string('ip')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        // Fixture model table
        Schema::create('test_users', function ($table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('password')->nullable();
            $table->timestamps();
        });
    }
}
