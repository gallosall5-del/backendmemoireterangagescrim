<?php

namespace App\Http\Controllers\Api;

use App\Models\Infraction;
use App\Models\Accident;
use App\Models\Personnel;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use OpenApi\Annotations as OA;

/**
 * Contrôleur pour l'export PDF/Excel et l'import de données.
 */
class ExportController extends ApiController
{
    /**
     * @OA\Get(
     *     path="/api/export/infractions/pdf",
     *     tags={"Export"},
     *     summary="Export PDF des infractions",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="annee", in="query", required=false, @OA\Schema(type="integer", example=2025)),
     *     @OA\Parameter(name="service_id", in="query", required=false, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="date_from", in="query", required=false, @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="date_to", in="query", required=false, @OA\Schema(type="string", format="date")),
     *     @OA\Response(response=200, description="Fichier PDF téléchargé", @OA\MediaType(mediaType="application/pdf"))
     * )
     */
    public function infrationsPdf(Request $request)
    {
        $query = Infraction::with(['typeInfraction.categorieInfraction', 'service', 'commune'])->visibleByUser();

        if ($request->filled('annee')) $query->byAnnee($request->annee);
        if ($request->filled('service_id')) $query->byService($request->service_id);
        if ($request->filled('date_from') || $request->filled('date_to')) {
            if ($request->filled('date_from') && $request->filled('date_to')) {
                $query->byDateRange($request->date_from, $request->date_to);
            } elseif ($request->filled('date_from')) {
                $query->where('date', '>=', $request->date_from);
            } else {
                $query->where('date', '<=', $request->date_to);
            }
        }
        if ($request->filled('hour')) {
            $query->whereHour('created_at', $request->hour);
        }
        if ($request->filled('commune_id')) {
            $query->byCommune($request->commune_id);
        } elseif ($request->filled('departement_id')) {
            $query->whereHas('commune', function($q) use ($request) {
                $q->where('departement_id', $request->departement_id);
            });
        } elseif ($request->filled('region_id')) {
            $query->whereHas('commune.departement', function($q) use ($request) {
                $q->where('region_id', $request->region_id);
            });
        }

        $infractions = $query->orderByDesc('date')->get();

        $pdf = Pdf::loadView('exports.infractions', [
            'infractions' => $infractions,
            'titre' => 'Rapport des Infractions',
            'date_generation' => now()->format('d/m/Y H:i'),
            'filters' => $request->all(),
        ]);

        return $pdf->download('infractions_' . now()->format('Y-m-d') . '.pdf');
    }

    /**
     * @OA\Get(
     *     path="/api/export/accidents/pdf",
     *     tags={"Export"},
     *     summary="Export PDF des accidents",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="type", in="query", required=false, @OA\Schema(type="string", enum={"matériel","corporel","mortel"})),
     *     @OA\Response(response=200, description="Fichier PDF téléchargé", @OA\MediaType(mediaType="application/pdf"))
     * )
     */
    public function accidentsPdf(Request $request)
    {
        $query = Accident::with(['service', 'commune', 'victimes'])->visibleByUser();

        if ($request->filled('type')) $query->byType($request->type);
        if ($request->filled('date_from') || $request->filled('date_to')) {
            if ($request->filled('date_from') && $request->filled('date_to')) {
                $query->byDateRange($request->date_from, $request->date_to);
            } elseif ($request->filled('date_from')) {
                $query->where('date', '>=', $request->date_from);
            } else {
                $query->where('date', '<=', $request->date_to);
            }
        }
        if ($request->filled('hour')) {
            $query->whereHour('created_at', $request->hour);
        }
        if ($request->filled('commune_id')) {
            $query->byCommune($request->commune_id);
        } elseif ($request->filled('departement_id')) {
            $query->whereHas('commune', function($q) use ($request) {
                $q->where('departement_id', $request->departement_id);
            });
        } elseif ($request->filled('region_id')) {
            $query->whereHas('commune.departement', function($q) use ($request) {
                $q->where('region_id', $request->region_id);
            });
        }

        $accidents = $query->orderByDesc('date')->get();

        $pdf = Pdf::loadView('exports.accidents', [
            'accidents' => $accidents,
            'titre' => 'Rapport des Accidents de Circulation',
            'date_generation' => now()->format('d/m/Y H:i'),
        ]);

        return $pdf->download('accidents_' . now()->format('Y-m-d') . '.pdf');
    }

