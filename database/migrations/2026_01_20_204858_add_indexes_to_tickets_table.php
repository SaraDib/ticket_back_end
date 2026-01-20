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
        Schema::table('tickets', function (Blueprint $table) {
            $table->index('projet_id');
            $table->index('assigned_to');
            $table->index('created_by');
            $table->index('statut');
            $table->index('priorite');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropIndex(['projet_id']);
            $table->dropIndex(['assigned_to']);
            $table->dropIndex(['created_by']);
            $table->dropIndex(['statut']);
            $table->dropIndex(['priorite']);
            $table->dropIndex(['created_at']);
        });
    }
};
