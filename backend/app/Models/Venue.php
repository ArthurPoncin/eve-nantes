<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

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

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function checkins(): HasMany
    {
        return $this->hasMany(Checkin::class);
    }

    /**
     * Le prochain evenement publie a venir (le plus proche dans le temps).
     *
     * Permet d'afficher « ce qui arrive » sur les cartes de la liste sans
     * charger toute la programmation de chaque lieu.
     */
    public function nextEvent(): HasOne
    {
        return $this->hasOne(Event::class)->ofMany(
            ['starts_at' => 'min'],
            fn (Builder $query) => $query
                ->where('is_published', true)
                ->where('starts_at', '>=', now())
        );
    }
}

