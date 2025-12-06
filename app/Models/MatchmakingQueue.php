<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MatchmakingQueue extends Model
{
    use HasFactory;

    protected $table = 'matchmaking_queue';

    protected $fillable = [
        'user_id',
        'mode',
        'queued_at',
    ];

    protected $casts = [
        'queued_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function pvpProfile(): BelongsTo
    {
        return $this->belongsTo(PvpProfile::class, 'user_id', 'user_id');
    }
}
