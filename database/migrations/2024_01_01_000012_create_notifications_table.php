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
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('type'); // EmailNotification, WhatsAppNotification, etc.
            $table->string('titre')->nullable();
            $table->text('message');
            $table->json('data')->nullable(); // Données supplémentaires
            $table->boolean('lu')->default(false);
            $table->boolean('envoye')->default(false);
            $table->enum('canal', ['email', 'whatsapp', 'system'])->default('system');
            $table->timestamp('lu_at')->nullable();
            $table->timestamp('envoye_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
