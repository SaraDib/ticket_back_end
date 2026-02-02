<?php

namespace Database\Seeders;

use App\Models\PointHistory;
use App\Models\Projet;
use App\Models\ProjetEtape;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class PointsSystemSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Créer ou récupérer un manager
        $manager = User::where('email', 'manager@test.com')->first() ?: User::create([
            'name' => 'Manager Test',
            'email' => 'manager@test.com',
            'password' => Hash::make('password'),
            'role' => 'manager',
            'telephone' => '0611223344',
        ]);

        // 2. Créer ou récupérer un collaborateur
        $collaborateur = User::where('email', 'collab@test.com')->first() ?: User::create([
            'name' => 'Collaborateur Test',
            'email' => 'collab@test.com',
            'password' => Hash::make('password'),
            'role' => 'collaborateur',
            'telephone' => '0655667788',
        ]);

        // 3. Créer un projet de test
        $projet = Projet::create([
            'nom' => 'Projet Test Points',
            'type' => 'interne',
            'description' => 'Un projet pour tester le système de points de récompense.',
            'manager_id' => $manager->id,
            'statut' => 'en_cours',
            'date_debut' => now(),
        ]);

        // 4. Créer une étape
        $etape = ProjetEtape::create([
            'projet_id' => $projet->id,
            'nom' => 'Phase de Test',
            'ordre' => 1,
            'statut' => 'en_cours',
        ]);

        // 5. Créer des tickets avec des points
        $tickets = [
            [
                'titre' => 'Ticket terminé 1',
                'description' => 'Premier ticket pour tester le cumul de points.',
                'reward_points' => 50,
                'statut' => 'resolu',
            ],
            [
                'titre' => 'Ticket terminé 2',
                'description' => 'Deuxième ticket pour tester le cumul de points.',
                'reward_points' => 100,
                'statut' => 'resolu',
            ],
            [
                'titre' => 'Ticket en cours',
                'description' => 'Un ticket pas encore terminé.',
                'reward_points' => 75,
                'statut' => 'en_cours',
            ],
        ];

        foreach ($tickets as $tData) {
            $ticket = Ticket::create(array_merge($tData, [
                'projet_id' => $projet->id,
                'etape_id' => $etape->id,
                'created_by' => $manager->id,
                'assigned_to' => $collaborateur->id,
                'priorite' => 'normale',
                'opened_at' => now()->subDays(1),
            ]));

            // Si le ticket est résolu, on ajoute manuellement car le seeder ne déclenche pas forcément static::updating 
            // de la même manière selon l'environnement, ou on veut être sûr des datas initiales.
            if ($ticket->statut === 'resolu') {
                $collaborateur->increment('points', $ticket->reward_points);
                
                PointHistory::create([
                    'user_id' => $collaborateur->id,
                    'ticket_id' => $ticket->id,
                    'points' => $ticket->reward_points,
                    'description' => "Récompense pour la résolution du ticket: {$ticket->titre}",
                    'created_at' => now()->subHours(rand(1, 24)),
                ]);
            }
        }

        $this->command->info('Seeder PointsSystemSeeder exécuté avec succès !');
        $this->command->info('Utilisateur collab@test.com a maintenant ' . $collaborateur->fresh()->points . ' points.');
    }
}
