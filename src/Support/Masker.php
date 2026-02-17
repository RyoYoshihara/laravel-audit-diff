<?php

namespace AuditDiff\Laravel\Support;

class Masker
{
    /**
     * @param array<string, mixed> $data
     * @param array<int, string> $maskKeys
     * @return array<string, mixed>
     */
    public static function mask(array $data, array $maskKeys): array
    {
        if (empty($maskKeys)) return $data;

        $maskMap = array_fill_keys(array_map('strtolower', $maskKeys), true);

        $walker = function ($value, $key) use (&$walker, $maskMap) {
            if (is_string($key) && isset($maskMap[strtolower($key)])) {
                return '***';
            }

            if (is_array($value)) {
                $out = [];
                foreach ($value as $k => $v) {
                    $out[$k] = $walker($v, $k);
                }
                return $out;
            }

            return $value;
        };

        $out = [];
        foreach ($data as $k => $v) {
            $out[$k] = $walker($v, $k);
        }

        return $out;
    }
}
