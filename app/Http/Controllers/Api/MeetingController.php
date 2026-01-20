<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Meeting;
use App\Models\User;
use App\Models\Projet;
use App\Models\Notification;
use App\Services\NotificationService;
use Illuminate\Http\Request;

class MeetingController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return response()->json(Meeting::with(['organisateur', 'projet', 'participants'])->get());
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'titre' => 'required|string|max:255',
            'description' => 'nullable|string',
            'date_heure' => 'required|date',
            'duree_minutes' => 'nullable|integer',
            'lieu' => 'nullable|string',
            'lien_visio' => 'nullable|string',
            'projet_id' => 'nullable|exists:projets,id',
            'participant_ids' => 'nullable|array',
            'participant_ids.*' => 'exists:users,id',
        ]);

        $validated['organisateur_id'] = auth()->id();
        $validated['statut'] = 'planifie';

        $meeting = Meeting::create($validated);

        if (isset($validated['participant_ids'])) {
            $meeting->participants()->attach($validated['participant_ids'], ['statut_presence' => 'invite']);
            
            foreach ($validated['participant_ids'] as $userId) {
                $pUser = User::find($userId);
                if ($pUser) {
                    NotificationService::send($pUser, 'Nouvelle réunion planifiée', "Vous êtes invité à la réunion: {$meeting->titre} le " . $meeting->date_heure->format('d/m/Y H:i'), ['system', 'email', 'whatsapp']);
                }
            }
        }

        return response()->json($meeting->load('participants'), 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $meeting = Meeting::with(['organisateur', 'projet', 'participants'])->findOrFail($id);
        return response()->json($meeting);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $meeting = Meeting::findOrFail($id);
        
        $validated = $request->validate([
            'titre' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'date_heure' => 'sometimes|date',
            'duree_minutes' => 'nullable|integer',
            'lieu' => 'nullable|string',
            'lien_visio' => 'nullable|string',
            'projet_id' => 'nullable|exists:projets,id',
            'statut' => 'sometimes|in:planifie,en_cours,termine,annule',
            'participant_ids' => 'nullable|array',
            'participant_ids.*' => 'exists:users,id',
        ]);

        $meeting->update($validated);

        if (isset($validated['participant_ids'])) {
            $meeting->participants()->sync($validated['participant_ids']);
        }

        return response()->json($meeting->load('participants'));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $meeting = Meeting::findOrFail($id);
        $meeting->delete();
        return response()->json(null, 204);
    }

    /**
     * Add participants to meeting
     */
    public function ajouterParticipants(Request $request, Meeting $meeting)
    {
        $validated = $request->validate([
            'participant_ids' => 'required|array',
            'participant_ids.*' => 'exists:users,id',
        ]);

        $meeting->participants()->syncWithoutDetaching($validated['participant_ids']);
        return response()->json($meeting->load('participants'));
    }

    /**
     * Remove participant from meeting
     */
    public function retirerParticipant(Meeting $meeting, User $user)
    {
        $meeting->participants()->detach($user->id);
        return response()->json(null, 204);
    }

    /**
     * Update presence status
     */
    public function modifierStatutPresence(Request $request, Meeting $meeting, User $user)
    {
        $validated = $request->validate([
            'statut_presence' => 'required|in:invite,confirme,refuse,absent,present',
        ]);

        $meeting->participants()->updateExistingPivot($user->id, [
            'statut_presence' => $validated['statut_presence']
        ]);

        return response()->json(['success' => true]);
    }

    /**
     * Add report (compte-rendu)
     */
    public function ajouterCompteRendu(Request $request, Meeting $meeting)
    {
        $validated = $request->validate([
            'compte_rendu' => 'required|string',
        ]);

        $meeting->update([
            'compte_rendu' => $validated['compte_rendu'],
            'statut' => 'termine'
        ]);

        return response()->json($meeting);
    }
}
