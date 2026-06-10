<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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

    /** Ceux qui ont trinqué (« Santé ! ») à cette virée. */
    public function kudosGivers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'kudos')->withTimestamps();
    }

    /** Virées en cours (pas encore clôturées). */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('ended_at');
    }

    /**
     * Une virée privée n'est visible que de son auteur et de ses abonnés ;
     * une virée publique (défaut) est visible de tous.
     */
    public function isVisibleTo(?User $viewer): bool
    {
        if ($this->is_public) {
            return true;
        }
        if ($viewer === null) {
            return false;
        }

        return $viewer->id === $this->user_id || $viewer->isFollowing($this->user);
    }

    /** Restreint la requête aux virées visibles par ce spectateur. */
    public function scopeVisibleTo(Builder $query, ?User $viewer): Builder
    {
        if ($viewer === null) {
            return $query->where('is_public', true);
        }

        $followedIds = $viewer->following()->pluck('users.id');

        return $query->where(function (Builder $inner) use ($viewer, $followedIds): void {
            $inner->where('is_public', true)
                ->orWhere('user_id', $viewer->id)
                ->orWhereIn('user_id', $followedIds);
        });
    }
}
