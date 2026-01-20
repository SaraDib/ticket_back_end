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
     * Une team a plusieurs collaborateurs
     */
    public function collaborateurs()
    {
        return $this->hasMany(User::class);
    }

    /**
     * Alias pour collaborateurs (utilisé dans certains contrôleurs)
     */
    public function members()
    {
        return $this->collaborateurs();
    }

    /**
     * Récupérer uniquement les managers de la team
     */
    public function managers()
    {
        return $this->hasMany(User::class)->where('role', 'manager');
    }
}
