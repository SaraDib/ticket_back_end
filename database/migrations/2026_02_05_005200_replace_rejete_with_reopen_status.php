<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Étape 1: Convertir tous les tickets 'rejete' en 'ferme'
        DB::table('tickets')
            ->where('statut', 'rejete')
            ->update(['statut' => 'ferme']);
        
        // Étape 2: Modifier la colonne pour enlever 'rejete' et ajouter 'reopen'
        DB::statement("ALTER TABLE tickets MODIFY COLUMN statut ENUM('en_attente', 'en_cours', 'reopen', 'resolu', 'ferme') DEFAULT 'en_attente'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Convertir les 'reopen' en 'en_cours' avant de restaurer
        DB::table('tickets')
            ->where('statut', 'reopen')
            ->update(['statut' => 'en_cours']);
            
        // Restaurer l'ancien enum avec 'rejete' sans 'reopen'
        DB::statement("ALTER TABLE tickets MODIFY COLUMN statut ENUM('en_attente', 'en_cours', 'resolu', 'ferme', 'rejete') DEFAULT 'en_attente'");
    }
};
