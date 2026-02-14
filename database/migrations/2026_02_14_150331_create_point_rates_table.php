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
        Schema::create('point_rates', function (Blueprint $table) {
            $table->id();
            $table->integer('level')->unique();
            $table->decimal('rate', 10, 2)->default(0.00); // DH par point
            $table->timestamps();
        });

        // Insert some default rates for levels 1 to 10
        for ($i = 1; $i <= 10; $i++) {
            DB::table('point_rates')->insert([
                'level' => $i,
                'rate' => $i * 0.10, // Exemple: 0.10 DH, 0.20 DH, etc.
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('point_rates');
    }
};
