<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TicketRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'titre',
        'description',
        'projet_id',
        'client_id',
        'etape_id',
        'priorite',
        'statut',
        'raison_rejet',
        'validateur_id',
        'ticket_id',
        'validated_at',
    ];

    protected $casts = [
        'validated_at' => 'datetime',
    ];

    /**
     * Une demande appartient à un projet
     */
    public function projet()
    {
        return $this->belongsTo(Projet::class);
    }

    /**
     * Une demande appartient à un client
     */
    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * Une demande peut être liée à une étape
     */
    public function etape()
    {
        return $this->belongsTo(ProjetEtape::class, 'etape_id');
    }

    /**
     * Une demande peut être validée par un admin
     */
    public function validateur()
    {
        return $this->belongsTo(User::class, 'validateur_id');
    }

    /**
     * Une demande approuvée génère un ticket
     */
    public function ticket()
    {
        return $this->belongsTo(Ticket::class);
    }

    /**
     * Scope pour les demandes en attente
     */
    public function scopeEnAttente($query)
    {
        return $query->where('statut', 'en_attente');
    }

    /**
     * Scope pour les demandes approuvées
     */
    public function scopeApprouve($query)
    {
        return $query->where('statut', 'approuve');
    }

    /**
     * Scope pour les demandes rejetées
     */
    public function scopeRejete($query)
    {
        return $query->where('statut', 'rejete');
    }
}
