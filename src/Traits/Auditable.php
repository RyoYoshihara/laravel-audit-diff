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
        static::updated(function (Model $model) {
            if (!config('audit-diff.enabled', true)) return;

            $events = (array) config('audit-diff.events', ['updated']);
            if (!in_array('updated', $events, true)) return;

            // changed attributes (dirty) AFTER save, we need the change set
            // Eloquent provides getChanges() which should contain updated keys (including updated_at)
            $changes = $model->getChanges();
            if (empty($changes)) return;

            // Determine original values for changed keys
            $before = [];
            $after = [];
            $diff = [];

            $nullEqualsEmpty = (bool) config('audit-diff.null_equals_empty_string', true);
            $skipTimestampsOnly = (bool) config('audit-diff.skip_if_only_timestamps_changed', true);
            $storeFullSnapshot = (bool) config('audit-diff.store_full_snapshot', false);

            // keys to exclude from diff by default (timestamps)
            $timestampKeys = array_filter([
                $model->getCreatedAtColumn(),
                $model->getUpdatedAtColumn(),
            ]);

            // If store_full_snapshot is enabled, we will store entire attribute set in before/after,
            // but diff remains only changed keys.
            if ($storeFullSnapshot) {
                $beforeSnapshot = $model->getOriginal();
                $afterSnapshot = $model->getAttributes();
            }

            foreach (array_keys($changes) as $key) {
                $old = $model->getOriginal($key);
                $new = Arr::get($model->getAttributes(), $key);

                if ($nullEqualsEmpty) {
                    $old = ($old === '') ? null : $old;
                    $new = ($new === '') ? null : $new;
                }

                // If no actual semantic change, skip key
                if ($old === $new) {
                    continue;
                }

                $before[$key] = $old;
                $after[$key] = $new;

                $diff[$key] = [
                    'before' => $old,
                    'after' => $new,
                ];
            }

            // If only timestamps changed, skip
            if ($skipTimestampsOnly) {
                $nonTimestampKeys = array_diff(array_keys($diff), $timestampKeys);
                if (empty($nonTimestampKeys)) {
                    return;
                }
            }

            // If diff is empty after normalization, skip
            if (empty($diff)) return;

            // Apply masking
            $maskKeys = (array) config('audit-diff.mask_keys', []);
            if (!empty($maskKeys)) {
                if ($storeFullSnapshot) {
                    $beforeSnapshot = Masker::mask((array) $beforeSnapshot, $maskKeys);
                    $afterSnapshot  = Masker::mask((array) $afterSnapshot, $maskKeys);
                } else {
                    $before = Masker::mask($before, $maskKeys);
                    $after  = Masker::mask($after, $maskKeys);
                }

                // diff uses same primitive values, rebuild from masked before/after for changed keys
                $maskedDiff = [];
                foreach ($diff as $k => $_) {
                    $maskedDiff[$k] = [
                        'before' => $storeFullSnapshot ? Arr::get($beforeSnapshot, $k) : Arr::get($before, $k),
                        'after'  => $storeFullSnapshot ? Arr::get($afterSnapshot, $k) : Arr::get($after, $k),
                    ];
                }
                $diff = $maskedDiff;
            }

            // Actor
            $actor = ActorResolver::resolve();

            // Request metadata (nullable for CLI/queue)
            $meta = self::resolveRequestMeta();

            // Save
            $log = new AuditLog();
            $log->auditable_type = get_class($model);
            $log->auditable_id = (string) $model->getKey();
            $log->event = 'updated';
            $log->actor_id = $actor['id'];
            $log->actor_type = $actor['type'];

            $log->diff = $diff;
            if ($storeFullSnapshot) {
                $log->before = $beforeSnapshot ?? null;
                $log->after  = $afterSnapshot ?? null;
            } else {
                $log->before = $before;
                $log->after  = $after;
            }

            $log->url = $meta['url'];
            $log->method = $meta['method'];
            $log->ip = $meta['ip'];
            $log->user_agent = $meta['user_agent'];

            $log->created_at = now();
            $log->save();
        });
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
