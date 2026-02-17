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

        // touch updates only timestamp(s)
        $u->touch();

        $count = DB::table('audit_logs')->count();
        $this->assertSame(0, $count);
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
}
