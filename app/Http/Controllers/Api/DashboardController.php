<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Projet;
use App\Models\Ticket;
use App\Models\User;
use App\Models\Meeting;
use Illuminate\Http\Request;
use Carbon\Carbon;

class DashboardController extends Controller
{
    /**
     * Get global stats
     */
    public function stats()
    {
        $now = now();
        $startOfCurrentMonth = $now->copy()->startOfMonth();
        $startOfLastMonth = $now->copy()->subMonth()->startOfMonth();
        $endOfLastMonth = $now->copy()->subMonth()->endOfMonth();

        // Projets
        $projetsCount = Projet::count();
        $projetsLastMonth = Projet::where('created_at', '<', $startOfCurrentMonth)->count();
        $projetsVariation = $this->calculateVariation($projetsCount, $projetsLastMonth);

        // Tickets Actifs
        $ticketsActifs = Ticket::whereNotIn('statut', ['resolu', 'ferme', 'rejete'])->count();
        $ticketsActifsLastMonth = Ticket::whereNotIn('statut', ['resolu', 'ferme', 'rejete'])
            ->where('created_at', '<', $startOfCurrentMonth)
            ->count();
        $ticketsActifsVariation = $this->calculateVariation($ticketsActifs, $ticketsActifsLastMonth);

        // Tickets Résolus
        $ticketsResolus = Ticket::where('statut', 'resolu')->count();
        $ticketsResolusLastMonth = Ticket::where('statut', 'resolu')
            ->where('updated_at', '<', $startOfCurrentMonth) // On utilise updated_at car c'est quand il est passé en résolu
            ->count();
        $ticketsResolusVariation = $this->calculateVariation($ticketsResolus, $ticketsResolusLastMonth);

        // Équipe (Collaborateurs)
        $collaborateursCount = User::where('role', 'collaborateur')->count();
        $collaborateursLastMonth = User::where('role', 'collaborateur')
            ->where('created_at', '<', $startOfCurrentMonth)
            ->count();
        $collaborateursVariation = $this->calculateVariation($collaborateursCount, $collaborateursLastMonth);

        return response()->json([
            'projets_count' => $projetsCount,
            'projets_variation' => $projetsVariation,
            
            'tickets_actifs' => $ticketsActifs,
            'tickets_actifs_variation' => $ticketsActifsVariation,
            
            'tickets_resolus' => $ticketsResolus,
            'tickets_resolus_variation' => $ticketsResolusVariation,
            
            'collaborateurs_count' => $collaborateursCount,
            'collaborateurs_variation' => $collaborateursVariation,

            'recent_tickets' => Ticket::with(['projet', 'assignedTo'])->latest()->take(5)->get(),
            'prochains_meetings' => Meeting::where('date_heure', '>=', now())
                ->where('statut', 'planifie')
                ->latest()
                ->take(5)
                ->get(),
        ]);
    }

    private function calculateVariation($current, $previous)
    {
        if ($previous == 0) {
            return $current > 0 ? 100 : 0;
        }
        return round((($current - $previous) / $previous) * 100, 1);
    }

    /**
     * Team availability (based on tickets)
     */
    public function disponibiliteEquipe()
    {
        $collaborateurs = User::where('role', 'collaborateur')
            ->withCount(['assignedTickets' => function ($query) {
                $query->whereNotIn('statut', ['resolu', 'ferme', 'rejete']);
            }])
            ->get();

        return response()->json($collaborateurs->map(function ($user) {
            return [
                'id' => $user->id,
                'name' => $user->name,
                'tickets_en_cours' => $user->assigned_tickets_count,
                'disponibilite' => $user->assigned_tickets_count > 3 ? 'occupe' : ($user->assigned_tickets_count > 0 ? 'partiel' : 'libre'),
            ];
        }));
    }

    /**
     * Gantt chart data
     */
    public function gantt()
    {
        $projets = Projet::with('etapes')->get();
        
        $data = $projets->map(function ($projet) {
            return [
                'id' => 'p' . $projet->id,
                'name' => $projet->nom,
                'start' => $projet->date_debut ? $projet->date_debut->format('Y-m-d') : null,
                'end' => $projet->date_fin_prevue ? $projet->date_fin_prevue->format('Y-m-d') : null,
                'progress' => $projet->avancement_realise,
                'type' => 'project',
                'dependencies' => [],
            ];
        });

        return response()->json($data);
    }

    /**
     * Project progress
     */
    public function projetsAvancement()
    {
        $projets = Projet::select('id', 'nom', 'avancement_realise', 'avancement_prevu', 'statut')
            ->get();
        return response()->json($projets);
    }

    /**
     * Upcoming deadlines (notifications)
     */
    public function echeances()
    {
        $projets = Projet::where('date_fin_prevue', '>=', now())
            ->where('date_fin_prevue', '<=', now()->addDays(7))
            ->get();

        $tickets = Ticket::where('deadline', '>=', now())
            ->where('deadline', '<=', now()->addDays(7))
            ->get();

        return response()->json([
            'projets' => $projets,
            'tickets' => $tickets,
        ]);
    }
}
