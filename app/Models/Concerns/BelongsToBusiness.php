<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * Automatically scopes all queries to the current business from the URL.
 *
 * Usage: add `use BelongsToBusiness;` to any model that has a business_id column.
 *
 * - On business routes (/b/{business}/...): queries are filtered to that business only
 * - On non-business routes (admin, etc.): no filter applied — all records visible
 * - Use withoutGlobalScope('business') to opt out manually when needed
 */
trait BelongsToBusiness
{
    public static function bootBelongsToBusiness(): void
    {
        static::addGlobalScope('business', function (Builder $query) {
            $business = request()->route('business');

            if ($business) {
                $query->where(
                    $query->getModel()->getTable() . '.business_id',
                    $business->id
                );
            }
        });
    }
}
