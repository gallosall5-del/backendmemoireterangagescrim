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
use App\Enums\ScopeType;
use OpenApi\Annotations as OA;

/**
 * Contrôleur pour le tableau de bord et les statistiques.
 * Fournit les KPIs et données agrégées pour les graphiques.
 */
class DashboardController extends ApiController
{
    /**
     * Applique les filtres geo (region_id, departement_id, commune_id, service_id)
     * et temporels (annee, mois) sur une query Eloquent de type Infraction.
     */
    private function applyInfractionFilters($query, Request $request)
    {
        $annee  = $request->get('annee');
        $mois   = $request->get('mois');
        $region = $request->get('region_id');
        $dept   = $request->get('departement_id');
        $commune= $request->get('commune_id');
        $svc    = $request->get('service_id');

        if ($annee)   $query->byAnnee($annee);
        if ($mois)    $query->whereMonth('date', (int)$mois);
        if ($svc)     $query->byService($svc);
        if ($commune) $query->byCommune($commune);
        if ($dept) {
            $query->whereHas('commune', fn($q) => $q->where('departement_id', $dept));
        }
        if ($region) {
            $query->whereHas('commune.departement', fn($q) => $q->where('region_id', $region));
        }
        return $query;
    }

    /**
     * Applique les filtres geo et temporels sur une query Accident.
     */
    private function applyAccidentFilters($query, Request $request)
    {
        $annee  = $request->get('annee');
        $mois   = $request->get('mois');
        $region = $request->get('region_id');
        $dept   = $request->get('departement_id');
        $commune= $request->get('commune_id');
        $svc    = $request->get('service_id');

        if ($annee)   $query->whereYear('date', $annee);
        if ($mois)    $query->whereMonth('date', (int)$mois);
        if ($svc)     $query->byService($svc);
        if ($commune) $query->byCommune($commune);
        if ($dept) {
            $query->whereHas('commune', fn($q) => $q->where('departement_id', $dept));
        }
        if ($region) {
            $query->whereHas('commune.departement', fn($q) => $q->where('region_id', $region));
        }
        return $query;
    }

    /**
     * Applique les filtres geo et temporels sur une query générique (Immigration, Amendes…).
     */
    private function applyGenericFilters($query, Request $request, bool $hasService = true)
    {
        $annee  = $request->get('annee');
        $mois   = $request->get('mois');
        $svc    = $request->get('service_id');

        if ($annee) $query->whereYear('date', $annee);
        if ($mois)  $query->whereMonth('date', (int)$mois);
        if ($svc && $hasService) $query->where('service_id', $svc);
        return $query;
    }

    /**
     * @OA\Get(
     *     path="/api/dashboard/stats",
     *     tags={"Dashboard"},
     *     summary="KPIs globaux du tableau de bord",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="annee",         in="query", required=false, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="mois",          in="query", required=false, @OA\Schema(type="integer", minimum=1, maximum=12)),
     *     @OA\Parameter(name="region_id",     in="query", required=false, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="departement_id",in="query", required=false, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="commune_id",    in="query", required=false, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="service_id",    in="query", required=false, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Statistiques retournées")
     * )
     */
    public function stats(Request $request): JsonResponse
    {
        $infractions = $this->applyInfractionFilters(Infraction::visibleByUser(), $request);
        $accidents   = $this->applyAccidentFilters(Accident::visibleByUser(), $request);
        $immigration = $this->applyGenericFilters(ImmigrationClandestine::visibleByUser(), $request);
        $servRem     = $this->applyGenericFilters(ServiceRemunere::visibleByUser(), $request, false);
        $amendes     = $this->applyGenericFilters(AmendePieceSaisie::visibleByUser(), $request);

        $data = [
            'total_infractions'        => (clone $infractions)->count(),
            'infractions_constatees'   => (clone $infractions)->byIssue('Constatée')->count(),
            'infractions_deferees'     => (clone $infractions)->byIssue('Déférée')->count(),
            'total_accidents'          => (clone $accidents)->count(),
            'accidents_mortels'        => (clone $accidents)->byType('mortel')->count(),
            'accidents_corporels'      => (clone $accidents)->byType('corporel')->count(),
            'accidents_materiels'      => (clone $accidents)->byType('matériel')->count(),
            'total_personnel'          => Personnel::visibleByUser()->byStatut('Actif')->count(),
            'total_immigration'        => (clone $immigration)->sum('nombre_interpellation'),
            'total_services_remuneres' => (clone $servRem)->sum('montant'),
            'total_amendes'            => (clone $amendes)->byType('Amende')->sum('montant'),
        ];

        return $this->successResponse($data, 'Statistiques globales.');
    }

