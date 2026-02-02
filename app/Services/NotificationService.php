<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    /**
     * Envoyer une notification par plusieurs canaux
     */
    public static function send(User $user, string $titre, string $message, array $canaux = ['system'])
    {
        // 1. Enregistrement en base (Système)
        $notification = Notification::create([
            'user_id' => $user->id,
            'titre' => $titre,
            'message' => $message,
            'type' => 'system',
            'canal' => in_array('whatsapp', $canaux) ? 'whatsapp' : (in_array('email', $canaux) ? 'email' : 'system'),
        ]);

        // 2. Envoi par Email
        if (in_array('email', $canaux) && $user->email) {
            try {
                Mail::raw($message, function ($mail) use ($user, $titre) {
                    $mail->to($user->email)
                         ->subject($titre);
                });
                Log::info("Email envoyé à {$user->email}");
            } catch (\Exception $e) {
                Log::error("Erreur envoi email: " . $e->getMessage());
            }
        }

        // 3. Envoi par WhatsApp (via Baileys service)
        if (in_array('whatsapp', $canaux) && $user->telephone) {
            try {
                // Normaliser le numéro au format international
                $phone = $user->telephone;
                // Si commence par 0, remplacer par +212 (Maroc)
                if (substr($phone, 0, 1) === '0') {
                    $phone = '+212' . substr($phone, 1);
                }
                // Si ne commence pas par +, ajouter +212
                if (substr($phone, 0, 1) !== '+') {
                    $phone = '+212' . $phone;
                }
                
                Log::info("Envoi WhatsApp vers {$phone}");
                
                // Utiliser la variable d'environnement pour l'URL du service
                $whatsappUrl = env('WHATSAPP_SERVICE_URL', 'http://localhost:3001');
                
                // Ajouter un timeout de 5 secondes pour éviter le blocage
                $response = Http::timeout(5)->post("{$whatsappUrl}/send-message", [
                    'phone' => $phone,
                    'message' => "*{$titre}*\n\n{$message}"
                ]);

                if ($response->successful()) {
                    $notification->update(['envoye' => true, 'envoye_at' => now()]);
                    Log::info("WhatsApp envoyé à {$phone}");
                } else {
                    Log::error("Erreur service WhatsApp: " . $response->body());
                }
            } catch (\Exception $e) {
                Log::error("Erreur connexion service WhatsApp: " . $e->getMessage());
            }
        }

        return $notification;
    }
}
