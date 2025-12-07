<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class EncounterTicket extends Model
{
    use HasFactory;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_RESOLVED = 'resolved';

    protected $fillable = [
        'user_id',
        'zone_id',
        'species_id',
        'rolled_level',
        'seed',
        'status',
        'expires_at',
        'integrity_hash',
    ];

    protected $casts = [
        'seed' => 'int',
        'rolled_level' => 'int',
        'battle_state' => 'array',
        'expires_at' => 'datetime',
    ];

    protected $hidden = [
        'integrity_hash',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function zone()
    {
        return $this->belongsTo(Zone::class);
    }

    public function species()
    {
        return $this->belongsTo(MonsterSpecies::class, 'species_id');
    }

    public function isExpired(): bool
    {
        return $this->expires_at instanceof Carbon && $this->expires_at->isPast();
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE && ! $this->isExpired();
    }
}
