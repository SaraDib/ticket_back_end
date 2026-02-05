<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TicketRequest;
use App\Models\Ticket;
use App\Models\User;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TicketRequestController extends Controller
{
    /**
     * Lister toutes les demandes (admin/manager) ou uniquement celles du client
     */
    public function index(Request $request)
    {
        $user = $request->user();

        if ($user->role === 'client') {
            // Client voit seulement ses propres demandes
            $clientId = $user->client->id;
            $requests = TicketRequest::with(['projet', 'etape', 'validateur', 'ticket'])
                ->where('client_id', $clientId)
                ->latest()
                ->get();
        } else {
            // Admin/Manager voient toutes les demandes
            $requests = TicketRequest::with(['projet', 'client', 'etape', 'validateur', 'ticket'])
                ->latest()
                ->get();
        }

        return response()->json($requests);
    }

    /**
     * Créer une nouvelle demande de ticket (client uniquement)
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'titre' => 'required|string|max:255',
            'description' => 'required|string',
            'projet_id' => 'required|exists:projets,id',
            'etape_id' => 'nullable|exists:projet_etapes,id',
            'priorite' => 'required|in:basse,normale,haute,urgente',
        ]);

        $user = $request->user();

        if ($user->role !== 'client') {
            return response()->json([
                'message' => 'Seuls les clients peuvent créer des demandes de tickets'
            ], 403);
        }

        $clientId = $user->client->id;

        $ticketRequest = TicketRequest::create([
            ...$validated,
            'client_id' => $clientId,
            'statut' => 'en_attente',
        ]);

        // Notifier tous les admins
        $admins = User::where('role', 'admin')->get();
        foreach ($admins as $admin) {
            Notification::create([
                'user_id' => $admin->id,
                'type' => 'ticket_request',
                'titre' => 'Nouvelle demande de ticket',
                'message' => "Le client {$user->client->nom} a demandé un nouveau ticket: {$ticketRequest->titre}",
                'data' => json_encode([
                    'ticket_request_id' => $ticketRequest->id,
                    'projet_id' => $ticketRequest->projet_id,
                ]),
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Demande de ticket créée avec succès',
            'ticket_request' => $ticketRequest->load(['projet', 'etape']),
        ], 201);
    }

    /**
     * Voir une demande spécifique
     */
    public function show(Request $request, $id)
    {
        $user = $request->user();
        $ticketRequest = TicketRequest::with(['projet', 'client', 'etape', 'validateur', 'ticket'])
            ->findOrFail($id);

        // Vérifier les permissions
        if ($user->role === 'client' && $ticketRequest->client_id !== $user->client->id) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        return response()->json($ticketRequest);
    }

    /**
     * Approuver une demande (admin/manager uniquement)
     */
    public function approve(Request $request, $id)
    {
        $user = $request->user();

        if (!in_array($user->role, ['admin', 'manager'])) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        $validated = $request->validate([
            'assigned_to' => 'required|exists:users,id',
            'heures_estimees' => 'nullable|integer',
            'deadline' => 'nullable|date',
        ]);

        $ticketRequest = TicketRequest::findOrFail($id);

        if ($ticketRequest->statut !== 'en_attente') {
            return response()->json([
                'message' => 'Cette demande a déjà été traitée'
            ], 400);
        }

        DB::beginTransaction();
        try {
            // Créer le ticket
            $ticket = Ticket::create([
                'titre' => $ticketRequest->titre,
                'description' => $ticketRequest->description,
                'projet_id' => $ticketRequest->projet_id,
                'etape_id' => $ticketRequest->etape_id,
                'priorite' => $ticketRequest->priorite,
                'created_by' => $user->id,
                'assigned_to' => $validated['assigned_to'],
                'heures_estimees' => $validated['heures_estimees'] ?? null,
                'deadline' => $validated['deadline'] ?? null,
                'statut' => 'en_cours',
            ]);

            // Mettre à jour la demande
            $ticketRequest->update([
                'statut' => 'approuve',
                'validateur_id' => $user->id,
                'ticket_id' => $ticket->id,
                'validated_at' => now(),
            ]);

            // Notifier le client
            if ($ticketRequest->client->user_id) {
                Notification::create([
                    'user_id' => $ticketRequest->client->user_id,
                    'type' => 'ticket_request_approved',
                    'titre' => 'Demande approuvée',
                    'message' => "Votre demande de ticket '{$ticketRequest->titre}' a été approuvée et un ticket a été créé.",
                    'data' => json_encode([
                        'ticket_id' => $ticket->id,
                        'ticket_request_id' => $ticketRequest->id,
                    ]),
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Demande approuvée et ticket créé',
                'ticket' => $ticket->load(['projet', 'assignedTo', 'createdBy']),
                'ticket_request' => $ticketRequest->fresh(['projet', 'client', 'validateur', 'ticket']),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Erreur lors de l\'approbation',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Rejeter une demande (admin/manager uniquement)
     */
    public function reject(Request $request, $id)
    {
        $user = $request->user();

        if (!in_array($user->role, ['admin', 'manager'])) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        $validated = $request->validate([
            'raison_rejet' => 'required|string',
        ]);

        $ticketRequest = TicketRequest::findOrFail($id);

        if ($ticketRequest->statut !== 'en_attente') {
            return response()->json([
                'message' => 'Cette demande a déjà été traitée'
            ], 400);
        }

        $ticketRequest->update([
            'statut' => 'rejete',
            'raison_rejet' => $validated['raison_rejet'],
            'validateur_id' => $user->id,
            'validated_at' => now(),
        ]);

        // Notifier le client
        if ($ticketRequest->client->user_id) {
            Notification::create([
                'user_id' => $ticketRequest->client->user_id,
                'type' => 'ticket_request_rejected',
                'titre' => 'Demande rejetée',
                'message' => "Votre demande de ticket '{$ticketRequest->titre}' a été rejetée. Raison: {$validated['raison_rejet']}",
                'data' => json_encode([
                    'ticket_request_id' => $ticketRequest->id,
                ]),
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Demande rejetée',
            'ticket_request' => $ticketRequest->fresh(['projet', 'client', 'validateur']),
        ]);
    }

    /**
     * Statistiques des demandes (admin uniquement)
     */
    public function stats()
    {
        $stats = [
            'en_attente' => TicketRequest::enAttente()->count(),
            'approuve' => TicketRequest::approuve()->count(),
            'rejete' => TicketRequest::rejete()->count(),
            'total' => TicketRequest::count(),
        ];

        return response()->json($stats);
    }
}
