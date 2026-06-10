<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Viree extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'public_id',
        'is_public',
        'started_at',
        'ended_at',
        'distance_m',
        'weather_snapshot',
        'ai_narrative',
    ];

    protected $casts = [
        'is_public' => 'boolean',
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'distance_m' => 'integer',
        'weather_snapshot' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (Viree $viree): void {
            $viree->public_id ??= (string) Str::uuid();
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function checkins(): HasMany
    {
        return $this->hasMany(Checkin::class)->orderBy('happened_at')->orderBy('id');
    }

    /** Virées en cours (pas encore clôturées). */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('ended_at');
    }
}
