<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Team;
use Illuminate\Http\Request;

class TeamController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $query = Team::with('members');

        // Les managers ne voient que leur propre équipe
        if ($user->role === 'manager' && $user->team_id) {
            $query->where('id', $user->team_id);
        }

        return response()->json($query->get());
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'nom' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $team = Team::create($validated);
        return response()->json($team, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $team = Team::with('members')->findOrFail($id);
        return response()->json($team);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $team = Team::findOrFail($id);

        $validated = $request->validate([
            'nom' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
        ]);

        $team->update($validated);
        return response()->json($team);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $team = Team::findOrFail($id);
        $team->delete();
        return response()->json(null, 204);
    }

    /**
     * Get my team members (for managers)
     */
    public function myTeamMembers(Request $request)
    {
        $user = $request->user();
        
        // Seuls les managers avec une équipe peuvent utiliser cette route
        if ($user->role !== 'manager' || !$user->team_id) {
            return response()->json(['message' => 'Accès interdit'], 403);
        }

        $team = Team::with('members')->findOrFail($user->team_id);
        return response()->json([
            'team' => $team,
            'members' => $team->members
        ]);
    }

    /**
     * Ajouter ou retirer un membre de l'équipe (Many-to-Many)
     */
    public function toggleMember(Request $request, Team $team)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        $userId = $validated['user_id'];
        
        if ($team->members()->where('user_id', $userId)->exists()) {
            $team->members()->detach($userId);
            $action = 'removed';
        } else {
            $team->members()->attach($userId);
            $action = 'added';
        }

        return response()->json(['status' => 'success', 'action' => $action]);
    }
}
