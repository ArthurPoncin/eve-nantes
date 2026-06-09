<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Soiree extends Model
{
    use HasFactory;

    protected $table = 'soirees';

    protected $fillable = [
        'user_id',
        'venue_id',
        'event_id',
        'mood',
        'weather_snapshot',
        'ai_narrative',
        'shared_with',
    ];

    protected $casts = [
        'weather_snapshot' => 'array',
        'shared_with' => 'array',
    ];

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
