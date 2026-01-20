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
        $query = User::with(['team', 'documents']);
        
        if ($request->has('role')) {
            $query->where('role', $request->role);
        }

        return response()->json($query->get());
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
            'role' => 'required|in:admin,manager,collaborateur',
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
            'role' => 'sometimes|in:admin,manager,collaborateur',
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
    public function documents(User $user)
    {
        return response()->json($user->documents);
    }

    /**
     * Upload document for user
     */
    public function uploadDocument(Request $request, User $user)
    {
        $request->validate([
            'fichier' => 'required|file|max:10240',
            'type' => 'required|string|in:demande_stage,contrat,attestation_travail,attestation_stage,cv,contrat_confidentialite,autre',
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
}
