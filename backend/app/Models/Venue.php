<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Venue extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'address_line',
        'postal_code',
        'city',
        'capacity',
        'latitude',
        'longitude',
    ];

    protected $casts = [
        'capacity' => 'integer',
        'latitude' => 'float',
        'longitude' => 'float',
    ];

    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }
}

