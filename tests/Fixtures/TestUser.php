<?php

namespace AuditDiff\Laravel\Tests\Fixtures;

use AuditDiff\Laravel\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;

class TestUser extends Model
{
    use Auditable;

    protected $table = 'test_users';

    protected $guarded = [];
}
