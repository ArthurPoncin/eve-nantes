<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Challenge extends Model
{
    /** Clé primaire lisible ('explorateur-du-mois'…), pas d'auto-incrément. */
    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'label',
        'description',
        'icon',
        'criteria',
        'starts_at',
        'ends_at',
    ];

    protected $casts = [
        'criteria' => 'array',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_challenges')
            ->withPivot('progress', 'completed_at');
    }

    /** Défis dont la fenêtre est ouverte en ce moment. */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('starts_at', '<=', now())->where('ends_at', '>=', now());
    }
}
