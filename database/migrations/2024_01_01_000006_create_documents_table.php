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
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->string('nom');
            $table->enum('type', [
                'cahier_charges',
                'specification_fonctionnelle',
                'contrat_client',
                'demande_stage',
                'contrat_collaborateur',
                'attestation_travail',
                'attestation_stage',
                'cv',
                'contrat_confidentialite',
                'autre'
            ]);
            $table->string('fichier_path');
            $table->string('fichier_nom_original');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('taille')->nullable(); // en bytes
            
            // Polymorphic relation (peut être lié à un projet ou un user)
            $table->morphs('documentable');
            
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