    /**
     * @OA\Get(
     *     path="/api/export/infractions/csv",
     *     tags={"Export"},
     *     summary="Export CSV des infractions (compatible Excel)",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="annee", in="query", required=false, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Fichier CSV téléchargé", @OA\MediaType(mediaType="text/csv"))
     * )
     */
    public function infractionsCsv(Request $request)
    {
        $query = Infraction::with(['typeInfraction', 'service', 'commune'])->visibleByUser();

        if ($request->filled('annee')) $query->byAnnee($request->annee);
        if ($request->filled('service_id')) $query->byService($request->service_id);
        if ($request->filled('date_from') || $request->filled('date_to')) {
            if ($request->filled('date_from') && $request->filled('date_to')) {
                $query->byDateRange($request->date_from, $request->date_to);
            } elseif ($request->filled('date_from')) {
                $query->where('date', '>=', $request->date_from);
            } else {
                $query->where('date', '<=', $request->date_to);
            }
        }
        if ($request->filled('hour')) {
            $query->whereHour('created_at', $request->hour);
        }
        if ($request->filled('commune_id')) {
            $query->byCommune($request->commune_id);
        } elseif ($request->filled('departement_id')) {
            $query->whereHas('commune', function($q) use ($request) {
                $q->where('departement_id', $request->departement_id);
            });
        } elseif ($request->filled('region_id')) {
            $query->whereHas('commune.departement', function($q) use ($request) {
                $q->where('region_id', $request->region_id);
            });
        }

        $infractions = $query->orderByDesc('date')->get();

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="infractions_' . now()->format('Y-m-d') . '.csv"',
        ];

        $callback = function () use ($infractions) {
            $file = fopen('php://output', 'w');
            // En-tête BOM pour UTF-8 dans Excel
            fprintf($file, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($file, ['ID', 'Date', 'Lieu', 'Commune', 'Service', 'Type', 'Issue', 'Description'], ';');

            foreach ($infractions as $infraction) {
                fputcsv($file, [
                    $infraction->id,
                    $infraction->date->format('d/m/Y'),
                    $infraction->lieu,
                    $infraction->commune->nom ?? '',
                    $infraction->service->nom ?? '',
                    $infraction->typeInfraction->nom ?? '',
                    $infraction->issue,
                    $infraction->description,
                ], ';');
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    // Export CSV des accidents
    public function accidentsCsv(Request $request)
    {
        $query = Accident::with(['service', 'commune'])->visibleByUser();

        if ($request->filled('type')) $query->byType($request->type);
        if ($request->filled('date_from') || $request->filled('date_to')) {
            if ($request->filled('date_from') && $request->filled('date_to')) {
                $query->byDateRange($request->date_from, $request->date_to);
            } elseif ($request->filled('date_from')) {
                $query->where('date', '>=', $request->date_from);
            } else {
                $query->where('date', '<=', $request->date_to);
            }
        }
        if ($request->filled('hour')) {
            $query->whereHour('created_at', $request->hour);
        }
        if ($request->filled('commune_id')) {
            $query->byCommune($request->commune_id);
        } elseif ($request->filled('departement_id')) {
            $query->whereHas('commune', function($q) use ($request) {
                $q->where('departement_id', $request->departement_id);
            });
        } elseif ($request->filled('region_id')) {
            $query->whereHas('commune.departement', function($q) use ($request) {
                $q->where('region_id', $request->region_id);
            });
        }

        $accidents = $query->orderByDesc('date')->get();

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="accidents_' . now()->format('Y-m-d') . '.csv"',
        ];

        $callback = function () use ($accidents) {
            $file = fopen('php://output', 'w');
            fprintf($file, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($file, ['ID', 'Date', 'Type', 'Lieu', 'Commune', 'Service', 'Moyen', 'Cause'], ';');

            foreach ($accidents as $accident) {
                fputcsv($file, [
                    $accident->id,
                    $accident->date->format('d/m/Y'),
                    $accident->type,
                    $accident->lieu,
                    $accident->commune->nom ?? '',
                    $accident->service->nom ?? '',
                    $accident->moyen,
                    $accident->cause_probable,
                ], ';');
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * @OA\Post(
     *     path="/api/export/import/json",
     *     tags={"Export"},
     *     summary="Importer des données depuis un fichier JSON",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(mediaType="multipart/form-data",
     *             @OA\Schema(@OA\Property(property="file", type="string", format="binary", description="Fichier JSON max 10MB"))
     *         )
     *     ),
     *     @OA\Response(response=200, description="Import réussi", @OA\JsonContent(ref="#/components/schemas/SuccessResponse")),
     *     @OA\Response(response=422, description="Fichier invalide", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
     * )
     */
    public function importJson(Request $request): JsonResponse
    {
        $request->validate(['file' => 'required|file|mimes:json|max:10240']);

        $content = json_decode(file_get_contents($request->file('file')->getRealPath()), true);

        if (!$content) {
            return $this->errorResponse('Fichier JSON invalide.', 422);
        }

        $imported = 0;
        DB::beginTransaction();
        try {
            if (isset($content['infractions'])) {
                foreach ($content['infractions'] as $data) {
                    $data['user_id'] = auth()->id();
                    Infraction::create($data);
                    $imported++;
                }
            }
            DB::commit();
            return $this->successResponse(['imported' => $imported], "{$imported} enregistrements importés.");
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Erreur lors de l\'import: ' . $e->getMessage(), 500);
        }
    }
}
