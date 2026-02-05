<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TicketCommentaire extends Model
{
    use HasFactory;

    protected $fillable = [
        'ticket_id',
        'user_id',
        'commentaire',
    ];

    /**
     * Un commentaire appartient à un ticket
     */
    public function ticket()
    {
        return $this->belongsTo(Ticket::class);
    }

    /**
     * Un commentaire est écrit par un utilisateur
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Un commentaire peut avoir des pièces jointes
     */
    public function attachments()
    {
        return $this->hasMany(TicketAttachment::class, 'commentaire_id');
    }
}
