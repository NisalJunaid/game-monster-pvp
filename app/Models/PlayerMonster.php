<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PlayerMonster extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'species_id',
        'level',
        'exp',
        'current_hp',
        'max_hp',
        'nickname',
        'is_in_team',
        'team_slot',
    ];

    protected $casts = [
        'is_in_team' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function species()
    {
        return $this->belongsTo(MonsterSpecies::class, 'species_id');
    }
}
