<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\TicketCommentaire;
use App\Models\TicketAttachment;
use App\Models\Notification;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class TicketController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        set_time_limit(120); // Temporary fix for timeout
        
        $perPage = $request->get('per_page', 20); // Default 20 items per page
        
        // Optimize relationship loading with only necessary fields
        $query = Ticket::with([
            'projet:id,nom,type,statut,client_id',
            'etape:id,nom,statut',
            'createdBy:id,name,email',
            'assignedTo:id,name,email'
        ]);
        
        if ($request->has('projet_id')) {
            $query->where('projet_id', $request->projet_id);
        }
        
        if ($request->has('assigned_to')) {
            $query->where('assigned_to', $request->assigned_to);
        }

        if ($request->has('statut')) {
            $query->where('statut', $request->statut);
        }

        // Restriction pour les clients : uniquement les tickets de leurs projets
        // Restriction pour les managers : uniquement les tickets assignés aux membres de leur équipe
        // Restriction pour les collaborateurs : uniquement leurs tickets assignés ou créés
        $user = $request->user();
        $role = trim(strtolower($user->role));

        if ($role === 'client') {
            $client = $user->client;
            $clientId = $client ? $client->id : 0;
            $query->whereIn('projet_id', function($q) use ($clientId) {
                $q->select('id')->from('projets')->where('client_id', $clientId);
            });
        } elseif ($role === 'manager') {
            // Un manager voit les tickets des membres de son équipe
            // OU les tickets qu'il a créés ou qui lui sont assignés directement
            $query->where(function($q) use ($user) {
                $q->where('assigned_to', $user->id)
                  ->orWhere('created_by', $user->id);
                
                if ($user->team_id) {
                    $q->orWhereHas('assignedTo', function($sub) use ($user) {
                        $sub->where('team_id', $user->team_id);
                    });
                }
            });
        } elseif ($role === 'collaborateur') {
            // Un collaborateur ne voit ABSOLUMENT QUE ses tickets (assignés ou créés)
            $query->where(function($q) use ($user) {
                $q->where('assigned_to', $user->id)
                  ->orWhere('created_by', $user->id);
            });
        } elseif ($role !== 'admin') {
            // Par défaut, si ce n'est pas un admin (et que c'est un rôle inconnu), 
            // on ne montre que ses propres tickets par sécurité.
            $query->where(function($q) use ($user) {
                $q->where('assigned_to', $user->id)
                  ->orWhere('created_by', $user->id);
            });
        }

        // Add ordering
        $query->orderBy('created_at', 'desc');

        // Return paginated results
        return response()->json($query->paginate($perPage));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'titre' => 'required|string|max:255',
            'description' => 'required|string',
            'projet_id' => 'required|exists:projets,id',
            'etape_id' => 'required|exists:projet_etapes,id',
            'assigned_to' => 'nullable|exists:users,id',
            'priorite' => 'required|in:basse,normale,haute,urgente',
            'heures_estimees' => 'nullable|numeric',
            'reward_points' => 'nullable|integer|min:0',
            'deadline' => 'nullable|date',
        ]);

        $validated['created_by'] = auth()->id();
        $validated['statut'] = 'en_attente';

        $ticket = Ticket::create($validated);
        $ticket->load('projet');

        if ($ticket->assigned_to) {
            $message = "Le ticket #{$ticket->id}: {$ticket->titre} vous a été assigné.\n";
            $message .= "Projet: {$ticket->projet->nom} (Type: {$ticket->projet->type})";
            NotificationService::send($ticket->assignedTo, 'Nouveau ticket assigné', $message, ['system', 'email', 'whatsapp']);
        }

        return response()->json($ticket, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, string $id)
    {
        $user = $request->user();
        $ticket = Ticket::with([
            'projet', 
            'etape', 
            'createdBy', 
            'assignedTo', 
            'commentaires.user', 
            'commentaires.attachments.user',
            'attachments.user'
        ])->findOrFail($id);
        
        // Sécurité pour les clients
        if ($user->role === 'client') {
            $client = $user->client;
            if (!$client || $ticket->projet->client_id !== $client->id) {
                return response()->json(['message' => 'Accès interdit'], 403);
            }
        }

        return response()->json($ticket);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $ticket = Ticket::findOrFail($id);
        
        $validated = $request->validate([
            'titre' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'projet_id' => 'sometimes|exists:projets,id',
            'etape_id' => 'nullable|exists:projet_etapes,id',
            'assigned_to' => 'nullable|exists:users,id',
            'statut' => 'sometimes|in:en_attente,en_cours,reopen,resolu,ferme',
            'priorite' => 'sometimes|in:basse,normale,haute,urgente',
            'heures_estimees' => 'nullable|numeric|min:0',
            'heures_reelles' => 'nullable|numeric|min:0',
            'reward_points' => 'nullable|integer|min:0',
            'deadline' => 'nullable|date',
        ]);

        $oldStatus = $ticket->getOriginal('statut');
        $oldAssignedTo = $ticket->getOriginal('assigned_to');

        // Validation spécifique pour les collaborateurs : 
        // Un collaborateur ne peut pas repasser un ticket de 'resolu' à 'en_cours' directement
        // Il faut d'abord que le ticket soit en statut 'reopen'
        $user = auth()->user();
        if ($user && $user->role === 'collaborateur' && isset($validated['statut'])) {
            if ($oldStatus === 'resolu' && $validated['statut'] === 'en_cours') {
                return response()->json([
                    'message' => 'Vous ne pouvez pas repasser un ticket résolu en "en_cours" directement. Le ticket doit d\'abord être réouvert (statut "reopen") par un manager ou admin.'
                ], 403);
            }
        }

        $ticket->update($validated);

        // Détecter si l'assigné a changé
        if ($ticket->assigned_to && $ticket->assigned_to != $oldAssignedTo) {
            $ticket->load('projet');
            $message = "Le ticket #{$ticket->id}: {$ticket->titre} vous a été assigné.\n";
            $message .= "Projet: {$ticket->projet->nom} (Type: {$ticket->projet->type})";
            NotificationService::send($ticket->assignedTo, 'Ticket assigné', $message, ['system', 'email', 'whatsapp']);
        }

        // Détecter le statut 'reopen' - notification spécifique au collaborateur
        if ($ticket->statut === 'reopen' && $oldStatus !== 'reopen') {
            if ($ticket->assigned_to) {
                $ticket->load('projet');
                $message = "Le ticket #{$ticket->id}: {$ticket->titre} a été RÉOUVERT pour des mises à jour.\n";
                $message .= "Projet: {$ticket->projet->nom} (Type: {$ticket->projet->type})\n";
                $message .= "Veuillez vérifier les nouveaux commentaires et effectuer les modifications nécessaires.";
                NotificationService::send($ticket->assignedTo, 'Ticket RÉOUVERT - Action Requise', $message, ['system', 'email', 'whatsapp']);
            }
        }

        // Notifier le manager et l'admin lors des changements de statut par le collaborateur
        $currentUser = auth()->user();
        if ($currentUser && $currentUser->role === 'collaborateur' && $ticket->statut !== $oldStatus) {
            $ticket->load('projet');
            
            // en_attente → en_cours : Le collaborateur commence à travailler
            if ($oldStatus === 'en_attente' && $ticket->statut === 'en_cours') {
                $message = "Le collaborateur {$currentUser->name} a commencé à travailler sur le ticket #{$ticket->id}: {$ticket->titre}.\n";
                $message .= "Projet: {$ticket->projet->nom} (Type: {$ticket->projet->type})";
                
                // Notifier le manager de l'équipe
                if ($currentUser->team_id) {
                    $manager = \App\Models\User::where('role', 'manager')
                        ->where('team_id', $currentUser->team_id)
                        ->first();
                    if ($manager) {
                        NotificationService::send($manager, 'Ticket pris en charge', $message, ['system', 'whatsapp']);
                    }
                }
                
                // Notifier l'admin
                $admin = \App\Models\User::where('role', 'admin')->first();
                if ($admin) {
                    NotificationService::send($admin, 'Ticket pris en charge', $message, ['system']);
                }
            }
            
            // en_cours → resolu : Le collaborateur a terminé le travail
            if ($oldStatus === 'en_cours' && $ticket->statut === 'resolu') {
                $message = "Le collaborateur {$currentUser->name} a marqué le ticket #{$ticket->id}: {$ticket->titre} comme RÉSOLU.\n";
                $message .= "Projet: {$ticket->projet->nom} (Type: {$ticket->projet->type})\n";
                $message .= "Validation requise.";
                
                // Notifier le manager de l'équipe
                if ($currentUser->team_id) {
                    $manager = \App\Models\User::where('role', 'manager')
                        ->where('team_id', $currentUser->team_id)
                        ->first();
                    if ($manager) {
                        NotificationService::send($manager, 'Ticket résolu - Validation requise', $message, ['system', 'email', 'whatsapp']);
                    }
                }
                
                // Notifier l'admin
                $admin = \App\Models\User::where('role', 'admin')->first();
                if ($admin) {
                    NotificationService::send($admin, 'Ticket résolu - Validation requise', $message, ['system', 'email']);
                }
            }
        }

        // Détecter la réouverture générale (ex: de 'ferme' ou 'resolu' vers autre chose)
        if (in_array($oldStatus, ['ferme', 'resolu']) && !in_array($ticket->statut, ['ferme', 'resolu', 'reopen'])) {
            $notifyUsers = array_unique(array_filter([$ticket->created_by, $ticket->assigned_to]));
            foreach ($notifyUsers as $userId) {
                if ($userId !== auth()->id()) {
                    $targetUser = \App\Models\User::find($userId);
                    if ($targetUser) {
                        NotificationService::send($targetUser, 'Ticket RÉOUVERT', "Le ticket #{$ticket->id}: {$ticket->titre} a été réouvert par " . auth()->user()->name, ['system', 'email', 'whatsapp']);
                    }
                }
            }
        }

        return response()->json($ticket);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $ticket = Ticket::findOrFail($id);
        $ticket->delete();
        return response()->json(null, 204);
    }

    /**
     * Add comment to ticket
     */
    public function ajouterCommentaire(Request $request, Ticket $ticket)
    {
        $validated = $request->validate([
            'commentaire' => 'nullable|string', // Nullable pour permettre des fichiers sans commentaire
            'fichiers.*' => 'nullable|file|max:10240', // Jusqu'à 10MB par fichier
        ]);

        // Au moins un commentaire ou un fichier doit être fourni
        if (empty($validated['commentaire']) && !$request->hasFile('fichiers')) {
            return response()->json(['message' => 'Un commentaire ou un fichier est requis'], 422);
        }

        $commentaire = $ticket->commentaires()->create([
            'commentaire' => $validated['commentaire'] ?? '',
            'user_id' => auth()->id(),
        ]);

        // Traiter les fichiers joints
        if ($request->hasFile('fichiers')) {
            foreach ($request->file('fichiers') as $file) {
                $path = $file->store('attachments/tickets/' . $ticket->id, 'public');
                
                $commentaire->attachments()->create([
                    'ticket_id' => $ticket->id,
                    'user_id' => auth()->id(),
                    'fichier_path' => $path,
                    'fichier_nom_original' => $file->getClientOriginalName(),
                    'mime_type' => $file->getMimeType(),
                    'taille' => $file->getSize(),
                ]);
            }
        }

        $ticket->load('projet');
        $currentUser = auth()->user();
        
        // Détecter les mentions dans le commentaire
        $mentionedUsers = [];
        $commentText = $validated['commentaire'];
        
        // Détecter @all - notifie tous les utilisateurs impliqués dans le projet
        if (preg_match('/@all\b/i', $commentText)) {
            // Récupérer tous les utilisateurs du projet (créateur, assignés des tickets, etc.)
            $projectUsers = \App\Models\User::whereHas('assignedTickets', function($q) use ($ticket) {
                $q->where('projet_id', $ticket->projet_id);
            })->orWhereHas('createdTickets', function($q) use ($ticket) {
                $q->where('projet_id', $ticket->projet_id);
            })->orWhere('id', $ticket->projet->manager_id)
            ->where('id', '!=', $currentUser->id)
            ->get();
            
            foreach ($projectUsers as $user) {
                $mentionedUsers[$user->id] = $user;
            }
        }
        
        // Détecter les mentions individuelles @username
        preg_match_all('/@(\w+(?:\s+\w+)*)/u', $commentText, $matches);
        if (!empty($matches[1])) {
            foreach ($matches[1] as $username) {
                if (strtolower($username) === 'all') continue; // Déjà traité
                
                // Chercher les utilisateurs par nom (insensible à la casse)
                $users = \App\Models\User::whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($username) . '%'])
                    ->where('id', '!=', $currentUser->id)
                    ->get();
                
                foreach ($users as $user) {
                    $mentionedUsers[$user->id] = $user;
                }
            }
        }
        
        // Notifier les utilisateurs mentionnés
        if (!empty($mentionedUsers)) {
            foreach ($mentionedUsers as $user) {
                $message = "{$currentUser->name} vous a mentionné dans un commentaire sur le ticket #{$ticket->id}: {$ticket->titre}\n";
                $message .= "Projet: {$ticket->projet->nom}\n";
                $message .= "Commentaire: " . substr($commentText, 0, 100) . (strlen($commentText) > 100 ? '...' : '');
                
                NotificationService::send($user, 'Vous avez été mentionné', $message, ['system', 'whatsapp']);
            }
        } else {
            // Si aucune mention, notifier créateur et assigné (comportement par défaut)
            $notifyUsers = array_unique(array_filter([$ticket->created_by, $ticket->assigned_to]));
            foreach ($notifyUsers as $userId) {
                if ($userId !== auth()->id()) {
                    $targetUser = \App\Models\User::find($userId);
                    if ($targetUser) {
                        NotificationService::send($targetUser, 'Nouveau commentaire', "Un commentaire a été ajouté sur le ticket #{$ticket->id} par " . auth()->user()->name, ['system', 'whatsapp']);
                    }
                }
            }
        }

        return response()->json($commentaire->load(['user', 'attachments.user']), 201);
    }

    /**
     * Get ticket comments
     */
    public function commentaires(Ticket $ticket)
    {
        return response()->json($ticket->commentaires()->with('user')->get());
    }

    /**
     * Add attachment to ticket
     */
    public function ajouterAttachment(Request $request, Ticket $ticket)
    {
        $request->validate([
            'fichier' => 'required|file|max:10240',
        ]);

        $file = $request->file('fichier');
        $path = $file->store('attachments/tickets/' . $ticket->id, 'public');

        $attachment = $ticket->attachments()->create([
            'user_id' => auth()->id(),
            'fichier_path' => $path,
            'fichier_nom_original' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'taille' => $file->getSize(),
        ]);

        return response()->json($attachment, 201);
    }

    /**
     * Get ticket attachments
     */
    public function attachments(Ticket $ticket)
    {
        return response()->json($ticket->attachments);
    }

    /**
     * Delete ticket attachment
     */
    public function deleteAttachment(Request $request, Ticket $ticket, TicketAttachment $attachment)
    {
        // Vérifier que la pièce jointe appartient bien au ticket
        if ($attachment->ticket_id !== $ticket->id) {
            return response()->json(['message' => 'Pièce jointe non trouvée pour ce ticket'], 404);
        }

        // Supprimer le fichier du stockage
        if (Storage::disk('public')->exists($attachment->fichier_path)) {
            Storage::disk('public')->delete($attachment->fichier_path);
        }

        // Supprimer l'enregistrement de la base de données
        $attachment->delete();

        return response()->json(['message' => 'Pièce jointe supprimée avec succès'], 200);
    }

    /**
     * Change ticket status
     */
    public function changerStatut(Request $request, Ticket $ticket)
    {
        $validated = $request->validate([
            'statut' => 'required|in:en_attente,en_cours,reopen,resolu,ferme',
        ]);

        $user = $request->user();
        if ($user->role === 'collaborateur' && $validated['statut'] === 'ferme') {
            return response()->json(['message' => 'Seuls les managers ou admins peuvent fermer un ticket.'], 403);
        }

        // Validation spécifique : Un collaborateur ne peut pas repasser de 'resolu' à 'en_cours' directement
        if ($user->role === 'collaborateur' && $ticket->statut === 'resolu' && $validated['statut'] === 'en_cours') {
            return response()->json([
                'message' => 'Vous ne pouvez pas repasser un ticket résolu en "en_cours" directement. Le ticket doit d\'abord être réouvert (statut "reopen") par un manager ou admin.'
            ], 403);
        }

        $oldStatus = $ticket->statut;
        $ticket->update(['statut' => $validated['statut']]);
        $newStatus = $ticket->statut;

        // Notification spécifique pour le statut 'reopen'
        if ($newStatus === 'reopen') {
            if ($ticket->assigned_to) {
                $ticket->load('projet');
                $message = "Le ticket #{$ticket->id}: {$ticket->titre} a été RÉOUVERT pour des mises à jour.\n";
                $message .= "Projet: {$ticket->projet->nom} (Type: {$ticket->projet->type})\n";
                $message .= "Veuillez vérifier les nouveaux commentaires et effectuer les modifications nécessaires.";
                NotificationService::send($ticket->assignedTo, 'Ticket RÉOUVERT - Action Requise', $message, ['system', 'email', 'whatsapp']);
            }
        } else {
            // Notifier le manager et l'admin lors des changements de statut par le collaborateur
            if ($user && $user->role === 'collaborateur' && $newStatus !== $oldStatus) {
                $ticket->load('projet');
                
                // en_attente → en_cours : Le collaborateur commence à travailler
                if ($oldStatus === 'en_attente' && $newStatus === 'en_cours') {
                    $message = "Le collaborateur {$user->name} a commencé à travailler sur le ticket #{$ticket->id}: {$ticket->titre}.\n";
                    $message .= "Projet: {$ticket->projet->nom} (Type: {$ticket->projet->type})";
                    
                    // Notifier le manager de l'équipe
                    if ($user->team_id) {
                        $manager = \App\Models\User::where('role', 'manager')
                            ->where('team_id', $user->team_id)
                            ->first();
                        if ($manager) {
                            NotificationService::send($manager, 'Ticket pris en charge', $message, ['system', 'whatsapp']);
                        }
                    }
                    
                    // Notifier l'admin
                    $admin = \App\Models\User::where('role', 'admin')->first();
                    if ($admin) {
                        NotificationService::send($admin, 'Ticket pris en charge', $message, ['system']);
                    }
                }
                
                // en_cours → resolu : Le collaborateur a terminé le travail
                if ($oldStatus === 'en_cours' && $newStatus === 'resolu') {
                    $message = "Le collaborateur {$user->name} a marqué le ticket #{$ticket->id}: {$ticket->titre} comme RÉSOLU.\n";
                    $message .= "Projet: {$ticket->projet->nom} (Type: {$ticket->projet->type})\n";
                    $message .= "Validation requise.";
                    
                    // Notifier le manager de l'équipe
                    if ($user->team_id) {
                        $manager = \App\Models\User::where('role', 'manager')
                            ->where('team_id', $user->team_id)
                            ->first();
                        if ($manager) {
                            NotificationService::send($manager, 'Ticket résolu - Validation requise', $message, ['system', 'email', 'whatsapp']);
                        }
                    }
                    
                    // Notifier l'admin
                    $admin = \App\Models\User::where('role', 'admin')->first();
                    if ($admin) {
                        NotificationService::send($admin, 'Ticket résolu - Validation requise', $message, ['system', 'email']);
                    }
                }
            }
            
            // Notification pour le créateur ou l'assigné (autres statuts)
            $notifyUsers = array_unique(array_filter([$ticket->created_by, $ticket->assigned_to]));
            foreach ($notifyUsers as $userId) {
                if ($userId !== auth()->id()) {
                    $targetUser = \App\Models\User::find($userId);
                    if ($targetUser) {
                        $isReopening = in_array($oldStatus, ['ferme', 'resolu']) && !in_array($newStatus, ['ferme', 'resolu', 'reopen']);
                        $subject = $isReopening ? 'Ticket RÉOUVERT' : 'Statut mis à jour';
                        $channels = $isReopening ? ['system', 'email', 'whatsapp'] : ['system', 'whatsapp'];
                        
                        NotificationService::send($targetUser, $subject, "Le statut du ticket #{$ticket->id} est passé de {$oldStatus} à : {$newStatus}", $channels);
                    }
                }
            }
        }

        return response()->json($ticket);
    }

    /**
     * Assign ticket to user
     */
    public function assigner(Request $request, Ticket $ticket)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        $ticket->update(['assigned_to' => $validated['user_id']]);
        $ticket->load('projet');

        // Notification pour le nouvel assigné
        $message = "Le ticket #{$ticket->id}: {$ticket->titre} vous a été assigné.\n";
        $message .= "Projet: {$ticket->projet->nom} (Type: {$ticket->projet->type})";
        NotificationService::send($ticket->assignedTo, 'Ticket assigné', $message, ['system', 'email', 'whatsapp']);

        return response()->json($ticket);
    }

    /**
     * Search users for mentions (autocomplete)
     */
    public function searchUsersForMention(Request $request, Ticket $ticket)
    {
        $query = $request->get('q', '');
        
        if (strlen($query) < 2) {
            return response()->json([]);
        }
        
        // Rechercher les utilisateurs liés au projet
        $users = \App\Models\User::where(function($q) use ($query) {
            $q->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($query) . '%'])
              ->orWhereRaw('LOWER(email) LIKE ?', ['%' . strtolower($query) . '%']);
        })
        ->where('id', '!=', auth()->id())
        ->select('id', 'name', 'email', 'role')
        ->limit(10)
        ->get();
        
        return response()->json($users);
    }
}
