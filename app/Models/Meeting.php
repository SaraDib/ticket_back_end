<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Meeting extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'titre',
        'description',
        'date_heure',
        'duree_minutes',
        'lieu',
        'lien_visio',
        'organisateur_id',
        'projet_id',
        'compte_rendu',
        'statut',
    ];

    protected $casts = [
        'date_heure' => 'datetime',
    ];

    /**
     * Un meeting est organisé par un utilisateur
     */
    public function organisateur()
    {
        return $this->belongsTo(User::class, 'organisateur_id');
    }

    /**
     * Un meeting peut être lié à un projet
     */
    public function projet()
    {
        return $this->belongsTo(Projet::class);
    }

    /**
     * Un meeting a plusieurs participants (many-to-many)
     */
    public function participants()
    {
        return $this->belongsToMany(User::class, 'meeting_participants')
            ->withPivot('statut_presence')
            ->withTimestamps();
    }

    /**
     * Scope pour meetings planifiés
     */
    public function scopePlanifies($query)
    {
        return $query->where('statut', 'planifie');
    }

    /**
     * Scope pour meetings à venir (dans le futur)
     */
    public function scopeAVenir($query)
    {
        return $query->where('date_heure', '>', now());
    }
}
