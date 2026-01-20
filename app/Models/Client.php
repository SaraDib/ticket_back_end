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
    ];

    /**
     * Un client peut avoir plusieurs projets
     */
    public function projets()
    {
        return $this->hasMany(Projet::class);
    }
}
