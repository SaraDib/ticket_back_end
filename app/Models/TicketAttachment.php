<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TicketAttachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'ticket_id',
        'commentaire_id',
        'user_id',
        'fichier_path',
        'fichier_nom_original',
        'mime_type',
        'taille',
    ];

    protected $appends = ['url'];

    /**
     * Une pièce jointe appartient à un ticket
     */
    public function ticket()
    {
        return $this->belongsTo(Ticket::class);
    }

    /**
     * Une pièce jointe peut appartenir à un commentaire
     */
    public function commentaire()
    {
        return $this->belongsTo(TicketCommentaire::class, 'commentaire_id');
    }

    /**
     * Une pièce jointe est uploadée par un utilisateur
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Obtenir l'URL complète de la pièce jointe
     */
    public function getUrlAttribute()
    {
        return rtrim(config('app.url'), '/') . '/storage/' . $this->fichier_path;
    }
}
