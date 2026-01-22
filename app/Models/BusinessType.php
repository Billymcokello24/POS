<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BusinessType extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'default_categories',
        'default_settings',
        'is_active',
    ];

    protected $casts = [
        'default_categories' => 'array',
        'default_settings' => 'array',
        'is_active' => 'boolean',
    ];
}
