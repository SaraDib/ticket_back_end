<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Client extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'nom',
        'ice',
        'identifiant_fiscal',
        'telephone',
        'email',
        'adresse',
        'user_id',
    ];

    /**
     * Un client peut avoir un compte utilisateur
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Un client peut avoir plusieurs projets
     */
    public function projets()
    {
        return $this->hasMany(Projet::class);
    }

    /**
     * Un client peut faire plusieurs demandes de tickets
     */
    public function ticketRequests()
    {
        return $this->hasMany(TicketRequest::class);
    }
}
