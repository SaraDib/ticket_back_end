<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Projet;
use App\Models\ProjetEtape;
use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use App\Services\NotificationService;
use App\Models\User;

class ProjetController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $query = Projet::with(['client', 'manager', 'teams']);

        if ($user->role === 'client') {
            $client = $user->client;
            if ($client) {
                $query->where('client_id', $client->id);
            } else {
                // Si l'utilisateur est client mais n'a pas de fiche client liée
                return response()->json([]);
            }
        } elseif ($user->role === 'manager') {
            // Un manager voit les projets qu'il manage directement OU les projets de ses équipes
            $query->where(function($q) use ($user) {
                $q->where('manager_id', $user->id)
                  ->orWhereHas('teams', function($t) use ($user) {
                      $t->whereIn('teams.id', $user->teams->pluck('id'));
                  });
            });
        } elseif ($user->role === 'collaborateur') {
            $query->where(function($q) use ($user) {
                // Projets dont il est manager
                $q->where('manager_id', $user->id)
                // OU projets de ses équipes
                ->orWhereHas('teams', function($t) use ($user) {
                    $t->whereIn('teams.id', $user->teams->pluck('id'));
                })
                // OU projets où il a au moins un ticket (assigné ou crée)
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
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'nom' => 'required|string|max:255',
            'type' => 'required|in:interne,externe',
            'description' => 'nullable|string',
            'client_id' => 'nullable|exists:clients,id',
            'manager_id' => 'nullable|exists:users,id',
            'github_links' => 'nullable|string',
            'avancement_prevu' => 'nullable|integer|min:0|max:100',
            'date_debut' => 'nullable|date',
            'date_fin_prevue' => 'nullable|date',
            'statut' => 'nullable|in:en_attente,en_cours,termine,suspendu',
            'team_ids' => 'nullable|array',
            'team_ids.*' => 'exists:teams,id',
        ]);

        $projet = Projet::create($validated);

        // Créer automatiquement une étape "Général" pour tous les tickets non spécifiques
        $projet->etapes()->create([
            'nom' => 'Général',
            'description' => 'Étape générale pour les tickets non spécifiques',
            'ordre' => 0,
            'statut' => 'en_cours',
        ]);

        if ($request->has('team_ids')) {
            $projet->teams()->sync($request->team_ids);
            
            // Notifier les membres des équipes
            $teams = \App\Models\Team::with('members')->whereIn('id', $request->team_ids)->get();
            $managerName = $projet->manager_id ? User::find($projet->manager_id)->name : 'Non défini';
            
            foreach ($teams as $team) {
                $msg = "Votre équipe '{$team->nom}' a été affectée au nouveau projet : {$projet->nom}.\n";
                $msg .= "Manager responsable : {$managerName}";
                
                foreach ($team->members as $member) {
                    if ($member->id !== auth()->id()) {
                        NotificationService::send($member, 'Nouveau projet pour votre équipe', $msg, ['system', 'whatsapp']);
                    }
                }
            }
        }

        if ($projet->manager_id) {
            $manager = User::find($projet->manager_id);
            if ($manager) {
                NotificationService::send($manager, 'Nouveau projet assigné', "Vous avez été nommé manager du projet : {$projet->nom}", ['system', 'email', 'whatsapp']);
            }
        }

        return response()->json($projet, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, string $id)
    {
        $user = $request->user();
        $projet = Projet::with([
            'client', 
            'manager', 
            'etapes', 
            'documents', 
            'tickets.assignedTo:id,name,email',
            'tickets.createdBy:id,name,email',
            'tickets.etape:id,nom,ordre',
            'meetings.participants:id,name,email',
            'meetings.organisateur:id,name,email',
            'teams'
        ])->findOrFail($id);

        // Sécurité pour les clients
        if ($user->role === 'client') {
            $client = $user->client;
            if (!$client || $projet->client_id !== $client->id) {
                return response()->json(['message' => 'Accès interdit'], 403);
            }
        } elseif ($user->role === 'manager') {
            // Un manager peut accéder uniquement s'il est le manager du projet OU s'il fait partie d'une équipe assignée au projet
            $isManager = $projet->manager_id === $user->id;
            $inTeam = $projet->teams()->whereIn('teams.id', $user->teams->pluck('id'))->exists();
            
            if (!$isManager && !$inTeam) {
                return response()->json(['message' => 'Accès interdit'], 403);
            }
        } elseif ($user->role === 'collaborateur') {
            // Un collaborateur peut accéder s'il est manager, s'il fait partie d'une équipe assignée au projet, ou s'il a au moins un ticket
            $isManager = $projet->manager_id === $user->id;
            $inTeam = $projet->teams()->whereIn('teams.id', $user->teams->pluck('id'))->exists();
            $hasTickets = $projet->tickets()->where(function($q) use ($user) {
                $q->where('assigned_to', $user->id)
                  ->orWhere('created_by', $user->id);
            })->exists();
            
            if (!$isManager && !$inTeam && !$hasTickets) {
                return response()->json(['message' => 'Accès interdit'], 403);
            }
        }

        return response()->json($projet);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $projet = Projet::findOrFail($id);
        
        $validated = $request->validate([
            'nom' => 'sometimes|string|max:255',
            'type' => 'sometimes|in:interne,externe',
            'description' => 'nullable|string',
            'client_id' => 'nullable|exists:clients,id',
            'manager_id' => 'nullable|exists:users,id',
            'github_links' => 'nullable|string',
            'avancement_realise' => 'nullable|integer|min:0|max:100',
            'avancement_prevu' => 'nullable|integer|min:0|max:100',
            'date_debut' => 'nullable|date',
            'date_fin_prevue' => 'nullable|date',
            'date_fin_reelle' => 'nullable|date',
            'statut' => 'nullable|in:en_attente,en_cours,termine,suspendu',
            'team_ids' => 'nullable|array',
            'team_ids.*' => 'exists:teams,id',
        ]);

        $oldManagerId = $projet->manager_id;
        $projet->update($validated);

        if ($request->has('team_ids')) {
            $oldTeamIds = DB::table('projet_team')->where('projet_id', $projet->id)->pluck('team_id')->toArray();
            $projet->teams()->sync($request->team_ids);
            $newTeamIds = array_diff($request->team_ids, $oldTeamIds);
            
            if (!empty($newTeamIds)) {
                $teams = \App\Models\Team::with('members')->whereIn('id', $newTeamIds)->get();
                $managerName = $projet->manager ? $projet->manager->name : 'Non défini';
                
                foreach ($teams as $team) {
                    $msg = "Votre équipe '{$team->nom}' a été affectée au projet : {$projet->nom}.\n";
                    $msg .= "Manager responsable : {$managerName}";
                    
                    foreach ($team->members as $member) {
                        if ($member->id !== auth()->id()) {
                            NotificationService::send($member, 'Nouveau projet pour votre équipe', $msg, ['system', 'whatsapp']);
                        }
                    }
                }
            }
        }

        if ($projet->manager_id && $projet->manager_id != $oldManagerId) {
            $manager = User::find($projet->manager_id);
            if ($manager) {
                NotificationService::send($manager, 'Projet assigné', "Vous avez été nommé manager du projet : {$projet->nom}", ['system', 'email', 'whatsapp']);
            }
        }

        return response()->json($projet);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $projet = Projet::findOrFail($id);
        $projet->delete();
        return response()->json(null, 204);
    }

    /**
     * Get project steps
     */
    public function etapes(Projet $projet)
    {
        return response()->json($projet->etapes);
    }

    /**
     * Add step to project
     */
    public function ajouterEtape(Request $request, Projet $projet)
    {
        $validated = $request->validate([
            'nom' => 'required|string|max:255',
            'description' => 'nullable|string',
            'ordre' => 'required|integer',
            'statut' => 'nullable|in:non_commence,en_cours,termine',
            'date_debut' => 'nullable|date',
            'date_fin' => 'nullable|date',
        ]);

        $etape = $projet->etapes()->create($validated);
        return response()->json($etape, 201);
    }

    /**
     * Update project step
     */
    public function modifierEtape(Request $request, Projet $projet, ProjetEtape $etape)
    {
        $validated = $request->validate([
            'nom' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'ordre' => 'sometimes|integer',
            'statut' => 'sometimes|in:non_commence,en_cours,termine',
            'date_debut' => 'nullable|date',
            'date_fin' => 'nullable|date',
        ]);

        $etape->update($validated);
        return response()->json($etape);
    }

    /**
     * Delete project step
     */
    public function supprimerEtape(Projet $projet, ProjetEtape $etape)
    {
        $etape->delete();
        return response()->json(null, 204);
    }

    /**
     * Get project documents
     */
    public function documents(Request $request, Projet $projet)
    {
        $user = $request->user();
        if ($user->role === 'client') {
            $client = $user->client;
            if (!$client || $projet->client_id !== $client->id) {
                return response()->json(['message' => 'Accès interdit'], 403);
            }
        } elseif ($user->role === 'manager') {
            if ($projet->manager_id !== $user->id) {
                return response()->json(['message' => 'Accès interdit'], 403);
            }
        } elseif ($user->role === 'collaborateur') {
            // Même logique que show()
            $isManager = $projet->manager_id === $user->id;
            $hasTickets = $projet->tickets()->where(function($q) use ($user) {
                $q->where('assigned_to', $user->id)->orWhere('created_by', $user->id);
            })->exists();

            if (!$isManager && !$hasTickets) {
                return response()->json(['message' => 'Accès interdit'], 403);
            }
        }
        return response()->json($projet->documents);
    }

    /**
     * Upload document for project
     */
    public function uploadDocument(Request $request, Projet $projet)
    {
        $user = $request->user();
        if ($user->role === 'client') {
            $client = $user->client;
            if (!$client || $projet->client_id !== $client->id) {
                return response()->json(['message' => 'Accès interdit'], 403);
            }
        } elseif ($user->role === 'manager') {
            if ($projet->manager_id !== $user->id) {
                return response()->json(['message' => 'Accès interdit'], 403);
            }
        } elseif ($user->role === 'collaborateur') {
            $isManager = $projet->manager_id === $user->id;
            $hasTickets = $projet->tickets()->where(function($q) use ($user) {
                $q->where('assigned_to', $user->id)->orWhere('created_by', $user->id);
            })->exists();

            if (!$isManager && !$hasTickets) {
                return response()->json(['message' => 'Accès interdit'], 403);
            }
        }

        $request->validate([
            'fichier' => 'required|file|max:10240',
            'type' => 'required|string',
            'nom' => 'required|string|max:255',
        ]);

        $file = $request->file('fichier');
        $path = $file->store('documents/projets/' . $projet->id, 'public');

        $document = $projet->documents()->create([
            'nom' => $request->nom,
            'type' => $request->type,
            'fichier_path' => $path,
            'fichier_nom_original' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'taille' => $file->getSize(),
            'uploaded_by' => auth()->id(),
        ]);

        return response()->json($document, 201);
    }

    /**
     * Delete document for project
     */
    public function supprimerDocument(Request $request, Projet $projet, Document $document)
    {
        $user = $request->user();
        if ($user->role === 'client') {
            $client = $user->client;
            if (!$client || $projet->client_id !== $client->id) {
                return response()->json(['message' => 'Accès interdit'], 403);
            }
        } elseif ($user->role === 'manager') {
            if ($projet->manager_id !== $user->id) {
                return response()->json(['message' => 'Accès interdit'], 403);
            }
        } elseif ($user->role === 'collaborateur') {
            $isManager = $projet->manager_id === $user->id;
            $hasTickets = $projet->tickets()->where(function($q) use ($user) {
                $q->where('assigned_to', $user->id)->orWhere('created_by', $user->id);
            })->exists();

            if (!$isManager && !$hasTickets) {
                return response()->json(['message' => 'Accès interdit'], 403);
            }
        }

        // Supprimer le fichier physiquement
        Storage::delete($document->fichier_path);
        
        $document->delete();
        return response()->json(null, 204);
    }

    /**
     * Get project tickets
     */
    public function tickets(Projet $projet)
    {
        return response()->json($projet->tickets()->with(['assignedTo', 'createdBy'])->get());
    }
}
