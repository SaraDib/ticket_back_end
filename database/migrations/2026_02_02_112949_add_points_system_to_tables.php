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
            $table->integer('reward_points')->default(0)->after('heures_reelles');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->integer('points')->default(0)->after('role');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn('reward_points');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('points');
        });
    }
};
