<?php

namespace AuditDiff\Laravel\Traits;

use AuditDiff\Laravel\Models\AuditLog;
use AuditDiff\Laravel\Support\ActorResolver;
use AuditDiff\Laravel\Support\Masker;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(function (Model $model) {
            self::recordEvent($model, 'created');
        });

        static::updated(function (Model $model) {
            self::recordEvent($model, 'updated');
        });

        static::deleted(function (Model $model) {
            self::recordEvent($model, 'deleted');
        });
    }

    private static function recordEvent(Model $model, string $event): void
    {
        if (!config('audit-diff.enabled', true)) return;

        $events = (array) config('audit-diff.events', ['updated']);
        if (!in_array($event, $events, true)) return;

        $nullEqualsEmpty = (bool) config('audit-diff.null_equals_empty_string', true);
        $skipTimestampsOnly = (bool) config('audit-diff.skip_if_only_timestamps_changed', true);
        $storeFullSnapshot = (bool) config('audit-diff.store_full_snapshot', false);

        $maskKeys = (array) config('audit-diff.mask_keys', []);
        $excludeKeys = (array) config('audit-diff.exclude_keys', []);

        // timestamps keys
        $timestampKeys = array_filter([
            $model->getCreatedAtColumn(),
            $model->getUpdatedAtColumn(),
        ]);

        // Normalize snapshots
        $original = $model->getOriginal();
        $attributes = $model->getAttributes();

        // exclude keys (also exclude timestamps by default? -> not automatically, only config)
        $original = self::excludeKeys($original, $excludeKeys);
        $attributes = self::excludeKeys($attributes, $excludeKeys);

        // Apply null/"" normalization on snapshots (for created/deleted snapshot storage)
        if ($nullEqualsEmpty) {
            $original = self::normalizeNullEmpty($original);
            $attributes = self::normalizeNullEmpty($attributes);
        }

        $diff = null;
        $before = null;
        $after = null;

        if ($event === 'updated') {
            $changes = $model->getChanges();
            if (empty($changes)) return;

            // Remove excluded keys from changes early
            foreach ($excludeKeys as $k) {
                unset($changes[$k]);
            }

            $beforePartial = [];
            $afterPartial = [];
            $diffPartial = [];

            foreach (array_keys($changes) as $key) {
                $old = $model->getOriginal($key);
                $new = Arr::get($model->getAttributes(), $key);

                if (in_array($key, $excludeKeys, true)) {
                    continue;
                }

                if ($nullEqualsEmpty) {
                    $old = ($old === '') ? null : $old;
                    $new = ($new === '') ? null : $new;
                }

                if ($old === $new) {
                    continue;
                }

                $beforePartial[$key] = $old;
                $afterPartial[$key] = $new;

                $diffPartial[$key] = [
                    'before' => $old,
                    'after' => $new,
                ];
            }

            if (empty($diffPartial)) return;

            // only timestamps?
            if ($skipTimestampsOnly) {
                $nonTimestampKeys = array_diff(array_keys($diffPartial), $timestampKeys);
                if (empty($nonTimestampKeys)) {
                    return;
                }
            }

            // decide before/after storage
            if ($storeFullSnapshot) {
                $before = $original;
                $after = $attributes;
            } else {
                $before = $beforePartial;
                $after = $afterPartial;
            }

            $diff = $diffPartial;
        }

        if ($event === 'created') {
            // created: store snapshot (after) / diff null
            $diff = null;

            if ($storeFullSnapshot) {
                $before = null;
                $after = $attributes;
            } else {
                // MVP: afterに全属性（除外済み）を入れる
                $before = null;
                $after = $attributes;
            }
        }

        if ($event === 'deleted') {
            // deleted: store snapshot (before) / diff null
            $diff = null;

            if ($storeFullSnapshot) {
                $before = $original;
                $after = null;
            } else {
                // MVP: beforeに全属性（除外済み）を入れる
                $before = $original;
                $after = null;
            }
        }

        // Masking (apply to before/after + diff)
        if (!empty($maskKeys)) {
            if (is_array($before)) $before = Masker::mask($before, $maskKeys);
            if (is_array($after)) $after = Masker::mask($after, $maskKeys);

            if (is_array($diff)) {
                $maskedDiff = [];
                foreach ($diff as $k => $_) {
                    $maskedDiff[$k] = [
                        'before' => is_array($before) ? Arr::get($before, $k) : ($diff[$k]['before'] ?? null),
                        'after'  => is_array($after) ? Arr::get($after, $k) : ($diff[$k]['after'] ?? null),
                    ];
                }
                $diff = $maskedDiff;
            }
        }

        $actor = ActorResolver::resolve();
        $meta = self::resolveRequestMeta();

        $log = new AuditLog();
        $log->auditable_type = get_class($model);
        $log->auditable_id = (string) $model->getKey();
        $log->event = $event;

        $log->actor_id = $actor['id'];
        $log->actor_type = $actor['type'];

        $log->diff = $diff;
        $log->before = $before;
        $log->after = $after;

        $log->url = $meta['url'];
        $log->method = $meta['method'];
        $log->ip = $meta['ip'];
        $log->user_agent = $meta['user_agent'];

        $log->created_at = now();
        $log->save();
    }

    /** @param array<string,mixed> $data */
    private static function excludeKeys(array $data, array $excludeKeys): array
    {
        foreach ($excludeKeys as $k) {
            unset($data[$k]);
        }
        return $data;
    }

    /** @param array<string,mixed> $data */
    private static function normalizeNullEmpty(array $data): array
    {
        $out = [];
        foreach ($data as $k => $v) {
            if (is_array($v)) {
                $out[$k] = self::normalizeNullEmpty($v);
            } else {
                $out[$k] = ($v === '') ? null : $v;
            }
        }
        return $out;
    }

    /**
     * @return array{url: string|null, method: string|null, ip: string|null, user_agent: string|null}
     */
    private static function resolveRequestMeta(): array
    {
        try {
            /** @var Request|null $req */
            $req = request();
            if (!$req) {
                return ['url' => null, 'method' => null, 'ip' => null, 'user_agent' => null];
            }

            return [
                'url' => $req->fullUrl(),
                'method' => $req->method(),
                'ip' => $req->ip(),
                'user_agent' => $req->userAgent(),
            ];
        } catch (\Throwable $e) {
            return ['url' => null, 'method' => null, 'ip' => null, 'user_agent' => null];
        }
    }
}
