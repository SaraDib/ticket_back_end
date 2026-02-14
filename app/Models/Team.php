<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Team extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'nom',
        'description',
    ];

    /**
     * Une team a plusieurs collaborateurs (Many-to-Many)
     */
    public function members()
    {
        return $this->belongsToMany(User::class, 'team_user')->withTimestamps();
    }

    /**
     * Garder collaborateurs pour la compatibilité (One-to-Many)
     */
    public function collaborateurs()
    {
        return $this->hasMany(User::class);
    }

    /**
     * Récupérer uniquement les managers de la team
     */
    public function managers()
    {
        return $this->members()->where('role', 'manager');
    }

    /**
     * Une équipe peut travailler sur plusieurs projets
     */
    public function projets()
    {
        return $this->belongsToMany(Projet::class, 'projet_team')->withTimestamps();
    }
}
