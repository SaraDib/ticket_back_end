<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class PointSettingController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return response()->json(\App\Models\PointSetting::all());
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $setting = \App\Models\PointSetting::findOrFail($id);
        
        $validated = $request->validate([
            'value' => 'required|numeric|min:0',
        ]);

        $setting->update($validated);

        return response()->json($setting);
    }
}
