<?php

namespace App\Http\Controllers\Api;

use App\Models\Infraction;
use App\Models\Accident;
use App\Models\Personnel;
use App\Models\Victime;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Recherche globale multi-entités.
 * GET /api/search?q=terme&types[]=infractions&limit=5
 */
class SearchController extends ApiController
{
    private const DEFAULT_LIMIT = 5;
    private const MAX_LIMIT     = 20;

    public function search(Request $request): JsonResponse
    {
        $q     = trim($request->get('q', ''));
        $types = $request->get('types', ['infractions', 'accidents', 'personnel', 'victimes']);
        $limit = min((int) $request->get('limit', self::DEFAULT_LIMIT), self::MAX_LIMIT);

        if (strlen($q) < 2) {
            return $this->errorResponse('La recherche doit contenir au moins 2 caractères.', 422);
        }

        $results = [];

        if (in_array('infractions', $types)) {
            $items = Infraction::visibleByUser()
                ->with(['typeInfraction', 'commune', 'service'])
                ->where(fn($q2) => $q2
                    ->where('lieu', 'ILIKE', "%{$q}%")
                    ->orWhere('description', 'ILIKE', "%{$q}%")
                    ->orWhere('id', is_numeric($q) ? (int)$q : -1)
                )
                ->limit($limit)
                ->get()
                ->map(fn($i) => [
                    'type'    => 'infraction',
                    'id'      => $i->id,
                    'label'   => "Infraction #{$i->id} — " . ($i->typeInfraction->nom ?? 'N/A'),
                    'sub'     => $i->lieu . ($i->commune ? ' · ' . $i->commune->nom : ''),
                    'date'    => $i->date?->format('d/m/Y'),
                    'badge'   => $i->issue,
                    'url'     => '/infractions',
                ]);
            $results['infractions'] = $items;
        }

        if (in_array('accidents', $types)) {
            $items = Accident::visibleByUser()
                ->with(['commune', 'service'])
                ->where(fn($q2) => $q2
                    ->where('lieu', 'ILIKE', "%{$q}%")
                    ->orWhere('cause_probable', 'ILIKE', "%{$q}%")
                    ->orWhere('description', 'ILIKE', "%{$q}%")
                    ->orWhere('id', is_numeric($q) ? (int)$q : -1)
                )
                ->limit($limit)
                ->get()
                ->map(fn($a) => [
                    'type'  => 'accident',
                    'id'    => $a->id,
                    'label' => "Accident #{$a->id} — " . ucfirst($a->type),
                    'sub'   => $a->lieu . ($a->commune ? ' · ' . $a->commune->nom : ''),
                    'date'  => $a->date?->format('d/m/Y'),
                    'badge' => $a->type,
                    'url'   => '/accidents',
                ]);
            $results['accidents'] = $items;
        }

        if (in_array('personnel', $types)) {
            $items = Personnel::visibleByUser()
                ->with(['service'])
                ->where(fn($q2) => $q2
                    ->where('nom', 'ILIKE', "%{$q}%")
                    ->orWhere('prenom', 'ILIKE', "%{$q}%")
                    ->orWhere('ccap', 'ILIKE', "%{$q}%")
                    ->orWhere('grade', 'ILIKE', "%{$q}%")
                )
                ->limit($limit)
                ->get()
                ->map(fn($p) => [
                    'type'  => 'personnel',
                    'id'    => $p->id,
                    'label' => "{$p->prenom} {$p->nom} ({$p->grade})",
                    'sub'   => $p->service->nom ?? 'N/A',
                    'badge' => $p->statut,
                    'url'   => '/personnel',
                ]);
            $results['personnel'] = $items;
        }

        if (in_array('victimes', $types)) {
            $items = Victime::with(['infraction', 'accident'])
                ->where(fn($q2) => $q2
                    ->where('nom', 'ILIKE', "%{$q}%")
                    ->orWhere('prenom', 'ILIKE', "%{$q}%")
                    ->orWhere('no_cin_passeport', 'ILIKE', "%{$q}%")
                )
                ->limit($limit)
                ->get()
                ->map(fn($v) => [
                    'type'  => 'victime',
                    'id'    => $v->id,
                    'label' => "{$v->prenom} {$v->nom}",
                    'sub'   => $v->no_cin_passeport ?? 'CIN non renseigné',
                    'badge' => $v->nationalite,
                    'url'   => '/victimes',
                ]);
            $results['victimes'] = $items;
        }

        $total = collect($results)->flatten(1)->count();

        return $this->successResponse([
            'query'   => $q,
            'total'   => $total,
            'results' => $results,
        ]);
    }
}
