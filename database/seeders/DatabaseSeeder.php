<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\Meeting;
use App\Models\Projet;
use App\Models\ProjetEtape;
use App\Models\Team;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Créer des teams
        $teamDev = Team::create([
            'nom' => 'Équipe Développement',
            'description' => 'Équipe de développement web et mobile',
        ]);

        $teamDesign = Team::create([
            'nom' => 'Équipe Design',
            'description' => 'Équipe de design UI/UX',
        ]);

        // Créer un admin
        $admin = User::create([
            'name' => 'Administrateur',
            'email' => 'admin@ticketmanagement.com',
            'password' => Hash::make('Admin@2024'),
            'role' => 'admin',
            'telephone' => '+212 6 12 34 56 78',
        ]);

        // Créer des managers
        $manager1 = User::create([
            'name' => 'Mohamed El Amrani',
            'email' => 'mohamed@ticketmanagement.com',
            'password' => Hash::make('password'),
            'role' => 'manager',
            'telephone' => '+212 6 11 22 33 44',
            'team_id' => $teamDev->id,
        ]);

        $manager2 = User::create([
            'name' => 'Fatima Zahra',
            'email' => 'fatima@ticketmanagement.com',
            'password' => Hash::make('password'),
            'role' => 'manager',
            'telephone' => '+212 6 55 66 77 88',
            'team_id' => $teamDesign->id,
        ]);

        // Créer des collaborateurs
        $collaborateurs = [
            ['name' => 'Ahmed Benjelloun', 'email' => 'ahmed@ticketmanagement.com', 'team_id' => $teamDev->id],
            ['name' => 'Sanaa Alaoui', 'email' => 'sanaa@ticketmanagement.com', 'team_id' => $teamDev->id],
            ['name' => 'Youssef Lahlou', 'email' => 'youssef@ticketmanagement.com', 'team_id' => $teamDesign->id],
            ['name' => 'Khadija Benali', 'email' => 'khadija@ticketmanagement.com', 'team_id' => $teamDesign->id],
        ];

        $collabModels = [];
        foreach ($collaborateurs as $collab) {
            $collabModels[] = User::create([
                'name' => $collab['name'],
                'email' => $collab['email'],
                'password' => Hash::make('password'),
                'role' => 'collaborateur',
                'telephone' => '+212 6 ' . rand(10000000, 99999999),
                'team_id' => $collab['team_id'],
            ]);
        }

        // Créer des clients
        $client1 = Client::create([
            'nom' => 'Maroc Telecom SA',
            'ice' => '001234567000001',
            'identifiant_fiscal' => '12345678',
            'telephone' => '+212 5 22 11 22 33',
            'email' => 'contact@maroctelecom.ma',
            'adresse' => 'Avenue Annakhil, Rabat, Maroc',
        ]);

        $client2 = Client::create([
            'nom' => 'Banque Populaire',
            'ice' => '001987654000002',
            'identifiant_fiscal' => '87654321',
            'telephone' => '+212 5 37 55 66 77',
            'email' => 'info@bp.ma',
            'adresse' => '101 Boulevard Zerktouni, Casablanca, Maroc',
        ]);

        // Créer un compte utilisateur pour le client Maroc Telecom
        $clientUser = User::create([
            'name' => 'Client Maroc Telecom',
            'email' => 'client@maroctelecom.ma',
            'password' => Hash::make('password'),
            'role' => 'client',
            'telephone' => '+212 5 22 11 22 33',
        ]);

        // Lier l'utilisateur au client
        $client1->update(['user_id' => $clientUser->id]);

        // Créer des projets internes
        $projetRakops = Projet::create([
            'nom' => 'Rakops - Plateforme Interne',
            'type' => 'interne',
            'description' => 'Développement de notre plateforme de gestion interne',
            'manager_id' => $manager1->id,
            'avancement_realise' => 65,
            'avancement_prevu' => 70,
            'date_debut' => now()->subMonths(6),
            'date_fin_prevue' => now()->addMonths(3),
            'statut' => 'en_cours',
            'github_links' => 'https://github.com/rakops/internal-platform',
        ]);

        // Créer des projets externes
        $projetClient1 = Projet::create([
            'nom' => 'Application Mobile Maroc Telecom',
            'type' => 'externe',
            'description' => 'Développement d\'une application mobile pour la gestion des comptes clients',
            'client_id' => $client1->id,
            'manager_id' => $manager1->id,
            'avancement_realise' => 40,
            'avancement_prevu' => 45,
            'date_debut' => now()->subMonths(3),
            'date_fin_prevue' => now()->addMonths(6),
            'statut' => 'en_cours',
            'github_links' => 'https://github.com/rakops/mt-mobile-app',
        ]);

        $projetClient2 = Projet::create([
            'nom' => 'Portail Web Banque Populaire',
            'type' => 'externe',
            'description' => 'Refonte du portail web de la banque',
            'client_id' => $client2->id,
            'manager_id' => $manager2->id,
            'avancement_realise' => 20,
            'avancement_prevu' => 25,
            'date_debut' => now()->subMonths(1),
            'date_fin_prevue' => now()->addMonths(8),
            'statut' => 'en_cours',
            'github_links' => 'https://github.com/rakops/bp-web-portal',
        ]);

        // Créer des étapes pour les projets
        ProjetEtape::create([
            'projet_id' => $projetClient1->id,
            'nom' => 'Analyse et spécifications',
            'description' => 'Recueil des besoins et spécifications techniques',
            'ordre' => 1,
            'statut' => 'termine',
            'date_debut' => now()->subMonths(3),
            'date_fin' => now()->subMonths(2)->subWeeks(2),
        ]);

        ProjetEtape::create([
            'projet_id' => $projetClient1->id,
            'nom' => 'Design UI/UX',
            'description' => 'Conception des maquettes et prototypes',
            'ordre' => 2,
            'statut' => 'termine',
            'date_debut' => now()->subMonths(2)->subWeeks(2),
            'date_fin' => now()->subMonths(2),
        ]);

        ProjetEtape::create([
            'projet_id' => $projetClient1->id,
            'nom' => 'Développement Backend',
            'description' => 'Développement de l\'API REST',
            'ordre' => 3,
            'statut' => 'en_cours',
            'date_debut' => now()->subMonths(2),
            'date_fin' => null,
        ]);

        ProjetEtape::create([
            'projet_id' => $projetClient1->id,
            'nom' => 'Développement Frontend',
            'description' => 'Développement de l\'application mobile',
            'ordre' => 4,
            'statut' => 'en_cours',
            'date_debut' => now()->subMonth(),
            'date_fin' => null,
        ]);

        // Créer des tickets
        Ticket::create([
            'titre' => 'Implémenter authentification JWT',
            'description' => 'Mettre en place l\'authentification par token JWT pour l\'API mobile',
            'projet_id' => $projetClient1->id,
            'created_by' => $manager1->id,
            'assigned_to' => $collabModels[0]->id,
            'statut' => 'en_cours',
            'priorite' => 'haute',
            'heures_estimees' => 16,
            'heures_reelles' => 10,
            'deadline' => now()->addDays(3),
        ]);

        Ticket::create([
            'titre' => 'Design écran de connexion',
            'description' => 'Créer la maquette de l\'écran de connexion selon la charte graphique',
            'projet_id' => $projetClient1->id,
            'created_by' => $manager1->id,
            'assigned_to' => $collabModels[2]->id,
            'statut' => 'ferme',
            'priorite' => 'normale',
            'heures_estimees' => 8,
            'heures_reelles' => 6,
            'deadline' => now()->subDays(5),
        ]);

        Ticket::create([
            'titre' => 'Correction bug paiement',
            'description' => 'Corriger le bug lors du paiement par carte bancaire',
            'projet_id' => $projetClient2->id,
            'created_by' => $manager2->id,
            'assigned_to' => $collabModels[1]->id,
            'statut' => 'en_cours',
            'priorite' => 'urgente',
            'heures_estimees' => 4,
            'heures_reelles' => 0,
            'deadline' => now()->addDay(),
        ]);

        // Créer un meeting
        $meeting = Meeting::create([
            'titre' => 'Réunion de sprint planning',
            'description' => 'Planification du prochain sprint de développement',
            'date_heure' => now()->addDays(2)->setTime(10, 0),
            'duree_minutes' => 90,
            'lieu' => 'Salle de réunion A',
            'lien_visio' => 'https://meet.google.com/abc-defg-hij',
            'organisateur_id' => $manager1->id,
            'projet_id' => $projetClient1->id,
            'statut' => 'planifie',
        ]);

        // Ajouter des participants au meeting
        $meeting->participants()->attach([
            $collabModels[0]->id => ['statut_presence' => 'confirme'],
            $collabModels[1]->id => ['statut_presence' => 'invite'],
            $manager1->id => ['statut_presence' => 'confirme'],
        ]);

        $this->command->info('✅ Base de données peuplée avec succès !');
        $this->command->info('');
        $this->command->info('🔐 Identifiants de connexion :');
        $this->command->info('   Admin    : admin@ticketmanagement.com / Admin@2024');
        $this->command->info('   Manager  : mohamed@ticketmanagement.com / password');
        $this->command->info('   Collab   : ahmed@ticketmanagement.com / password');
    }
}
