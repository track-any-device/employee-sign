<?php

namespace App\Support;

/**
 * The tenant the current request / job acts for.
 *
 * Tenancy is not implemented yet: web users always act for the "null"
 * tenant (a Custom, single-company install). Device requests act for the
 * tenant of the authenticated device. When tenants arrive, a route
 * middleware for /{tenant}/... only has to call set() after checking access.
 */
class CurrentTenant
{
    private ?int $id = null;

    public function id(): ?int
    {
        return $this->id;
    }

    public function set(?int $id): void
    {
        $this->id = $id;
    }

    /** Channel-safe key for the tenant ("none" for the null tenant). */
    public function key(): string
    {
        return self::keyFor($this->id);
    }

    public static function keyFor(?int $id): string
    {
        return $id === null ? 'none' : (string) $id;
    }
}
