<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Badge extends Model
{
    /** Clé primaire lisible ('noctambule', 'fidele'…), pas d'auto-incrément. */
    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'label',
        'description',
        'icon',
        'criteria',
    ];

    protected $casts = [
        'criteria' => 'array',
    ];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_badges')->withPivot('unlocked_at');
    }
}
