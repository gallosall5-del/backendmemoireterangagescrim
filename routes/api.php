<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\RegionController;
use App\Http\Controllers\Api\DepartementController;
use App\Http\Controllers\Api\CommuneController;
use App\Http\Controllers\Api\ServiceController;
use App\Http\Controllers\Api\PersonnelController;
use App\Http\Controllers\Api\TypeInfractionController;
use App\Http\Controllers\Api\InfractionController;
use App\Http\Controllers\Api\AccidentController;
use App\Http\Controllers\Api\VictimeController;
use App\Http\Controllers\Api\ServiceRemunereController;
use App\Http\Controllers\Api\AmendePieceSaisieController;
use App\Http\Controllers\Api\ImmigrationClandestineController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\ExportController;
use App\Http\Controllers\Api\SyncController;
use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\MediaController;
use App\Http\Controllers\Api\SearchController;

/*
|--------------------------------------------------------------------------
| Routes API - Teranga GESCRIM
|--------------------------------------------------------------------------
| Toutes les routes sont préfixées par /api
| L'authentification JWT est requise sauf pour le login
|--------------------------------------------------------------------------
*/

// ========== Authentification ==========
Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:10,1');
    Route::middleware('auth:api')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::post('refresh', [AuthController::class, 'refresh']);
        Route::get('me', [AuthController::class, 'me']);
        Route::post('change-password', [AuthController::class, 'changePassword']);
    });
});

