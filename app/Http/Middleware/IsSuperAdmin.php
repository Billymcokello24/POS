<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class IsSuperAdmin
{
    /**
     * Allow request to proceed. Actual permission checks can be added where needed.
     */
    public function handle(Request $request, Closure $next)
    {
        if (!$request->user() || !$request->user()->is_super_admin) {
            abort(403, 'Access denied. Super admin required.');
        }

        return $next($request);
    }
}
