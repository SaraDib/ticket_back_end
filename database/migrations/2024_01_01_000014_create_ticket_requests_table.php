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
        Schema::create('ticket_requests', function (Blueprint $table) {
            $table->id();
            $table->string('titre');
            $table->text('description');
            $table->foreignId('projet_id')->constrained('projets')->onDelete('cascade');
            $table->foreignId('client_id')->constrained('clients')->onDelete('cascade');
            $table->foreignId('etape_id')->nullable()->constrained('projet_etapes')->onDelete('set null');
            $table->enum('priorite', ['basse', 'normale', 'haute', 'urgente'])->default('normale');
            $table->enum('statut', ['en_attente', 'approuve', 'rejete'])->default('en_attente');
            $table->text('raison_rejet')->nullable();
            $table->foreignId('validateur_id')->nullable()->constrained('users')->onDelete('set null'); // Admin qui valide/rejette
            $table->foreignId('ticket_id')->nullable()->constrained('tickets')->onDelete('set null'); // Ticket créé si approuvé
            $table->timestamp('validated_at')->nullable();
            $table->timestamps();
            
            $table->index(['client_id', 'statut']);
            $table->index(['projet_id', 'statut']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ticket_requests');
    }
};
