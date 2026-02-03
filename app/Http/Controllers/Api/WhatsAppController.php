<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppController extends Controller
{
    /**
     * Proxy to get WhatsApp status
     */
    public function getStatus()
    {
        try {
            $whatsappUrl = env('WHATSAPP_SERVICE_URL', 'http://localhost:3001');
            $response = Http::timeout(5)->get("{$whatsappUrl}/status");
            
            return response()->json($response->json(), $response->status());
        } catch (\Exception $e) {
            Log::error("Erreur proxy WhatsApp getStatus: " . $e->getMessage());
            return response()->json([
                'connected' => false,
                'error' => 'Service WhatsApp indisponible'
            ], 503);
        }
    }

    /**
     * Proxy to logout WhatsApp
     */
    public function logout()
    {
        try {
            $whatsappUrl = env('WHATSAPP_SERVICE_URL', 'http://localhost:3001');
            $response = Http::timeout(5)->post("{$whatsappUrl}/logout");
            
            return response()->json($response->json(), $response->status());
        } catch (\Exception $e) {
            Log::error("Erreur proxy WhatsApp logout: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Impossible de se déconnecter du service WhatsApp'
            ], 503);
        }
    }
}
