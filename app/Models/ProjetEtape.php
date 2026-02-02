<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProjetEtape extends Model
{
    use HasFactory;

    protected $fillable = [
        'projet_id',
        'nom',
        'description',
        'ordre',
        'statut',
        'date_debut',
        'date_fin',
    ];

    protected $casts = [
        'date_debut' => 'date',
        'date_fin' => 'date',
    ];

    /**
     * Une étape appartient à un projet
     */
    public function projet()
    {
        return $this->belongsTo(Projet::class);
    }

    /**
     * Une étape a plusieurs tickets
     */
    public function tickets()
    {
        return $this->hasMany(Ticket::class, 'etape_id');
    }
}
