<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ClientController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\MeetingController;
use App\Http\Controllers\Api\PointRateController;
use App\Http\Controllers\Api\PointSettingController;
use App\Http\Controllers\Api\ProjetController;
use App\Http\Controllers\Api\TeamController;
use App\Http\Controllers\Api\TicketController;
use App\Http\Controllers\Api\TicketRequestController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\WhatsAppController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Routes publiques (authentification)
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
});

// Routes protégées par authentification
Route::middleware('auth:sanctum')->group(function () {
    
    // Routes d'authentification (utilisateur connecté)
    Route::prefix('auth')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
        Route::put('/profile', [AuthController::class, 'updateProfile']);
    });

    // Clients (Admin uniquement - STRICT)
    Route::middleware('role:admin')->group(function () {
        Route::get('clients', [ClientController::class, 'index']);
        Route::get('clients/{client}', [ClientController::class, 'show']);
        Route::post('clients', [ClientController::class, 'store']);
        Route::put('clients/{client}', [ClientController::class, 'update']);
        Route::delete('clients/{client}', [ClientController::class, 'destroy']);
    });

    // Teams (Admin et Managers)
    Route::middleware('role:admin,manager')->group(function () {
        Route::apiResource('teams', TeamController::class);
        Route::post('teams/{team}/toggle-member', [TeamController::class, 'toggleMember']);
    });
    Route::middleware('role:manager')->get('teams/my-team/members', [TeamController::class, 'myTeamMembers']);

    // Users / Collaborateurs (Admin et Managers pour la liste complète, consultation restreinte pour autres)
    Route::get('users', [UserController::class, 'index']);
    Route::get('users/{user}', [UserController::class, 'show']);
    Route::get('users/{user}/documents', [UserController::class, 'documents']);
    Route::post('users/{user}/documents', [UserController::class, 'uploadDocument']);
    Route::delete('users/{user}/documents/{document}', [UserController::class, 'supprimerDocument']);
    
        Route::middleware('role:admin,manager')->group(function () {
        Route::post('users', [UserController::class, 'store']);
        Route::put('users/{user}', [UserController::class, 'update']);
        Route::delete('users/{user}', [UserController::class, 'destroy']);
    });

    // Points System
    Route::prefix('points')->group(function () {
        Route::get('/history', [UserController::class, 'pointHistory']);
        Route::middleware('role:admin,manager')->get('/team-history', [UserController::class, 'teamPointHistory']);
        
        // Settings (coefficients modifiables)
        Route::get('/settings', [PointSettingController::class, 'index']);
        Route::middleware('role:admin')->put('/settings/{id}', [PointSettingController::class, 'update']);
        
        // Grille tarifaire (taux par level)
        Route::get('/rates', [PointRateController::class, 'index']);
        Route::middleware('role:admin')->group(function () {
            Route::post('/rates', [PointRateController::class, 'store']);
            Route::put('/rates/{rate}', [PointRateController::class, 'update']);
            Route::delete('/rates/{rate}', [PointRateController::class, 'destroy']);
        });
    });

    // Projets (Admin et Managers pour création/modification, Clients pour consultation)
    Route::middleware('role:admin,manager,client,collaborateur')->group(function () {
        Route::get('projets', [ProjetController::class, 'index']);
        Route::get('projets/{id}', [ProjetController::class, 'show']);
        Route::get('projets/{projet}/etapes', [ProjetController::class, 'etapes']);
        Route::get('projets/{projet}/documents', [ProjetController::class, 'documents']);
        Route::get('projets/{projet}/tickets', [ProjetController::class, 'tickets']);
        
        // Documents (Désormais accessible aux clients aussi)
        Route::post('projets/{projet}/documents', [ProjetController::class, 'uploadDocument']);
        Route::delete('projets/{projet}/documents/{document}', [ProjetController::class, 'supprimerDocument']);
    });

    Route::middleware('role:admin,manager')->group(function () {
        Route::post('projets', [ProjetController::class, 'store']);
        Route::put('projets/{projet}', [ProjetController::class, 'update']);
        Route::delete('projets/{projet}', [ProjetController::class, 'destroy']);
        Route::post('projets/{projet}/etapes', [ProjetController::class, 'ajouterEtape']);
        Route::put('projets/{projet}/etapes/{etape}', [ProjetController::class, 'modifierEtape']);
        Route::delete('projets/{projet}/etapes/{etape}', [ProjetController::class, 'supprimerEtape']);
    });

    // Tickets (Tous peuvent consulter/commenter, Managers assignent)
    Route::apiResource('tickets', TicketController::class);
    Route::post('tickets/{ticket}/commentaires', [TicketController::class, 'ajouterCommentaire']);
    Route::get('tickets/{ticket}/commentaires', [TicketController::class, 'commentaires']);
    Route::get('tickets/{ticket}/search-users', [TicketController::class, 'searchUsersForMention']);
    Route::post('tickets/{ticket}/attachments', [TicketController::class, 'ajouterAttachment']);
    Route::get('tickets/{ticket}/attachments', [TicketController::class, 'attachments']);
    Route::delete('tickets/{ticket}/attachments/{attachment}', [TicketController::class, 'deleteAttachment']);
    Route::put('tickets/{ticket}/statut', [TicketController::class, 'changerStatut']);
    Route::middleware('role:admin,manager')->group(function () {
        Route::put('tickets/{ticket}/assigner', [TicketController::class, 'assigner']);
    });

    // Ticket Requests (Demandes de tickets par les clients)
    Route::prefix('ticket-requests')->group(function () {
        Route::get('/', [TicketRequestController::class, 'index']); // Liste (tous rôles)
        Route::post('/', [TicketRequestController::class, 'store']); // Créer (clients uniquement)
        Route::get('/{id}', [TicketRequestController::class, 'show']); // Voir détails
        
        // Routes admin/manager uniquement
        Route::middleware('role:admin,manager')->group(function () {
            Route::post('/{id}/approve', [TicketRequestController::class, 'approve']); // Approuver
            Route::post('/{id}/reject', [TicketRequestController::class, 'reject']); // Rejeter
            Route::get('/stats/summary', [TicketRequestController::class, 'stats']); // Statistiques
        });
    });

    // Meetings
    Route::apiResource('meetings', MeetingController::class);
    Route::middleware('role:admin,manager')->group(function () {
        Route::post('meetings/{meeting}/participants', [MeetingController::class, 'ajouterParticipants']);
        Route::delete('meetings/{meeting}/participants/{user}', [MeetingController::class, 'retirerParticipant']);
        Route::put('meetings/{meeting}/compte-rendu', [MeetingController::class, 'ajouterCompteRendu']);
    });
    Route::put('meetings/{meeting}/participants/{user}/presence', [MeetingController::class, 'modifierStatutPresence']);

    // Dashboard (Tous peuvent voir avec filtrage dans le contrôleur)
    Route::prefix('dashboard')->group(function () {
        Route::get('/stats', [DashboardController::class, 'stats']);
        Route::get('/disponibilite-equipe', [DashboardController::class, 'disponibiliteEquipe']);
        Route::get('/gantt', [DashboardController::class, 'gantt']);
        Route::get('/projets-avancement', [DashboardController::class, 'projetsAvancement']);
        Route::get('/echeances', [DashboardController::class, 'echeances']);
    });

    // Notifications
    Route::prefix('notifications')->group(function () {
        Route::get('/', function (Request $request) {
            return $request->user()->notifications()->latest()->take(20)->get();
        });
        Route::put('/{notification}/lire', function (Request $request, $notification) {
            $notif = $request->user()->notifications()->findOrFail($notification);
            $notif->marquerCommeLue();
            return response()->json(['success' => true]);
        });
        Route::put('/tout-lire', function (Request $request) {
            $request->user()->notifications()->where('lu', false)->update([
                'lu' => true,
                'lu_at' => now(),
            ]);
            return response()->json(['success' => true]);
        });
    });

    // WhatsApp Proxy
    Route::prefix('whatsapp')->group(function () {
        Route::get('/status', [WhatsAppController::class, 'getStatus']);
        Route::post('/logout', [WhatsAppController::class, 'logout']);
    });
});
