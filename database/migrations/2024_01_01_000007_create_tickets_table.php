<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->string('titre');
            $table->text('description')->nullable();
            $table->foreignId('projet_id')->constrained('projets')->onDelete('cascade');
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->onDelete('set null');
            
            // Cycle de vie du ticket
            $table->enum('statut', [
                'ouvert',
                'en_cours',
                'en_attente',
                'resolu',
                'ferme',
                'rejete'
            ])->default('ouvert');
            
            $table->enum('priorite', ['basse', 'normale', 'haute', 'urgente'])->default('normale');
            
            // Mesurable
            $table->decimal('heures_estimees', 8, 2)->nullable();
            $table->decimal('heures_reelles', 8, 2)->default(0);
            $table->dateTime('deadline')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
