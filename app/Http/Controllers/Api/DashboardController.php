<?php

namespace App\Http\Controllers\Api;

use App\Models\Infraction;
use App\Models\Accident;
use App\Models\Personnel;
use App\Models\ImmigrationClandestine;
use App\Models\ServiceRemunere;
use App\Models\AmendePieceSaisie;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use OpenApi\Annotations as OA;

/**
 * Contrôleur pour le tableau de bord et les statistiques.
 * Fournit les KPIs et données agrégées pour les graphiques.
 */
class DashboardController extends ApiController
{
    // KPIs globaux
    /**
     * @OA\Get(
     *     path="/api/dashboard/stats",
     *     tags={"Dashboard"},
     *     summary="KPIs globaux du tableau de bord",
     *     description="Retourne les statistiques clés pour une année donnée",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="annee", in="query", required=false, description="Année concernée", @OA\Schema(type="integer", example=2025)),
     *     @OA\Response(
     *         response=200,
     *         description="Statistiques retournées",
     *         @OA\JsonContent(
     *             @OA\Property(property="total_infractions", type="integer", example=250),
     *             @OA\Property(property="infractions_constatees", type="integer", example=180),
     *             @OA\Property(property="infractions_deferees", type="integer", example=70),
     *             @OA\Property(property="total_accidents", type="integer", example=45),
     *             @OA\Property(property="accidents_mortels", type="integer", example=5),
     *             @OA\Property(property="total_personnel", type="integer", example=320),
     *             @OA\Property(property="total_immigration", type="integer", example=150)
     *         )
     *     )
     * )
     */
    public function stats(Request $request): JsonResponse
    {
        $annee = $request->get('annee', date('Y'));

        $data = [
            'total_infractions' => Infraction::byAnnee($annee)->count(),
            'infractions_constatees' => Infraction::byAnnee($annee)->byIssue('Constatée')->count(),
            'infractions_deferees' => Infraction::byAnnee($annee)->byIssue('Déférée')->count(),
            'total_accidents' => Accident::whereYear('date', $annee)->count(),
            'accidents_mortels' => Accident::whereYear('date', $annee)->byType('mortel')->count(),
            'accidents_corporels' => Accident::whereYear('date', $annee)->byType('corporel')->count(),
            'accidents_materiels' => Accident::whereYear('date', $annee)->byType('matériel')->count(),
            'total_personnel' => Personnel::byStatut('Actif')->count(),
            'total_immigration' => ImmigrationClandestine::whereYear('date', $annee)->sum('nombre_interpellation'),
            'total_services_remuneres' => ServiceRemunere::whereYear('date', $annee)->sum('montant'),
            'total_amendes' => AmendePieceSaisie::whereYear('date', $annee)->byType('Amende')->sum('montant'),
        ];

        return $this->successResponse($data, 'Statistiques globales.');
    }

    /**
     * @OA\Get(
     *     path="/api/dashboard/infractions-par-region",
     *     tags={"Dashboard"},
     *     summary="Infractions par région",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="annee", in="query", required=false, @OA\Schema(type="integer", example=2025)),
     *     @OA\Response(response=200, description="Nombre d'infractions groupées par région")
     * )
     */
    public function infractionsParRegion(Request $request): JsonResponse
    {
        $annee = $request->get('annee', date('Y'));

        $data = DB::table('infractions')
            ->join('communes', 'infractions.commune_id', '=', 'communes.id')
            ->join('departements', 'communes.departement_id', '=', 'departements.id')
            ->join('regions', 'departements.region_id', '=', 'regions.id')
            ->where('infractions.annee', $annee)
            ->select('regions.nom as region', DB::raw('COUNT(*) as total'))
            ->groupBy('regions.nom')
            ->orderByDesc('total')
            ->get();

        return $this->successResponse($data);
    }

    /**
     * @OA\Get(
     *     path="/api/dashboard/accidents-par-type",
     *     tags={"Dashboard"},
     *     summary="Accidents groupés par type",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="annee", in="query", required=false, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Accidents par type (matériel, corporel, mortel)")
     * )
     */
    public function accidentsParType(Request $request): JsonResponse
    {
        $annee = $request->get('annee', date('Y'));

        $data = Accident::whereYear('date', $annee)
            ->select('type', DB::raw('COUNT(*) as total'))
            ->groupBy('type')
            ->get();

        return $this->successResponse($data);
    }

    /**
     * @OA\Get(
     *     path="/api/dashboard/tendances-mensuelles",
     *     tags={"Dashboard"},
     *     summary="Tendances mensuelles infractions et accidents",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="annee", in="query", required=false, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Données mensuelles pour les graphiques")
     * )
     */
    public function tendancesMensuelles(Request $request): JsonResponse
    {
        $annee = $request->get('annee', date('Y'));

        $infractions = DB::table('infractions')
            ->where('annee', $annee)
            ->select(DB::raw('EXTRACT(MONTH FROM date) as mois'), DB::raw('COUNT(*) as total'))
            ->groupBy('mois')
            ->orderBy('mois')
            ->get();

        $accidents = DB::table('accidents')
            ->whereYear('date', $annee)
            ->select(DB::raw('EXTRACT(MONTH FROM date) as mois'), DB::raw('COUNT(*) as total'))
            ->groupBy('mois')
            ->orderBy('mois')
            ->get();

        return $this->successResponse([
            'infractions' => $infractions,
            'accidents' => $accidents,
        ]);
    }

    // Infractions par type
    public function infractionsParType(Request $request): JsonResponse
    {
        $annee = $request->get('annee', date('Y'));

        $data = DB::table('infractions')
            ->join('type_infractions', 'infractions.type_infraction_id', '=', 'type_infractions.id')
            ->join('categorie_infractions', 'type_infractions.categorie_infraction_id', '=', 'categorie_infractions.id')
            ->where('infractions.annee', $annee)
            ->select('categorie_infractions.nom as categorie', 'type_infractions.nom as type', DB::raw('COUNT(*) as total'))
            ->groupBy('categorie_infractions.nom', 'type_infractions.nom')
            ->orderByDesc('total')
            ->get();

        return $this->successResponse($data);
    }

    // Personnel par service
    public function personnelParService(): JsonResponse
    {
        $data = DB::table('personnels')
            ->join('services', 'personnels.service_id', '=', 'services.id')
            ->where('personnels.statut', 'Actif')
            ->select('services.nom as service', 'services.type', DB::raw('COUNT(*) as total'))
            ->groupBy('services.nom', 'services.type')
            ->orderByDesc('total')
            ->get();

        return $this->successResponse($data);
    }
}
