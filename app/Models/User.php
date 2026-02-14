<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'telephone',
        'team_id',
        'points',
        'level',
    ];

    protected static function booted()
    {
        static::saving(function ($user) {
            if ($user->isDirty('points')) {
                // Chaque palier de 1000 points augmente le level
                if ($user->points <= 0) {
                    $user->level = 1;
                } else {
                    $user->level = (int) floor(($user->points - 1) / 1000) + 1;
                }
            }
        });

        static::saved(function ($user) {
            if ($user->isDirty('team_id') && $user->team_id) {
                $user->teams()->syncWithoutDetaching([$user->team_id]);
            }
        });
    }

    /**
     * Historique des points de l'utilisateur
     */
    public function pointHistories()
    {
        return $this->hasMany(PointHistory::class);
    }

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    /**
     * Un utilisateur peut appartenir à plusieurs teams
     */
    public function teams()
    {
        return $this->belongsToMany(Team::class, 'team_user')->withTimestamps();
    }

    /**
     * Garder la relation team pour la compatibilité (renvoie la première équipe)
     */
    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Un utilisateur peut être lié à un client
     */
    public function client()
    {
        return $this->hasOne(Client::class);
    }

    public function projetsGeres()
    {
        return $this->hasMany(Projet::class, 'manager_id');
    }

    public function managedProjets() { return $this->projetsGeres(); }

    /**
     * Un utilisateur peut créer plusieurs tickets
     */
    public function ticketsCrees()
    {
        return $this->hasMany(Ticket::class, 'created_by');
    }

    public function createdTickets() { return $this->ticketsCrees(); }

    /**
     * Un utilisateur peut être assigné à plusieurs tickets
     */
    public function ticketsAssignes()
    {
        return $this->hasMany(Ticket::class, 'assigned_to');
    }

    public function assignedTickets() { return $this->ticketsAssignes(); }

    /**
     * Documents du collaborateur (relation polymorphique)
     */
    public function documents()
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    /**
     * Documents uploadés par l'utilisateur
     */
    public function documentsUploades()
    {
        return $this->hasMany(Document::class, 'uploaded_by');
    }

    /**
     * Commentaires de l'utilisateur
     */
    public function commentaires()
    {
        return $this->hasMany(TicketCommentaire::class);
    }

    /**
     * Pièces jointes uploadées par l'utilisateur
     */
    public function attachments()
    {
        return $this->hasMany(TicketAttachment::class);
    }

    /**
     * Meetings organisés par l'utilisateur
     */
    public function meetingsOrganises()
    {
        return $this->hasMany(Meeting::class, 'organisateur_id');
    }

    /**
     * Meetings auxquels l'utilisateur participe
     */
    public function meetingsParticipes()
    {
        return $this->belongsToMany(Meeting::class, 'meeting_participants')
            ->withPivot('statut_presence')
            ->withTimestamps();
    }

    /**
     * Notifications de l'utilisateur
     */
    public function notifications()
    {
        return $this->hasMany(Notification::class)->orderBy('created_at', 'desc');
    }

    /**
     * Vérifier si l'utilisateur est admin
     */
    public function isAdmin()
    {
        return $this->role === 'admin';
    }

    /**
     * Vérifier si l'utilisateur est manager
     */
    public function isManager()
    {
        return $this->role === 'manager';
    }

    /**
     * Vérifier si l'utilisateur est collaborateur
     */
    public function isCollaborateur()
    {
        return $this->role === 'collaborateur';
    }

    /**
     * Scope pour les admins
     */
    public function scopeAdmins($query)
    {
        return $query->where('role', 'admin');
    }

    /**
     * Scope pour les managers
     */
    public function scopeManagers($query)
    {
        return $query->where('role', 'manager');
    }

    /**
     * Scope pour les collaborateurs
     */
    public function scopeCollaborateurs($query)
    {
        return $query->where('role', 'collaborateur');
    }
}
