<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PvpProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'mmr',
        'wins',
        'losses',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
