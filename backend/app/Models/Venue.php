<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Venue extends Model
{
    use HasFactory;

    /** @var list<string> Ambiances NOCTAMBULE possibles pour un lieu. */
    public const MOODS = ['festif', 'chill', 'decouverte', 'afterwork'];

    protected $fillable = [
        'name',
        'slug',
        'address_line',
        'postal_code',
        'city',
        'mood',
        'capacity',
        'latitude',
        'longitude',
    ];

    protected $casts = [
        'capacity' => 'integer',
        'latitude' => 'float',
        'longitude' => 'float',
    ];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }
}

