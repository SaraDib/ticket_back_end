<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Ticket extends Model
{
    use HasFactory, SoftDeletes;

    protected static function booted()
    {
        static::saving(function ($ticket) {
            if ($ticket->isDirty('statut')) {
                $oldStatus = $ticket->getOriginal('statut');
                $newStatus = $ticket->statut;

                // Marquer le début du travail
                if ($oldStatus === 'en_attente' && $newStatus === 'en_cours') {
                    if (!$ticket->opened_at) {
                        $ticket->opened_at = now();
                    }
                }

                // Calculer les heures réelles à la résolution
                if ($newStatus === 'resolu' && $ticket->opened_at) {
                    $ticket->heures_reelles = $ticket->calculateActualHours($ticket->opened_at, now());
                }

                // Pénalité : Si le ticket repasse de 'resolu' à 'en_cours'
                if ($oldStatus === 'resolu' && $newStatus === 'en_cours') {
                    $currentPoints = $ticket->reward_points;
                    if ($currentPoints > 0) {
                        // Réduction de 20% et marquage explicite pour l'enregistrement
                        $newPoints = (int) round($currentPoints * 0.8);
                        $ticket->reward_points = $newPoints;
                    }
                    
                    // Notification du collaborateur
                    if ($ticket->assigned_to) {
                        $assignee = User::find($ticket->assigned_to);
                        if ($assignee) {
                            \App\Services\NotificationService::send(
                                $assignee, 
                                'Ticket RENVOYÉ : Travail Incomplet', 
                                "Le ticket #{$ticket->id} a été remis en statut '{$newStatus}'. Une pénalité de 20% a été appliquée sur les points de récompense.",
                                ['system', 'whatsapp']
                            );
                        }
                    }
                }
            }
        });

        // Attribution des points APRES l'enregistrement réussi du statut 'ferme'
        static::updated(function ($ticket) {
            $oldStatus = $ticket->getOriginal('statut');
            $newStatus = $ticket->statut;

            if ($newStatus === 'ferme' && $oldStatus !== 'ferme') {
                if ($ticket->reward_points > 0 && $ticket->assigned_to) {
                    $user = User::find($ticket->assigned_to);
                    if ($user) {
                        // On utilise save() au lieu de increment() pour déclencher les model events (Level up)
                        $user->points += $ticket->reward_points;
                        $user->save();
                        
                        PointHistory::create([
                            'user_id' => $user->id,
                            'ticket_id' => $ticket->id,
                            'points' => $ticket->reward_points,
                            'description' => "Récompense pour la résolution finale et fermeture du ticket: {$ticket->titre}",
                        ]);
                    }
                }
            }
        });
    }

    public function calculateActualHours($start, $end)
    {
        $start = \Carbon\Carbon::parse($start);
        $end = \Carbon\Carbon::parse($end);
        
        $totalMinutes = 0;
        $current = clone $start;
        
        while ($current->lt($end)) {
            $next = (clone $current)->addDay()->startOfDay();
            if ($next->gt($end)) {
                $next = clone $end;
            }
            
            if (!$current->isWeekend()) {
                $totalMinutes += $current->diffInMinutes($next);
            }
            
            $current = $next;
        }
        
        // Conversion de minutes réelles en heures de travail (1440 min réelles = 8h de travail)
        return round(($totalMinutes / 1440) * 8, 2);
    }

    protected $fillable = [
        'titre',
        'description',
        'projet_id',
        'etape_id',
        'created_by',
        'assigned_to',
        'statut',
        'priorite',
        'heures_estimees',
        'heures_reelles',
        'reward_points',
        'deadline',
        'opened_at',
    ];

    protected $casts = [
        'deadline' => 'datetime',
        'opened_at' => 'datetime',
        'heures_estimees' => 'decimal:2',
        'heures_reelles' => 'decimal:2',
        'reward_points' => 'integer',
    ];

    /**
     * Un ticket appartient à un projet
     */
    public function projet()
    {
        return $this->belongsTo(Projet::class);
    }

    /**
     * Un ticket appartient à une étape de projet (optionnel)
     */
    public function etape()
    {
        return $this->belongsTo(ProjetEtape::class, 'etape_id');
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
     * Scope pour tickets en cours (alias pour compatibilité)
     */
    public function scopeOuverts($query)
    {
        return $query->where('statut', 'en_cours');
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
