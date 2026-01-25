<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;

trait BelongsToBusiness
{
    /**
     * Scope a query to only include models for the given business id.
     */
    public function scopeForBusiness(Builder $query, $businessId)
    {
        return $query->where('business_id', $businessId);
    }

    /**
     * Scope a query to only include models for the current user's business.
     */
    public function scopeForCurrentBusiness(Builder $query)
    {
        $businessId = auth()->user()?->current_business_id ?? null;

        if ($businessId === null) {
            return $query;
        }

        return $query->where('business_id', $businessId);
    }
}
