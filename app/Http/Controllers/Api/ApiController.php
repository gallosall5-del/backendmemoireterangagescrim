<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller as BaseController;
use OpenApi\Annotations as OA;

/**
 * @OA\Info(
 *     title="Teranga GESCRIM API",
 *     version="1.0.0",
 *     description="API REST pour le système de Gestion de la Criminalité et de la Délinquance — Direction de la Sécurité Publique (DSP) du Sénégal.",
 *     @OA\Contact(email="admin@gescrim.sn", name="NAMASTECH"),
 *     @OA\License(name="Propriétaire", url="https://gescrim.sn")
 * )
 *
 * @OA\Server(url=L5_SWAGGER_CONST_HOST, description="Serveur de développement")
 *
 * @OA\SecurityScheme(
 *     securityScheme="bearerAuth",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="JWT",
 *     description="Token JWT obtenu via POST /api/auth/login"
 * )
 *
 * @OA\Tag(name="Authentification", description="Connexion, déconnexion et gestion du token JWT")
 * @OA\Tag(name="Régions", description="Gestion des régions administratives")
 * @OA\Tag(name="Départements", description="Gestion des départements")
 * @OA\Tag(name="Communes", description="Gestion des communes")
 * @OA\Tag(name="Services DSP", description="Services de la DSP")
 * @OA\Tag(name="Personnel", description="Personnel de la DSP")
 * @OA\Tag(name="Types Infractions", description="Catégories et types d'infractions")
 * @OA\Tag(name="Infractions", description="Saisie et gestion des infractions")
 * @OA\Tag(name="Accidents", description="Accidents de la circulation")
 * @OA\Tag(name="Victimes", description="Victimes et impliqués")
 * @OA\Tag(name="Services Rémunérés", description="Services rémunérés")
 * @OA\Tag(name="Amendes & Pièces Saisies", description="Amendes et pièces")
 * @OA\Tag(name="Immigration Clandestine", description="Immigration clandestine")
 * @OA\Tag(name="Dashboard", description="Statistiques et KPIs")
 * @OA\Tag(name="Export", description="Export et Import")
 * @OA\Tag(name="Synchronisation", description="Synchronisation offline")
 * @OA\Tag(name="Audit", description="Journal d'audit")
 *
 * Contrôleur de base pour l'API.
 * Fournit des méthodes utilitaires pour formater les réponses JSON standardisées.
 */
class ApiController extends BaseController
{
    /**
     * Retourner une réponse de succès
     */
    protected function successResponse($data = null, string $message = 'Opération réussie', int $code = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $code);
    }

    /**
     * Retourner une réponse d'erreur
     */
    protected function errorResponse(string $message = 'Erreur', int $code = 400, $errors = null): JsonResponse
    {
        $response = [
            'success' => false,
            'message' => $message,
        ];

        if ($errors) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $code);
    }

    /**
     * Retourner une réponse paginée
     */
    protected function paginatedResponse($paginator, string $message = 'Données récupérées'): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
        ]);
    }
}
