<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Projet extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'nom',
        'type',
        'description',
        'client_id',
        'manager_id',
        'github_links',
        'avancement_realise',
        'avancement_prevu',
        'date_debut',
        'date_fin_prevue',
        'date_fin_reelle',
        'statut',
    ];

    protected $casts = [
        'date_debut' => 'date',
        'date_fin_prevue' => 'date',
        'date_fin_reelle' => 'date',
    ];

    /**
     * Un projet appartient à un client (pour projets externes)
     */
    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * Un projet est géré par un manager
     */
    public function manager()
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    /**
     * Un projet peut être affecté à plusieurs équipes
     */
    public function teams()
    {
        return $this->belongsToMany(Team::class, 'projet_team')->withTimestamps();
    }

    /**
     * Un projet a plusieurs étapes
     */
    public function etapes()
    {
        return $this->hasMany(ProjetEtape::class)->orderBy('ordre');
    }

    /**
     * Un projet a plusieurs tickets
     */
    public function tickets()
    {
        return $this->hasMany(Ticket::class);
    }

    /**
     * Un projet peut avoir plusieurs documents (relation polymorphique)
     */
    public function documents()
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    /**
     * Un projet peut avoir plusieurs meetings
     */
    public function meetings()
    {
        return $this->hasMany(Meeting::class);
    }

    /**
     * Scope pour les projets internes (Rakops)
     */
    public function scopeInternes($query)
    {
        return $query->where('type', 'interne');
    }

    /**
     * Scope pour les projets externes
     */
    public function scopeExternes($query)
    {
        return $query->where('type', 'externe');
    }
}
