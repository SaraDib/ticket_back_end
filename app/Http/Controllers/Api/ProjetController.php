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
    public function index()
    {
        $projets = Projet::with(['client', 'manager'])->get();
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
        ]);

        $projet = Projet::create($validated);

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
    public function show(string $id)
    {
        $projet = Projet::with(['client', 'manager', 'etapes', 'documents', 'tickets'])->findOrFail($id);
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
        ]);

        $oldManagerId = $projet->manager_id;
        $projet->update($validated);

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
    public function documents(Projet $projet)
    {
        return response()->json($projet->documents);
    }

    /**
     * Upload document for project
     */
    public function uploadDocument(Request $request, Projet $projet)
    {
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
    public function supprimerDocument(Projet $projet, Document $document)
    {
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
