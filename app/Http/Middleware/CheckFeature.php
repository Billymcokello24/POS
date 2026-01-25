<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckFeature
{
    /**
     * Allow request through by default. If feature gates are configured, implement checks here.
     */
    public function handle(Request $request, Closure $next, $feature = null)
    {
        return $next($request);
    }
}
