<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Document extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'nom',
        'type',
        'fichier_path',
        'fichier_nom_original',
        'mime_type',
        'taille',
        'documentable_id',
        'documentable_type',
        'uploaded_by',
    ];

    protected $appends = ['url'];

    /**
     * Relation polymorphique - peut être lié à un Projet ou un User
     */
    public function documentable()
    {
        return $this->morphTo();
    }

    /**
     * Document uploadé par un utilisateur
     */
    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Obtenir l'URL complète du document
     */
    public function getUrlAttribute()
    {
        return rtrim(config('app.url'), '/') . '/storage/' . $this->fichier_path;
    }

    /**
     * Scope pour documents de projets
     */
    public function scopeProjet($query)
    {
        return $query->where('documentable_type', Projet::class);
    }

    /**
     * Scope pour documents de collaborateurs
     */
    public function scopeCollaborateur($query)
    {
        return $query->where('documentable_type', User::class);
    }
}
