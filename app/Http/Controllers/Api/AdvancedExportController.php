<?php

namespace App\Http\Controllers\Api;

use App\Models\Accident;
use App\Models\Infraction;
use App\Models\ImmigrationClandestine;
use App\Models\AuditLog;
use App\Services\Export\DateFilterService;
use App\Services\Export\PDFExportService;
use App\Services\Export\ExcelExportService;
use App\Services\Export\WordExportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdvancedExportController extends ApiController
{
    public function __construct(
        private DateFilterService  $dateFilter,
        private PDFExportService   $pdfService,
        private ExcelExportService $excelService,
        private WordExportService  $wordService,
    ) {}

    // POST /api/accidents/export
    public function accidents(Request $request)
    {
        $request->validate([
            'format'     => 'required|in:pdf,word,excel',
            'periodType' => 'required|string',
            'month'      => 'nullable|integer|min:1|max:12',
            'year'       => 'nullable|integer|min:2000|max:2100',
            'start_date' => 'nullable|date',
            'end_date'   => 'nullable|date|after_or_equal:start_date',
        ]);
        if (!$this->checkExportPermission($request->format)) {
            return $this->errorResponse('Permission insuffisante pour ce format d\'export.', 403);
        }

        [$from, $to] = $this->dateFilter->resolve($request->periodType, $request->all());
        $periodLabel  = $this->dateFilter->label($request->periodType, $request->all());

        $query = Accident::with(['service', 'commune', 'victimes'])->visibleByUser();
        $this->applyDateRange($query, $from, $to);
        $accidents = $query->orderByDesc('date')->orderByDesc('created_at')->get();

        $this->logExport('accidents', $request->format, $periodLabel, $accidents->count());

        return match ($request->format) {
            'pdf'   => $this->pdfService->generate('exports.advanced.accidents', [
                'records'        => $accidents,
                'titre'          => 'Rapport des Accidents de Circulation',
                'date_generation'=> now()->format('d/m/Y H:i'),
                'agent'          => Auth::user()?->name ?? 'Inconnu',
                'period_label'   => $periodLabel,
            ], 'accidents'),

            'excel' => $this->excelService->download(
                'Rapport des Accidents de Circulation',
                $periodLabel,
                Auth::user()?->name ?? 'Inconnu',
                ['#', 'Date', 'Heure', 'Type', 'Lieu', 'Commune', 'Service', 'Moyen', 'Cause', 'Victimes'],
                $accidents->map(fn($a, $i) => [
                    $i + 1,
                    $a->date?->format('d/m/Y') ?? '-',
                    $a->heure ?? '-',
                    $a->type ?? '-',
                    $a->lieu ?? '-',
                    $a->commune->nom ?? '-',
                    $a->service->nom ?? '-',
                    $a->moyen ?? '-',
                    $a->cause_probable ?? '-',
                    $a->victimes->count(),
                ])->values()->all(),
                ['TOTAUX', '', '', '', '', '', '', '', '', $accidents->sum(fn($a) => $a->victimes->count())],
                'accidents'
            ),

            'word' => $this->wordService->download(
                'Rapport des Accidents de Circulation',
                $periodLabel,
                Auth::user()?->name ?? 'Inconnu',
                ['#', 'Date', 'Heure', 'Type', 'Lieu', 'Commune', 'Service', 'Moyen', 'Cause', 'Victimes'],
                $accidents->map(fn($a, $i) => [
                    $i + 1,
                    $a->date?->format('d/m/Y') ?? '-',
                    $a->heure ?? '-',
                    $a->type ?? '-',
                    $a->lieu ?? '-',
                    $a->commune->nom ?? '-',
                    $a->service->nom ?? '-',
                    $a->moyen ?? '-',
                    $a->cause_probable ?? '-',
                    $a->victimes->count(),
                ])->values()->all(),
                'accidents'
            ),
        };
    }

    // POST /api/infractions/export
    public function infractions(Request $request)
    {
        $request->validate([
            'format'     => 'required|in:pdf,word,excel',
            'periodType' => 'required|string',
            'month'      => 'nullable|integer|min:1|max:12',
            'year'       => 'nullable|integer|min:2000|max:2100',
            'start_date' => 'nullable|date',
            'end_date'   => 'nullable|date|after_or_equal:start_date',
        ]);
        if (!$this->checkExportPermission($request->format)) {
            return $this->errorResponse('Permission insuffisante pour ce format d\'export.', 403);
        }

        [$from, $to] = $this->dateFilter->resolve($request->periodType, $request->all());
        $periodLabel  = $this->dateFilter->label($request->periodType, $request->all());

        $query = Infraction::with(['typeInfraction.categorieInfraction', 'service', 'commune'])->visibleByUser();
        $this->applyDateRange($query, $from, $to);
        $infractions = $query->orderByDesc('date')->orderByDesc('created_at')->get();

        $this->logExport('infractions', $request->format, $periodLabel, $infractions->count());

        return match ($request->format) {
            'pdf'   => $this->pdfService->generate('exports.advanced.infractions', [
                'records'        => $infractions,
                'titre'          => 'Rapport des Infractions',
                'date_generation'=> now()->format('d/m/Y H:i'),
                'agent'          => Auth::user()?->name ?? 'Inconnu',
                'period_label'   => $periodLabel,
            ], 'infractions'),

            'excel' => $this->excelService->download(
                'Rapport des Infractions',
                $periodLabel,
                Auth::user()?->name ?? 'Inconnu',
                ['#', 'Date', 'Heure', 'Lieu', 'Commune', 'Service', 'Type', 'Catégorie', 'Issue', 'Description'],
                $infractions->map(fn($inf, $i) => [
                    $i + 1,
                    $inf->date?->format('d/m/Y') ?? ($inf->annee ?? '-'),
                    $inf->heure ?? '-',
                    $inf->lieu ?? '-',
                    $inf->commune->nom ?? '-',
                    $inf->service->nom ?? '-',
                    $inf->typeInfraction->nom ?? '-',
                    $inf->typeInfraction->categorieInfraction->nom ?? '-',
                    $inf->issue ?? '-',
                    $inf->description ?? '-',
                ])->values()->all(),
                ['TOTAUX', '', '', '', '', '', '', '', '', $infractions->count()],
                'infractions'
            ),

            'word' => $this->wordService->download(
                'Rapport des Infractions',
                $periodLabel,
                Auth::user()?->name ?? 'Inconnu',
                ['#', 'Date', 'Heure', 'Lieu', 'Commune', 'Service', 'Type', 'Catégorie', 'Issue'],
                $infractions->map(fn($inf, $i) => [
                    $i + 1,
                    $inf->date?->format('d/m/Y') ?? ($inf->annee ?? '-'),
                    $inf->heure ?? '-',
                    $inf->lieu ?? '-',
                    $inf->commune->nom ?? '-',
                    $inf->service->nom ?? '-',
                    $inf->typeInfraction->nom ?? '-',
                    $inf->typeInfraction->categorieInfraction->nom ?? '-',
                    $inf->issue ?? '-',
                ])->values()->all(),
                'infractions'
            ),
        };
    }

    // POST /api/immigrations/export
    public function immigrations(Request $request)
    {
        $request->validate([
            'format'     => 'required|in:pdf,word,excel',
            'periodType' => 'required|string',
            'month'      => 'nullable|integer|min:1|max:12',
            'year'       => 'nullable|integer|min:2000|max:2100',
            'start_date' => 'nullable|date',
            'end_date'   => 'nullable|date|after_or_equal:start_date',
        ]);
        if (!$this->checkExportPermission($request->format)) {
            return $this->errorResponse('Permission insuffisante pour ce format d\'export.', 403);
        }

        [$from, $to] = $this->dateFilter->resolve($request->periodType, $request->all());
        $periodLabel  = $this->dateFilter->label($request->periodType, $request->all());

        $query = ImmigrationClandestine::with(['service', 'user'])->visibleByUser();
        $this->applyDateRange($query, $from, $to);
        $records = $query->orderByDesc('date')->orderByDesc('created_at')->get();

        $this->logExport('immigrations', $request->format, $periodLabel, $records->count());

        return match ($request->format) {
            'pdf'   => $this->pdfService->generate('exports.advanced.immigrations', [
                'records'        => $records,
                'titre'          => 'Rapport des Immigrations Clandestines',
                'date_generation'=> now()->format('d/m/Y H:i'),
                'agent'          => Auth::user()?->name ?? 'Inconnu',
                'period_label'   => $periodLabel,
            ], 'immigrations'),

            'excel' => $this->excelService->download(
                'Rapport des Immigrations Clandestines',
                $periodLabel,
                Auth::user()?->name ?? 'Inconnu',
                ['#', 'Date', 'Service', 'Total', 'Hommes', 'Femmes', 'Enfants', 'Sénégalais', 'Étrangers', 'Zone départ', 'Zone arrivée'],
                $records->map(fn($r, $i) => [
                    $i + 1,
                    $r->date?->format('d/m/Y') ?? '-',
                    $r->service->nom ?? '-',
                    $r->nombre_interpellation ?? 0,
                    $r->nombre_hommes ?? 0,
                    $r->nombre_femmes ?? 0,
                    $r->nombre_enfants ?? 0,
                    $r->nombre_senegalais ?? 0,
                    $r->nombre_etrangers ?? 0,
                    $r->zone_depart ?? '-',
                    $r->zone_arrivee_prevue ?? '-',
                ])->values()->all(),
                ['TOTAUX', '', '', $records->sum('nombre_interpellation'), $records->sum('nombre_hommes'), $records->sum('nombre_femmes'), $records->sum('nombre_enfants'), $records->sum('nombre_senegalais'), $records->sum('nombre_etrangers'), '', ''],
                'immigrations'
            ),

            'word' => $this->wordService->download(
                'Rapport des Immigrations Clandestines',
                $periodLabel,
                Auth::user()?->name ?? 'Inconnu',
                ['#', 'Date', 'Service', 'Total', 'Hommes', 'Femmes', 'Enfants', 'Zone départ', 'Zone arrivée'],
                $records->map(fn($r, $i) => [
                    $i + 1,
                    $r->date?->format('d/m/Y') ?? '-',
                    $r->service->nom ?? '-',
                    $r->nombre_interpellation ?? '-',
                    $r->nombre_hommes ?? 0,
                    $r->nombre_femmes ?? 0,
                    $r->nombre_enfants ?? 0,
                    $r->zone_depart ?? '-',
                    $r->zone_arrivee_prevue ?? '-',
                ])->values()->all(),
                'immigrations'
            ),
        };
    }

    private function checkExportPermission(string $format): bool
    {
        $user = Auth::user();
        return match ($format) {
            'pdf'   => $user->can('export.pdf'),
            'word'  => $user->can('export.pdf'),
            'excel' => $user->can('export.csv'),
            default => false,
        };
    }

    private function applyDateRange($query, ?string $from, ?string $to): void
    {
        if (!$from && !$to) return;

        $query->where(function ($q) use ($from, $to) {
            if ($from && $to) {
                $q->whereBetween('date', [$from, $to])
                  ->orWhere(fn($q2) => $q2->whereNull('date')
                      ->whereDate('created_at', '>=', $from)
                      ->whereDate('created_at', '<=', $to));
            } elseif ($from) {
                $q->where('date', '>=', $from)
                  ->orWhere(fn($q2) => $q2->whereNull('date')->whereDate('created_at', '>=', $from));
            } else {
                $q->where('date', '<=', $to)
                  ->orWhere(fn($q2) => $q2->whereNull('date')->whereDate('created_at', '<=', $to));
            }
        });
    }

    private function logExport(string $module, string $format, string $period, int $count): void
    {
        AuditLog::create([
            'user_id'    => Auth::id(),
            'action'     => 'export',
            'model_type' => $module,
            'model_id'   => null,
            'new_values' => [
                'format'  => $format,
                'period'  => $period,
                'count'   => $count,
            ],
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }
}
