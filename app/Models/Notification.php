<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'type',
        'titre',
        'message',
        'data',
        'lu',
        'envoye',
        'canal',
        'lu_at',
        'envoye_at',
    ];

    protected $casts = [
        'data' => 'array',
        'lu' => 'boolean',
        'envoye' => 'boolean',
        'lu_at' => 'datetime',
        'envoye_at' => 'datetime',
    ];

    /**
     * Une notification appartient à un utilisateur
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Marquer la notification comme lue
     */
    public function marquerCommeLue()
    {
        $this->update([
            'lu' => true,
            'lu_at' => now(),
        ]);
    }

    /**
     * Marquer la notification comme envoyée
     */
    public function marquerCommeEnvoyee()
    {
        $this->update([
            'envoye' => true,
            'envoye_at' => now(),
        ]);
    }

    /**
     * Scope pour notifications non lues
     */
    public function scopeNonLues($query)
    {
        return $query->where('lu', false);
    }

    /**
     * Scope pour notifications non envoyées
     */
    public function scopeNonEnvoyees($query)
    {
        return $query->where('envoye', false);
    }
}