    /**
     * @OA\Get(
     *     path="/api/dashboard/infractions-par-region",
     *     tags={"Dashboard"},
     *     summary="Infractions par région",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="annee", in="query", required=false, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="mois",  in="query", required=false, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Infractions groupées par région")
     * )
     */
    public function infractionsParRegion(Request $request): JsonResponse
    {
        $annee  = $request->get('annee', date('Y'));
        $mois   = $request->get('mois');
        $user   = auth()->user();

        $query = DB::table('infractions')
            ->join('communes',    'infractions.commune_id',       '=', 'communes.id')
            ->join('departements','communes.departement_id',       '=', 'departements.id')
            ->join('regions',     'departements.region_id',        '=', 'regions.id')
            ->where('infractions.annee', $annee);

        if ($mois) {
            $query->whereMonth('infractions.date', (int)$mois);
        }

        // Filtres geo frontend
        if ($request->has('region_id'))     $query->where('regions.id',       $request->region_id);
        if ($request->has('departement_id'))$query->where('departements.id',  $request->departement_id);
        if ($request->has('commune_id'))    $query->where('communes.id',       $request->commune_id);
        if ($request->has('service_id'))    $query->where('infractions.service_id', $request->service_id);

        // Scope territorial
        if ($user->read_scope_type !== ScopeType::NATIONAL) {
            match ($user->read_scope_type) {
                ScopeType::REGION      => $query->where('regions.id',       $user->read_scope_id),
                ScopeType::DEPARTEMENT => $query->where('departements.id',  $user->read_scope_id),
                ScopeType::COMMUNE     => $query->where('communes.id',      $user->read_scope_id),
                ScopeType::SERVICE     => $query->where('infractions.service_id', $user->read_scope_id),
                default                => null,
            };
        }

        $data = $query
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
     *     @OA\Parameter(name="mois",  in="query", required=false, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Accidents par type")
     * )
     */
    public function accidentsParType(Request $request): JsonResponse
    {
        $query = $this->applyAccidentFilters(Accident::visibleByUser(), $request);

        $data = $query
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
     *     @OA\Parameter(name="mois",  in="query", required=false, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Données mensuelles pour graphiques")
     * )
     */
    public function tendancesMensuelles(Request $request): JsonResponse
    {
        $mois    = $request->get('mois');
        $annee   = $request->get('annee');
        $svcId   = $request->get('service_id');

        $infQuery = $this->applyInfractionFilters(Infraction::visibleByUser(), $request);
        $accQuery = $this->applyAccidentFilters(Accident::visibleByUser(), $request);
        $immQuery = ImmigrationClandestine::visibleByUser();
        if ($annee) $immQuery->whereYear('date', $annee);
        if ($mois)  $immQuery->whereMonth('date', (int)$mois);
        if ($svcId) $immQuery->where('service_id', $svcId);

        if ($mois) {
            $infractions = $infQuery->select(DB::raw((int)$mois . ' as mois'), DB::raw('COUNT(*) as total'))->get();
            $accidents   = $accQuery->select(DB::raw((int)$mois . ' as mois'), DB::raw('COUNT(*) as total'))->get();
            $immigration = $immQuery->select(DB::raw((int)$mois . ' as mois'), DB::raw('SUM(nombre_interpellation) as total'))->get();
        } else {
            $infractions = $infQuery
                ->select(DB::raw('EXTRACT(MONTH FROM date)::int as mois'), DB::raw('COUNT(*) as total'))
                ->groupBy(DB::raw('EXTRACT(MONTH FROM date)::int'))
                ->orderBy('mois')->get();

            $accidents = $accQuery
                ->select(DB::raw('EXTRACT(MONTH FROM date)::int as mois'), DB::raw('COUNT(*) as total'))
                ->groupBy(DB::raw('EXTRACT(MONTH FROM date)::int'))
                ->orderBy('mois')->get();

            $immigration = $immQuery
                ->select(DB::raw('EXTRACT(MONTH FROM date)::int as mois'), DB::raw('SUM(nombre_interpellation) as total'))
                ->groupBy(DB::raw('EXTRACT(MONTH FROM date)::int'))
                ->orderBy('mois')->get();
        }

        return $this->successResponse([
            'infractions' => $infractions,
            'accidents'   => $accidents,
            'immigration' => $immigration,
        ]);
    }

    /**
     * Infractions par catégorie (PieChart "Formes de criminalité").
     */
    public function infractionsParType(Request $request): JsonResponse
    {
        $annee  = $request->get('annee', date('Y'));
        $mois   = $request->get('mois');
        $user   = auth()->user();

        $query = DB::table('infractions')
            ->join('type_infractions',     'infractions.type_infraction_id',          '=', 'type_infractions.id')
            ->join('categorie_infractions','type_infractions.categorie_infraction_id', '=', 'categorie_infractions.id')
            ->where('infractions.annee', $annee);

        if ($mois) {
            $query->whereMonth('infractions.date', (int)$mois);
        }

        if ($request->has('region_id') || $request->has('departement_id') || $request->has('commune_id')) {
            $query->join('communes',    'infractions.commune_id',  '=', 'communes.id')
                  ->join('departements','communes.departement_id', '=', 'departements.id');
            if ($request->has('region_id'))      $query->join('regions', 'departements.region_id', '=', 'regions.id')->where('regions.id', $request->region_id);
            if ($request->has('departement_id')) $query->where('departements.id', $request->departement_id);
            if ($request->has('commune_id'))     $query->where('communes.id',     $request->commune_id);
        }
        if ($request->has('service_id')) {
            $query->where('infractions.service_id', $request->service_id);
        }

        if ($user->read_scope_type !== ScopeType::NATIONAL) {
            if ($user->read_scope_type === ScopeType::SERVICE) {
                $query->where('infractions.service_id', $user->read_scope_id);
            } else {
                $query->whereIn('infractions.id', Infraction::visibleByUser()->select('id')->getQuery());
            }
        }

        $data = $query
            ->select('categorie_infractions.nom as categorie', 'type_infractions.nom as type', DB::raw('COUNT(*) as total'))
            ->groupBy('categorie_infractions.nom', 'type_infractions.nom')
            ->orderByDesc('total')
            ->get();

        return $this->successResponse($data);
    }

    /**
     * Personnel actif par service (BarChart "Effectifs par commissariat").
     */
    public function personnelParService(Request $request): JsonResponse
    {
        $user  = auth()->user();
        $svc   = $request->get('service_id');
        $region= $request->get('region_id');

        $query = DB::table('personnels')
            ->join('services', 'personnels.service_id', '=', 'services.id')
            ->where('personnels.statut', 'Actif');

        if ($svc)    $query->where('personnels.service_id', $svc);
        if ($region) {
            $query->join('communes',    'services.commune_id',    '=', 'communes.id')
                  ->join('departements','communes.departement_id','=', 'departements.id')
                  ->where('departements.region_id', $region);
        }

        if ($user->read_scope_type !== ScopeType::NATIONAL) {
            $query->whereIn('personnels.id', Personnel::visibleByUser()->select('id')->getQuery());
        }

        $data = $query
            ->select('services.nom as service', 'services.type', DB::raw('COUNT(*) as total'))
            ->groupBy('services.nom', 'services.type')
            ->orderByDesc('total')
            ->get();

        return $this->successResponse($data);
    }
}
