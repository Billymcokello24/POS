<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\PreventRequestsDuringMaintenance as Base;

class CheckMaintenanceMode extends Base
{
    // Extends Laravel's default maintenance handling; no changes needed.
}
