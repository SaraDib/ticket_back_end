<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PointSettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        \App\Models\PointSetting::updateOrCreate(
            ['key' => 'internal_coeff'],
            [
                'value' => 1.5,
                'label' => 'Coefficient Projet Interne',
                'description' => 'Valeur monétaire (en DH) pour 1 point sur un projet interne'
            ]
        );

        \App\Models\PointSetting::updateOrCreate(
            ['key' => 'external_coeff'],
            [
                'value' => 1.0,
                'label' => 'Coefficient Projet Externe',
                'description' => 'Valeur monétaire (en DH) pour 1 point sur un projet externe'
            ]
        );
    }
}
