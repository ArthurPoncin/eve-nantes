<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Checkin extends Model
{
    use HasFactory;

    protected $fillable = [
        'viree_id',
        'user_id',
        'venue_id',
        'happened_at',
    ];

    protected $casts = [
        'happened_at' => 'datetime',
    ];

    public function viree(): BelongsTo
    {
        return $this->belongsTo(Viree::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }
}