// ========== Routes protégées par JWT ==========
Route::middleware('auth:api')->group(function () {

    // --- Subdivisions administratives ---
    Route::prefix('regions')->group(function () {
        Route::get('/', [RegionController::class, 'index']);
        Route::get('/all', [RegionController::class, 'all']);
        Route::get('/{region}', [RegionController::class, 'show']);
        Route::post('/', [RegionController::class, 'store']);
        Route::put('/{region}', [RegionController::class, 'update']);
        Route::delete('/{region}', [RegionController::class, 'destroy']);
    });

    Route::prefix('departements')->group(function () {
        Route::get('/', [DepartementController::class, 'index']);
        Route::get('/all', [DepartementController::class, 'all']);
        Route::get('/{departement}', [DepartementController::class, 'show']);
        Route::post('/', [DepartementController::class, 'store']);
        Route::put('/{departement}', [DepartementController::class, 'update']);
        Route::delete('/{departement}', [DepartementController::class, 'destroy']);
    });

    Route::prefix('communes')->group(function () {
        Route::get('/', [CommuneController::class, 'index']);
        Route::get('/all', [CommuneController::class, 'all']);
        Route::get('/{commune}', [CommuneController::class, 'show']);
        Route::post('/', [CommuneController::class, 'store']);
        Route::put('/{commune}', [CommuneController::class, 'update']);
        Route::delete('/{commune}', [CommuneController::class, 'destroy']);
    });

    Route::prefix('services')->group(function () {
        Route::get('/', [ServiceController::class, 'index']);
        Route::get('/all', [ServiceController::class, 'all']);
        Route::get('/{service}', [ServiceController::class, 'show']);
        Route::post('/', [ServiceController::class, 'store']);
        Route::put('/{service}', [ServiceController::class, 'update']);
        Route::delete('/{service}', [ServiceController::class, 'destroy']);
    });

    // --- Personnel ---
    Route::apiResource('personnels', PersonnelController::class);

    // --- Types et catégories d'infractions ---
    Route::prefix('categories-infractions')->group(function () {
        Route::get('/', [TypeInfractionController::class, 'categories']);
        Route::post('/', [TypeInfractionController::class, 'storeCategorie']);
        Route::put('/{categorie}', [TypeInfractionController::class, 'updateCategorie']);
        Route::delete('/{categorie}', [TypeInfractionController::class, 'destroyCategorie']);
    });

    Route::prefix('types-infractions')->group(function () {
        Route::get('/', [TypeInfractionController::class, 'index']);
        Route::post('/', [TypeInfractionController::class, 'store']);
        Route::put('/{typeInfraction}', [TypeInfractionController::class, 'update']);
        Route::delete('/{typeInfraction}', [TypeInfractionController::class, 'destroy']);
    });

    // --- Infractions ---
    Route::apiResource('infractions', InfractionController::class);

    // --- Accidents ---
    Route::apiResource('accidents', AccidentController::class);

    // --- Victimes ---
    Route::apiResource('victimes', VictimeController::class);

    // --- Services rémunérés ---
    Route::apiResource('services-remuneres', ServiceRemunereController::class);

    // --- Amendes et pièces saisies ---
    Route::apiResource('amendes-pieces-saisies', AmendePieceSaisieController::class);

    // --- Immigrations clandestines ---
    Route::apiResource('immigrations-clandestines', ImmigrationClandestineController::class);

    // --- Notifications ---
    Route::prefix('notifications')->group(function () {
        Route::get('/', [NotificationController::class, 'index']);
        Route::get('/unread-count', [NotificationController::class, 'unreadCount']);
        Route::put('/read-all', [NotificationController::class, 'markAllAsRead']);
        Route::put('/{notification}/read', [NotificationController::class, 'markAsRead']);
        Route::post('/send', [NotificationController::class, 'send']);
    });

    // --- Utilisateurs et rôles ---
    Route::prefix('users')->group(function () {
        Route::get('/', [UserController::class, 'index']);
        Route::get('/{user}', [UserController::class, 'show']);
        Route::post('/', [UserController::class, 'store']);
        Route::put('/{user}', [UserController::class, 'update']);
        Route::delete('/{user}', [UserController::class, 'destroy']);
    });
    Route::get('roles', [UserController::class, 'roles']);

    // --- Dashboard et statistiques ---
    Route::prefix('dashboard')->group(function () {
        Route::get('/stats', [DashboardController::class, 'stats']);
        Route::get('/infractions-par-region', [DashboardController::class, 'infractionsParRegion']);
        Route::get('/accidents-par-type', [DashboardController::class, 'accidentsParType']);
        Route::get('/tendances-mensuelles', [DashboardController::class, 'tendancesMensuelles']);
        Route::get('/infractions-par-type', [DashboardController::class, 'infractionsParType']);
        Route::get('/personnel-par-service', [DashboardController::class, 'personnelParService']);
    });

    // --- Export ---
    Route::prefix('export')->group(function () {
        Route::get('/infractions/pdf', [ExportController::class, 'infrationsPdf']);
        Route::get('/infractions/csv', [ExportController::class, 'infractionsCsv']);
        Route::get('/accidents/pdf', [ExportController::class, 'accidentsPdf']);
        Route::get('/accidents/csv', [ExportController::class, 'accidentsCsv']);
        Route::post('/import/json', [ExportController::class, 'importJson']);
    });

    // --- Synchronisation offline ---
    Route::prefix('sync')->group(function () {
        Route::post('/batch', [SyncController::class, 'batch']);
        Route::get('/status', [SyncController::class, 'status']);
    });

    // --- Audit logs ---
    Route::prefix('audit-logs')->group(function () {
        Route::get('/', [AuditLogController::class, 'index']);
        Route::get('/{auditLog}', [AuditLogController::class, 'show']);
    });

    // --- Recherche globale ---
    Route::get('/search', [SearchController::class, 'search']);

    // --- Médias (polymorphique) ---
    // Upload / liste : POST|GET /api/{type}/{id}/media   (type = infractions|accidents|personnels|victimes)
    Route::get('/{type}/{id}/media', [MediaController::class, 'index'])
        ->where('type', 'infractions|accidents|personnels|victimes');
    Route::post('/{type}/{id}/media', [MediaController::class, 'store'])
        ->where('type', 'infractions|accidents|personnels|victimes');
    // Téléchargement / suppression : GET|DELETE /api/media/{id}
    Route::get('/media/{id}/download', [MediaController::class, 'download']);
    Route::delete('/media/{id}', [MediaController::class, 'destroy']);
});
