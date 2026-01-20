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
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['admin', 'manager', 'collaborateur'])->default('collaborateur')->after('email');
            $table->string('telephone')->nullable()->after('email');
            $table->foreignId('team_id')->nullable()->constrained('teams')->onDelete('set null')->after('role');
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'telephone', 'team_id']);
            $table->dropSoftDeletes();
        });
    }
};
