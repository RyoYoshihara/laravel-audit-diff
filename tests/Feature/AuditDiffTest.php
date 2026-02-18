<?php

namespace AuditDiff\Laravel\Tests\Feature;

use AuditDiff\Laravel\Tests\Fixtures\TestUser;
use AuditDiff\Laravel\Tests\TestCase;
use Illuminate\Support\Facades\DB;

class AuditDiffTest extends TestCase
{
    public function test_it_records_diff_on_update(): void
    {
        $u = TestUser::create(['name' => 'A']);
        $u->update(['name' => 'B']);

        $log = DB::table('audit_logs')->orderByDesc('id')->first();
        $this->assertNotNull($log);

        $diff = json_decode($log->diff, true);
        $this->assertSame('A', $diff['name']['before']);
        $this->assertSame('B', $diff['name']['after']);
    }

    public function test_it_skips_when_only_updated_at_changed(): void
    {
        $u = TestUser::create(['name' => 'A']);

        $beforeCount = DB::table('audit_logs')->count();

        // touch updates only timestamp(s)
        $u->touch();

        $afterCount = DB::table('audit_logs')->count();

        $this->assertSame($beforeCount, $afterCount);
    }


    public function test_it_masks_sensitive_values(): void
    {
        $u = TestUser::create(['name' => 'A', 'password' => 'old']);
        $u->update(['password' => 'new']);

        $log = DB::table('audit_logs')->orderByDesc('id')->first();
        $this->assertNotNull($log);

        $diff = json_decode($log->diff, true);
        $this->assertSame('***', $diff['password']['before']);
        $this->assertSame('***', $diff['password']['after']);
    }

    public function test_it_records_created_and_deleted(): void
    {
        $u = TestUser::create(['name' => 'A', 'password' => 'old']);

        $created = DB::table('audit_logs')->orderBy('id')->first();
        $this->assertSame('created', $created->event);

        // created: diff is null, after contains snapshot
        $this->assertTrue($created->diff === null || $created->diff === 'null');
        $after = json_decode($created->after ?? '{}', true) ?: [];
        $this->assertSame('A', $after['name'] ?? null);

        $u->delete();

        $deleted = DB::table('audit_logs')->orderByDesc('id')->first();
        $this->assertSame('deleted', $deleted->event);

        $before = json_decode($deleted->before ?? '{}', true) ?: [];
        $this->assertSame('A', $before['name'] ?? null);
    }

    public function test_it_excludes_keys_and_sets_actor(): void
    {
        $u = TestUser::create(['name' => 'A', 'remember_token' => 'SHOULD_NOT_LOG']);
        $u->update(['name' => 'B', 'remember_token' => 'UPDATED']);

        $log = DB::table('audit_logs')->orderByDesc('id')->first();

        // actor
        $this->assertSame('tester-1', $log->actor_id);
        $this->assertSame('tests', $log->actor_type);

        // exclude: remember_token should not appear
        $diff = json_decode($log->diff ?? '{}', true) ?: [];
        $this->assertArrayNotHasKey('remember_token', $diff);
    }

}
