<?php

namespace AuditDiff\Laravel\Support;

use Illuminate\Support\Facades\Auth;

class ActorResolver
{
    /**
     * @return array{id: string|null, type: string|null}
     */
    public static function resolve(): array
    {
        $resolver = config('audit-diff.actor_resolver');

        // Custom resolver (Closure)
        if (is_callable($resolver)) {
            try {
                $r = $resolver();

                $id = isset($r['id']) ? (string) $r['id'] : null;
                $type = isset($r['type']) ? (string) $r['type'] : null;

                return [
                    'id' => $id !== '' ? $id : null,
                    'type' => $type !== '' ? $type : null,
                ];
            } catch (\Throwable $e) {
                // Resolver failure should never break app logic
                return ['id' => null, 'type' => null];
            }
        }

        // Default: Laravel auth
        try {
            $user = Auth::user();
            if (!$user) return ['id' => null, 'type' => null];

            // id can be int/uuid/ulid, unify to string
            $id = method_exists($user, 'getAuthIdentifier') ? $user->getAuthIdentifier() : ($user->id ?? null);
            $type = get_class($user);

            return [
                'id' => $id !== null ? (string) $id : null,
                'type' => $type ?: null,
            ];
        } catch (\Throwable $e) {
            return ['id' => null, 'type' => null];
        }
    }
}
