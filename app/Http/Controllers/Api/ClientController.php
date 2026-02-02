<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Client;
use Illuminate\Http\Request;

class ClientController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        if ($user->role === 'client') {
            return response()->json(Client::with('user')->where('user_id', $user->id)->get());
        }
        return response()->json(Client::with('user')->get());
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'nom' => 'required|string|max:255',
            'ice' => 'nullable|string|unique:clients,ice',
            'identifiant_fiscal' => 'nullable|string',
            'telephone' => 'nullable|string',
            'email' => 'nullable|email|unique:clients,email',
            'adresse' => 'nullable|string',
            'user_id' => 'nullable|exists:users,id',
        ]);

        $client = Client::create($validated);
        return response()->json($client, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, string $id)
    {
        $user = $request->user();
        $client = Client::with('projets')->findOrFail($id);

        if ($user->role === 'client' && $client->user_id !== $user->id) {
            return response()->json(['message' => 'Accès interdit'], 403);
        }

        return response()->json($client);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $client = Client::findOrFail($id);

        $validated = $request->validate([
            'nom' => 'sometimes|string|max:255',
            'ice' => 'sometimes|string|unique:clients,ice,' . $id,
            'identifiant_fiscal' => 'sometimes|string',
            'telephone' => 'sometimes|string',
            'email' => 'sometimes|email|unique:clients,email,' . $id,
            'adresse' => 'sometimes|string',
            'user_id' => 'nullable|exists:users,id',
        ]);

        $client->update($validated);
        return response()->json($client);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $client = Client::findOrFail($id);
        $client->delete();
        return response()->json(null, 204);
    }
}
