<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ClientController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\MeetingController;
use App\Http\Controllers\Api\ProjetController;
use App\Http\Controllers\Api\TeamController;
use App\Http\Controllers\Api\TicketController;
use App\Http\Controllers\Api\TicketRequestController;
use App\Http\Controllers\Api\UserController;
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

    // Clients (Admin uniquement pour modification, tous pour liste selon portée)
    Route::middleware('role:admin')->post('clients', [ClientController::class, 'store']);
    Route::middleware('role:admin')->put('clients/{client}', [ClientController::class, 'update']);
    Route::middleware('role:admin')->delete('clients/{client}', [ClientController::class, 'destroy']);
    Route::get('clients', [ClientController::class, 'index']); // La portée est gérée dans le contrôleur
    Route::get('clients/{client}', [ClientController::class, 'show']);

    // Teams (Admin et Managers)
    Route::middleware('role:admin,manager')->apiResource('teams', TeamController::class);
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
    Route::post('tickets/{ticket}/attachments', [TicketController::class, 'ajouterAttachment']);
    Route::get('tickets/{ticket}/attachments', [TicketController::class, 'attachments']);
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
});
