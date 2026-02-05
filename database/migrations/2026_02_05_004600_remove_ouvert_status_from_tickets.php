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
        // Étape 1: Convertir tous les tickets 'ouvert' en 'en_cours'
        DB::table('tickets')
            ->where('statut', 'ouvert')
            ->update(['statut' => 'en_cours']);
        
        // Étape 2: Modifier la colonne pour enlever 'ouvert' de l'enum
        DB::statement("ALTER TABLE tickets MODIFY COLUMN statut ENUM('en_attente', 'en_cours', 'resolu', 'ferme', 'rejete') DEFAULT 'en_attente'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Restaurer l'ancien enum avec 'ouvert'
        DB::statement("ALTER TABLE tickets MODIFY COLUMN statut ENUM('ouvert', 'en_attente', 'en_cours', 'resolu', 'ferme', 'rejete') DEFAULT 'en_attente'");
    }
};
