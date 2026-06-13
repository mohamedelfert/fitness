<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;

/**
 * JSON cast that stores with JSON_UNESCAPED_UNICODE so Arabic (and other non-ASCII) is
 * written as literal UTF-8 rather than \uXXXX escapes. This keeps localized values
 * substring-searchable via SQL LIKE on **both** MariaDB (raw-text JSON) and MySQL 8
 * (SESSION_HANDOFF §3 flags their JSON handling differs). Reads decode to an array.
 *
 * @implements CastsAttributes<array<string, mixed>, array<string, mixed>>
 */
class LocalizedJson implements CastsAttributes
{
    public function get($model, string $key, $value, array $attributes): ?array
    {
        return $value === null ? null : json_decode($value, true);
    }

    public function set($model, string $key, $value, array $attributes): array
    {
        return [$key => $value === null ? null : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)];
    }
}
