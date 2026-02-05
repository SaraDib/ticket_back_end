<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use App\Services\NotificationService;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $query = User::with(['team', 'documents']);
        
        // Restriction pour les clients : ils ne peuvent voir que les admins et managers
        if ($user->role === 'client') {
            $query->whereIn('role', ['admin', 'manager']);
        } elseif ($user->role === 'manager') {
            // Les managers ne voient que les membres de leur équipe
            if ($user->team_id) {
                $query->where('team_id', $user->team_id);
            } else {
                // Si le manager n'a pas d'équipe, il ne voit personne
                $query->whereRaw('1 = 0');
            }
        }
        
        if ($request->has('role')) {
            $query->where('role', $request->role);
        }

        // Récupérer les coefficients depuis la base de données
        $internalCoeff = \App\Models\PointSetting::where('key', 'internal_coeff')->value('value') ?? 1.5;
        $externalCoeff = \App\Models\PointSetting::where('key', 'external_coeff')->value('value') ?? 1.0;

        $users = $query->with(['pointHistories.ticket.projet'])->get();

        // Calcul du cumul DH pour chaque utilisateur
        $users->each(function($u) use ($internalCoeff, $externalCoeff) {
            $total = 0;
            if ($u->pointHistories) {
                foreach ($u->pointHistories as $history) {
                    $isInterne = optional(optional($history->ticket)->projet)->type === 'interne';
                    $total += $history->points * ($isInterne ? $internalCoeff : $externalCoeff);
                }
            }
            $u->total_dh = $total;
        });

        return response()->json($users);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        \Log::info('🚀 [UserController] Début création utilisateur', ['data' => $request->all()]);
        
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
            'role' => 'required|in:admin,manager,collaborateur,client',
            'telephone' => 'nullable|string',
            'team_id' => 'nullable|exists:teams,id',
        ]);

        $validated['password'] = Hash::make($validated['password']);

        $user = User::create($validated);
        \Log::info('✅ [UserController] Utilisateur créé', ['user_id' => $user->id]);

        // Envoyer les notifications EN ARRIÈRE-PLAN après avoir renvoyé la réponse HTTP
        // Cela évite le timeout tout en permettant l'envoi WhatsApp
        // EMAIL DÉSACTIVÉ TEMPORAIREMENT pour test WhatsApp
        dispatch(function () use ($user) {
            try {
                \Log::info('📧 [UserController] Envoi notification en arrière-plan (WhatsApp uniquement)...');
                NotificationService::send(
                    $user, 
                    'Bienvenue !', 
                    "Votre compte Ticket Management a été créé. Vous pouvez vous connecter avec votre email : {$user->email}", 
                    ['system', 'email', 'whatsapp'] // System + Email + WhatsApp
                );
                \Log::info('✅ [UserController] Notifications envoyées en arrière-plan');
            } catch (\Exception $e) {
                \Log::error('❌ [UserController] Erreur notification en arrière-plan', [
                    'error' => $e->getMessage()
                ]);
            }
        })->afterResponse();

        \Log::info('🏁 [UserController] Fin création utilisateur, envoi réponse');
        return response()->json($user, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $user = User::with(['team', 'managedProjets', 'assignedTickets', 'documents'])->findOrFail($id);
        return response()->json($user);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $user = User::findOrFail($id);
        
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|string|email|max:255|unique:users,email,' . $id,
            'password' => 'nullable|string|min:8',
            'role' => 'sometimes|in:admin,manager,collaborateur,client',
            'telephone' => 'nullable|string',
            'team_id' => 'nullable|exists:teams,id',
        ]);

        if (isset($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        $user->update($validated);
        return response()->json($user);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $user = User::findOrFail($id);
        $user->delete();
        return response()->json(null, 204);
    }

    /**
     * Get user documents
     */
    public function documents(Request $request, User $user)
    {
        $currentUser = $request->user();
        
        // Un utilisateur peut voir ses propres documents, ou admin/manager peuvent tout voir
        if ($currentUser->id !== $user->id && !in_array($currentUser->role, ['admin', 'manager'])) {
            return response()->json(['message' => 'Accès interdit'], 403);
        }

        return response()->json($user->documents);
    }

    /**
     * Upload document for user
     */
    public function uploadDocument(Request $request, User $user)
    {
        $currentUser = $request->user();
        
        // Un utilisateur peut uploader pour lui-même, ou admin/manager pour tout le monde
        if ($currentUser->id !== $user->id && !in_array($currentUser->role, ['admin', 'manager'])) {
            return response()->json(['message' => 'Accès interdit'], 403);
        }

        $request->validate([
            'fichier' => 'required|file|max:10240',
            'type' => 'required|string|in:demande_stage,contrat,attestation_travail,attestation_stage,cv,contrat_confidentialite',
            'nom' => 'required|string|max:255',
        ]);

        $file = $request->file('fichier');
        $path = $file->store('documents/users/' . $user->id, 'public');

        $document = $user->documents()->create([
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
     * Delete document for user
     */
    public function supprimerDocument(Request $request, User $user, Document $document)
    {
        $currentUser = $request->user();
        
        // Un utilisateur peut supprimer ses propres documents, ou admin/manager peuvent tout faire
        if ($currentUser->id !== $user->id && !in_array($currentUser->role, ['admin', 'manager'])) {
            return response()->json(['message' => 'Accès interdit'], 403);
        }

        // Vérifier que le document appartient bien à l'utilisateur
        if ($document->documentable_id !== $user->id || $document->documentable_type !== User::class) {
            return response()->json(['message' => 'Document invalide'], 400);
        }

        // Supprimer le fichier physiquement
        Storage::delete($document->fichier_path);
        
        $document->delete();
        return response()->json(null, 204);
    }

    /**
     * Get user point history
     */
    public function pointHistory(Request $request)
    {
        $user = $request->user();
        $history = $user->pointHistories()->with(['ticket.projet'])->latest()->get();
        
        return response()->json([
            'total_points' => $user->points,
            'level' => $user->level,
            'history' => $history
        ]);
    }

    /**
     * Get team point history (for managers/admins)
     */
    public function teamPointHistory(Request $request)
    {
        $user = $request->user();
        
        $query = \App\Models\PointHistory::with(['user', 'ticket.projet'])->latest();
        
        if ($user->role === 'manager') {
            if ($user->team_id) {
                $query->whereHas('user', function($q) use ($user) {
                    $q->where('team_id', $user->team_id);
                });
            } else {
                return response()->json([]);
            }
        }
        
        // Admin sees all, manager sees team
        $history = $query->get();
        
        return response()->json($history);
    }
}
