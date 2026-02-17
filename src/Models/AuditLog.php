<?php

namespace AuditDiff\Laravel\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $auditable_type
 * @property string $auditable_id
 * @property string $event
 * @property string|null $actor_id
 * @property string|null $actor_type
 * @property array|null $diff
 * @property array|null $before
 * @property array|null $after
 * @property string|null $url
 * @property string|null $method
 * @property string|null $ip
 * @property string|null $user_agent
 * @property \Carbon\Carbon|null $created_at
 */
class AuditLog extends Model
{
    public $timestamps = false;

    protected $table = 'audit_logs';

    protected $guarded = [];

    protected $casts = [
        'diff' => 'array',
        'before' => 'array',
        'after' => 'array',
        'created_at' => 'datetime',
    ];
}
