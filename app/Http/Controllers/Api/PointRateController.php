<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PointRate;
use Illuminate\Http\Request;

class PointRateController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return response()->json(PointRate::orderBy('level')->get());
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'level' => 'required|integer|unique:point_rates',
            'rate' => 'required|numeric|min:0',
        ]);

        $pointRate = PointRate::create($validated);
        return response()->json($pointRate, 201);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $pointRate = PointRate::findOrFail($id);
        
        $validated = $request->validate([
            'level' => 'sometimes|integer|unique:point_rates,level,' . $id,
            'rate' => 'sometimes|numeric|min:0',
        ]);

        $pointRate->update($validated);
        return response()->json($pointRate);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $pointRate = PointRate::findOrFail($id);
        $pointRate->delete();
        return response()->json(null, 204);
    }
}
