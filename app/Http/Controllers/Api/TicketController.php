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
            'assigned_to' => 'nullable|exists:users,id',
            'priorite' => 'required|in:basse,normale,haute,urgente',
            'heures_estimees' => 'nullable|numeric',
            'deadline' => 'nullable|date',
        ]);

        $validated['created_by'] = auth()->id();
        $validated['statut'] = 'ouvert';

        $ticket = Ticket::create($validated);

        if ($ticket->assigned_to) {
            NotificationService::send($ticket->assignedTo, 'Nouveau ticket assigné', "Le ticket #{$ticket->id}: {$ticket->titre} vous a été assigné.", ['system', 'email', 'whatsapp']);
        }

        return response()->json($ticket, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $ticket = Ticket::with(['projet', 'createdBy', 'assignedTo', 'commentaires.user', 'attachments.user'])->findOrFail($id);
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
            'assigned_to' => 'nullable|exists:users,id',
            'statut' => 'sometimes|in:ouvert,en_cours,en_attente,resolu,ferme,rejete',
            'priorite' => 'sometimes|in:basse,normale,haute,urgente',
            'heures_estimees' => 'nullable|numeric|min:0',
            'heures_reelles' => 'nullable|numeric|min:0',
            'deadline' => 'nullable|date',
        ]);

        $ticket->update($validated);
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
            'commentaire' => 'required|string',
        ]);

        $commentaire = $ticket->commentaires()->create([
            'commentaire' => $validated['commentaire'],
            'user_id' => auth()->id(),
        ]);

        // Notification pour le créateur ou l'assigné
        $notifyUsers = array_unique(array_filter([$ticket->created_by, $ticket->assigned_to]));
        foreach ($notifyUsers as $userId) {
            if ($userId !== auth()->id()) {
                $targetUser = \App\Models\User::find($userId);
                if ($targetUser) {
                    NotificationService::send($targetUser, 'Nouveau commentaire', "Un commentaire a été ajouté sur le ticket #{$ticket->id} par " . auth()->user()->name, ['system', 'whatsapp']);
                }
            }
        }

        return response()->json($commentaire->load('user'), 201);
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
     * Change ticket status
     */
    public function changerStatut(Request $request, Ticket $ticket)
    {
        $validated = $request->validate([
            'statut' => 'required|in:ouvert,en_cours,en_attente,resolu,ferme,rejete',
        ]);

        $ticket->update(['statut' => $validated['statut']]);

        // Notification pour le créateur ou l'assigné
        $notifyUsers = array_unique(array_filter([$ticket->created_by, $ticket->assigned_to]));
        foreach ($notifyUsers as $userId) {
            if ($userId !== auth()->id()) {
                $targetUser = \App\Models\User::find($userId);
                if ($targetUser) {
                    NotificationService::send($targetUser, 'Statut mis à jour', "Le statut du ticket #{$ticket->id} est passé à : {$validated['statut']}", ['system', 'whatsapp']);
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

        // Notification pour le nouvel assigné
        NotificationService::send($ticket->assignedTo, 'Ticket assigné', "Le ticket #{$ticket->id}: {$ticket->titre} vous a été assigné.", ['system', 'email', 'whatsapp']);

        return response()->json($ticket);
    }
}
