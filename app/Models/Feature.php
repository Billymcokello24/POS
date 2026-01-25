<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Feature extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'key',
        'description',
    ];

    public function businesses()
    {
        return $this->belongsToMany(Business::class, 'business_feature')
            ->withPivot('is_enabled')
            ->withTimestamps();
    }

    public function plans()
    {
        return $this->belongsToMany(Plan::class);
    }
}
