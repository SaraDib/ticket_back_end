<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ClientController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\MeetingController;
use App\Http\Controllers\Api\ProjetController;
use App\Http\Controllers\Api\TeamController;
use App\Http\Controllers\Api\TicketController;
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

    // Clients (Admin uniquement)
    Route::middleware('role:admin')->apiResource('clients', ClientController::class);

    // Teams (Admin et Managers)
    Route::middleware('role:admin,manager')->apiResource('teams', TeamController::class);

    // Users / Collaborateurs (Admin et Managers pour la liste, Collaborateurs pour profil)
    Route::middleware('role:admin,manager')->group(function () {
        Route::apiResource('users', UserController::class);
        Route::get('users/{user}/documents', [UserController::class, 'documents']);
        Route::post('users/{user}/documents', [UserController::class, 'uploadDocument']);
    });

    // Projets (Admin et Managers)
    Route::middleware('role:admin,manager')->group(function () {
        Route::apiResource('projets', ProjetController::class);
        Route::get('projets/{projet}/etapes', [ProjetController::class, 'etapes']);
        Route::post('projets/{projet}/etapes', [ProjetController::class, 'ajouterEtape']);
        Route::put('projets/{projet}/etapes/{etape}', [ProjetController::class, 'modifierEtape']);
        Route::delete('projets/{projet}/etapes/{etape}', [ProjetController::class, 'supprimerEtape']);
        Route::get('projets/{projet}/documents', [ProjetController::class, 'documents']);
        Route::post('projets/{projet}/documents', [ProjetController::class, 'uploadDocument']);
        Route::delete('projets/{projet}/documents/{document}', [ProjetController::class, 'supprimerDocument']);
    });
    // Consultation projets pour collaborateurs
    Route::get('projets/{projet}/tickets', [ProjetController::class, 'tickets']);

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

    // Meetings
    Route::apiResource('meetings', MeetingController::class);
    Route::middleware('role:admin,manager')->group(function () {
        Route::post('meetings/{meeting}/participants', [MeetingController::class, 'ajouterParticipants']);
        Route::delete('meetings/{meeting}/participants/{user}', [MeetingController::class, 'retirerParticipant']);
        Route::put('meetings/{meeting}/compte-rendu', [MeetingController::class, 'ajouterCompteRendu']);
    });
    Route::put('meetings/{meeting}/participants/{user}/presence', [MeetingController::class, 'modifierStatutPresence']);

    // Dashboard (Admin et Managers)
    Route::middleware('role:admin,manager')->prefix('dashboard')->group(function () {
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
