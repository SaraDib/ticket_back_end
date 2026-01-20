<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Ticket extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'titre',
        'description',
        'projet_id',
        'created_by',
        'assigned_to',
        'statut',
        'priorite',
        'heures_estimees',
        'heures_reelles',
        'deadline',
    ];

    protected $casts = [
        'deadline' => 'datetime',
        'heures_estimees' => 'decimal:2',
        'heures_reelles' => 'decimal:2',
    ];

    /**
     * Un ticket appartient à un projet
     */
    public function projet()
    {
        return $this->belongsTo(Projet::class);
    }

    /**
     * Un ticket est créé par un utilisateur
     */
    public function createur()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function createdBy()
    {
        return $this->createur();
    }

    /**
     * Un ticket est assigné à un collaborateur
     */
    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function assignedTo()
    {
        return $this->assignee();
    }

    /**
     * Un ticket a plusieurs commentaires
     */
    public function commentaires()
    {
        return $this->hasMany(TicketCommentaire::class)->orderBy('created_at', 'desc');
    }

    /**
     * Un ticket a plusieurs pièces jointes
     */
    public function attachments()
    {
        return $this->hasMany(TicketAttachment::class);
    }

    /**
     * Scope pour tickets ouverts
     */
    public function scopeOuverts($query)
    {
        return $query->where('statut', 'ouvert');
    }

    /**
     * Scope pour tickets en cours
     */
    public function scopeEnCours($query)
    {
        return $query->where('statut', 'en_cours');
    }

    /**
     * Scope pour tickets urgents
     */
    public function scopeUrgents($query)
    {
        return $query->where('priorite', 'urgente');
    }

    /**
     * Vérifier si le ticket est en retard
     */
    public function getEstEnRetardAttribute()
    {
        return $this->deadline && now()->greaterThan($this->deadline) && !in_array($this->statut, ['resolu', 'ferme']);
    }
}
