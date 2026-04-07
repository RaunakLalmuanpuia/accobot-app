<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * Automatically scopes all queries to the current tenant from the URL.
 *
 * Usage: add `use BelongsToTenant;` to any model that has a tenant_id column.
 *
 * - On tenant routes (/t/{tenant}/...): queries are filtered to that tenant only
 * - On non-tenant routes (admin, etc.): no filter applied — all records visible
 * - Use withoutGlobalScope('tenant') to opt out manually when needed
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope('tenant', function (Builder $query) {
            $tenant = request()->route('tenant');

            if ($tenant) {
                $query->where(
                    $query->getModel()->getTable() . '.tenant_id',
                    $tenant->id
                );
            }
        });
    }
}
