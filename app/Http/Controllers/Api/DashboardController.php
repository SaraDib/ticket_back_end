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
    public function stats(Request $request)
    {
        $user = $request->user();
        $now = now();
        $startOfCurrentMonth = $now->copy()->startOfMonth();
        $startOfLastMonth = $now->copy()->subMonth()->startOfMonth();
        $endOfLastMonth = $now->copy()->subMonth()->endOfMonth();

        $projetQuery = Projet::query();
        $ticketQuery = Ticket::query();
        $ticketResoluQuery = Ticket::where('statut', 'resolu');

        if ($user->role === 'client') {
            $client = $user->client;
            $clientId = $client ? $client->id : 0;
            $projetQuery->where('client_id', $clientId);
            $ticketQuery->whereIn('projet_id', function($q) use ($clientId) {
                $q->select('id')->from('projets')->where('client_id', $clientId);
            });
            $ticketResoluQuery->whereIn('projet_id', function($q) use ($clientId) {
                $q->select('id')->from('projets')->where('client_id', $clientId);
            });
        } elseif ($user->role === 'manager') {
            // Manager voit ses projets et les tickets de son équipe
            $projetQuery->where('manager_id', $user->id);
            if ($user->team_id) {
                $ticketQuery->whereHas('assignedTo', function($q) use ($user) {
                    $q->where('team_id', $user->team_id);
                });
                $ticketResoluQuery->whereHas('assignedTo', function($q) use ($user) {
                    $q->where('team_id', $user->team_id);
                });
            } else {
                $ticketQuery->whereRaw('1 = 0');
                $ticketResoluQuery->whereRaw('1 = 0');
            }
        } elseif ($user->role === 'collaborateur') {
            $projetQuery->where(function($q) use ($user) {
                $q->where('manager_id', $user->id)
                  ->orWhereHas('tickets', function($sub) use ($user) {
                      $sub->where('assigned_to', $user->id)
                          ->orWhere('created_by', $user->id);
                  });
            });
            $ticketQuery->where(function($q) use ($user) {
                $q->where('assigned_to', $user->id)
                  ->orWhere('created_by', $user->id);
            });
            $ticketResoluQuery->where(function($q) use ($user) {
                $q->where('assigned_to', $user->id)
                  ->orWhere('created_by', $user->id);
            });
        }

        // Projets
        $projetsCount = (clone $projetQuery)->count();
        $projetsLastMonth = (clone $projetQuery)->where('created_at', '<', $startOfCurrentMonth)->count();
        $projetsVariation = $this->calculateVariation($projetsCount, $projetsLastMonth);

        // Tickets Actifs
        $activeTicketsConstraint = function($q) {
            $q->whereNotIn('statut', ['resolu', 'ferme', 'rejete']);
        };
        $ticketsActifs = (clone $ticketQuery)->where($activeTicketsConstraint)->count();
        $ticketsActifsLastMonth = (clone $ticketQuery)->where($activeTicketsConstraint)
            ->where('created_at', '<', $startOfCurrentMonth)
            ->count();
        $ticketsActifsVariation = $this->calculateVariation($ticketsActifs, $ticketsActifsLastMonth);

        // Tickets Résolus
        $ticketsResolus = (clone $ticketResoluQuery)->count();
        $ticketsResolusLastMonth = (clone $ticketResoluQuery)
            ->where('updated_at', '<', $startOfCurrentMonth)
            ->count();
        $ticketsResolusVariation = $this->calculateVariation($ticketsResolus, $ticketsResolusLastMonth);

        // Équipe (Collaborateurs)
        $collabQuery = User::where('role', 'collaborateur');
        if ($user->role === 'manager' && $user->team_id) {
            $collabQuery->where('team_id', $user->team_id);
        }
        $collaborateursCount = (clone $collabQuery)->count();
        $collaborateursLastMonth = (clone $collabQuery)
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
            
            'collaborateurs_count' => $user->role === 'client' ? 0 : $collaborateursCount,
            'collaborateurs_variation' => $user->role === 'client' ? 0 : $collaborateursVariation,

            'recent_tickets' => (clone $ticketQuery)->with(['projet', 'assignedTo'])->latest()->take(5)->get(),
            'prochains_meetings' => Meeting::where('date_heure', '>=', now())
                ->where('statut', 'planifie')
                ->where(function($q) use ($user) {
                    if ($user->role === 'client') {
                        $client = $user->client;
                        $clientId = $client ? $client->id : 0;
                        $q->whereIn('projet_id', function($sub) use ($clientId) {
                             $sub->select('id')->from('projets')->where('client_id', $clientId);
                        })->orWhereHas('participants', function($sub) use ($user) {
                             $sub->where('users.id', $user->id);
                        });
                    } elseif ($user->role === 'manager') {
                        $q->where('organisateur_id', $user->id)
                          ->orWhereHas('participants', function($sub) use ($user) {
                              $sub->where('users.id', $user->id);
                          })
                          ->orWhereIn('projet_id', function($sub) use ($user) {
                              $sub->select('id')->from('projets')->where('manager_id', $user->id);
                          });
                    } elseif ($user->role === 'collaborateur') {
                        $q->where('organisateur_id', $user->id)
                          ->orWhereHas('participants', function($sub) use ($user) {
                              $sub->where('users.id', $user->id);
                          });
                    }
                })
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
    public function disponibiliteEquipe(Request $request)
    {
        $user = $request->user();
        $query = User::where('role', 'collaborateur');
        
        // Manager voit uniquement son équipe
        if ($user->role === 'manager' && $user->team_id) {
            $query->where('team_id', $user->team_id);
        }
        
        $collaborateurs = $query->withCount(['assignedTickets' => function ($query) {
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
    public function gantt(Request $request)
    {
        $user = $request->user();
        $query = Projet::with('etapes');

        if ($user->role === 'client') {
            $client = $user->client;
            $query->where('client_id', $client ? $client->id : 0);
        } elseif ($user->role === 'manager') {
            $query->where('manager_id', $user->id);
        } elseif ($user->role === 'collaborateur') {
            $query->where(function($q) use ($user) {
                $q->where('manager_id', $user->id)
                  ->orWhereHas('tickets', function($sub) use ($user) {
                      $sub->where('assigned_to', $user->id)
                          ->orWhere('created_by', $user->id);
                  });
            });
        }

        $projets = $query->get();
        
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
    public function projetsAvancement(Request $request)
    {
        $user = $request->user();
        $query = Projet::select('id', 'nom', 'avancement_realise', 'avancement_prevu', 'statut');

        if ($user->role === 'client') {
            $client = $user->client;
            $query->where('client_id', $client ? $client->id : 0);
        } elseif ($user->role === 'manager') {
            $query->where('manager_id', $user->id);
        } elseif ($user->role === 'collaborateur') {
            $query->where(function($q) use ($user) {
                $q->where('manager_id', $user->id)
                  ->orWhereHas('tickets', function($sub) use ($user) {
                      $sub->where('assigned_to', $user->id)
                          ->orWhere('created_by', $user->id);
                  });
            });
        }

        $projets = $query->get();
        return response()->json($projets);
    }

    /**
     * Upcoming deadlines (notifications)
     */
    public function echeances(Request $request)
    {
        $user = $request->user();
        $projetQuery = Projet::where('date_fin_prevue', '>=', now())
            ->where('date_fin_prevue', '<=', now()->addDays(7));

        $ticketQuery = Ticket::where('deadline', '>=', now())
            ->where('deadline', '<=', now()->addDays(7));

        if ($user->role === 'client') {
            $client = $user->client;
            $clientId = $client ? $client->id : 0;
            $projetQuery->where('client_id', $clientId);
            $ticketQuery->whereIn('projet_id', function($q) use ($clientId) {
                $q->select('id')->from('projets')->where('client_id', $clientId);
            });
        } elseif ($user->role === 'manager') {
            $projetQuery->where('manager_id', $user->id);
            if ($user->team_id) {
                $ticketQuery->whereHas('assignedTo', function($q) use ($user) {
                    $q->where('team_id', $user->team_id);
                });
            } else {
                $ticketQuery->whereRaw('1 = 0');
            }
        } elseif ($user->role === 'collaborateur') {
            $projetQuery->where(function($q) use ($user) {
                $q->where('manager_id', $user->id)
                  ->orWhereHas('tickets', function($sub) use ($user) {
                      $sub->where('assigned_to', $user->id)
                          ->orWhere('created_by', $user->id);
                  });
            });
            $ticketQuery->where(function($q) use ($user) {
                $q->where('assigned_to', $user->id)
                  ->orWhere('created_by', $user->id);
            });
        }

        return response()->json([
            'projets' => $projetQuery->get(),
            'tickets' => $ticketQuery->get(),
        ]);
    }
}
