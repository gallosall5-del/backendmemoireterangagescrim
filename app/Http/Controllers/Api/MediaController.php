<?php

namespace App\Http\Controllers\Api;

use App\Models\Media;
use App\Models\Infraction;
use App\Models\Accident;
use App\Models\Personnel;
use App\Models\Victime;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Gestion des médias (photos, documents PDF, scans).
 * Relation polymorphique vers Infraction, Accident, Personnel, Victime.
 */
class MediaController extends ApiController
{
    private const ALLOWED_TYPES = [
        'image/jpeg', 'image/png', 'image/gif', 'image/webp',
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ];

    private const MODEL_MAP = [
        'infractions' => Infraction::class,
        'accidents'   => Accident::class,
        'personnels'  => Personnel::class,
        'victimes'    => Victime::class,
    ];

    /**
     * Lister les médias d'une entité.
     * GET /api/{type}/{id}/media
     */
    public function index(Request $request, string $type, int $id): JsonResponse
    {
        $model = $this->resolveModel($type, $id);
        if (!$model) {
            return $this->errorResponse('Entité introuvable.', 404);
        }

        $scopeService = app(\App\Services\ScopeAccessService::class);
        if (!$scopeService->canRead(auth()->user(), $model)) {
            return $this->errorResponse('Accès territorial refusé.', 403);
        }

        $media = Media::where('mediable_type', get_class($model))
            ->where('mediable_id', $id)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn($m) => $this->formatMedia($m));

        return $this->successResponse($media);
    }

    /**
     * Uploader un ou plusieurs fichiers.
     * POST /api/{type}/{id}/media
     */
    public function store(Request $request, string $type, int $id): JsonResponse
    {
        $request->validate([
            'files'   => 'required|array|min:1|max:10',
            'files.*' => 'required|file|max:10240', // 10 MB max par fichier
        ]);

        $model = $this->resolveModel($type, $id);
        if (!$model) {
            return $this->errorResponse('Entité introuvable.', 404);
        }

        $scopeService = app(\App\Services\ScopeAccessService::class);
        if (!$scopeService->canWrite(auth()->user(), $model)) {
            return $this->errorResponse('Accès territorial refusé.', 403);
        }

        $uploaded = [];
        foreach ($request->file('files') as $file) {
            // Validation MIME via magic bytes (finfo) — résistante au spoofing d'extension.
            // getMimeType() de Symfony utilise déjà finfo en interne sur les UploadedFile,
            // mais on re-vérifie explicitement depuis le contenu brut du fichier temporaire.
            $realMime = (new \finfo(FILEINFO_MIME_TYPE))->file($file->getRealPath());
            if (!in_array($realMime, self::ALLOWED_TYPES)) {
                continue;
            }

            $folder   = "media/{$type}/{$id}";
            $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
            $path     = $file->storeAs($folder, $filename, 'public');

            $media = Media::create([
                'mediable_type' => get_class($model),
                'mediable_id'   => $id,
                'filename'      => $file->getClientOriginalName(),
                'path'          => $path,
                'mime_type'     => $realMime,
                'size'          => $file->getSize(),
            ]);

            $uploaded[] = $this->formatMedia($media);
        }

        return $this->successResponse($uploaded, count($uploaded) . ' fichier(s) uploadé(s).', 201);
    }

    /**
     * Télécharger un média.
     * GET /api/media/{id}/download
     */
    public function download(int $id)
    {
        $media = Media::findOrFail($id);

        $parent = $media->mediable;
        if ($parent) {
            $scopeService = app(\App\Services\ScopeAccessService::class);
            if (!$scopeService->canRead(auth()->user(), $parent)) {
                return $this->errorResponse('Accès territorial refusé.', 403);
            }
        }

        if (!Storage::disk('public')->exists($media->path)) {
            return $this->errorResponse('Fichier introuvable sur le serveur.', 404);
        }

        return Storage::disk('public')->download($media->path, $media->filename);
    }

    /**
     * Supprimer un média.
     * DELETE /api/media/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        $media = Media::find($id);
        if (!$media) {
            return $this->errorResponse('Média introuvable.', 404);
        }

        $parent = $media->mediable;
        if ($parent) {
            $scopeService = app(\App\Services\ScopeAccessService::class);
            if (!$scopeService->canWrite(auth()->user(), $parent)) {
                return $this->errorResponse('Accès territorial refusé.', 403);
            }
        }

        Storage::disk('public')->delete($media->path);
        $media->delete();

        return $this->successResponse(null, 'Média supprimé.');
    }

    // ─── Helpers ────────────────────────────────────────────────────────────────

    private function resolveModel(string $type, int $id): ?object
    {
        $class = self::MODEL_MAP[$type] ?? null;
        if (!$class) return null;
        return $class::find($id);
    }

    private function formatMedia(Media $m): array
    {
        return [
            'id'        => $m->id,
            'filename'  => $m->filename,
            'mime_type' => $m->mime_type,
            'size'      => $m->size,
            'size_human'=> $this->humanSize($m->size),
            'url'       => Storage::disk('public')->url($m->path),
            'is_image'  => str_starts_with($m->mime_type, 'image/'),
            'created_at'=> $m->created_at?->toISOString(),
        ];
    }

    private function humanSize(int $bytes): string
    {
        if ($bytes >= 1048576) return round($bytes / 1048576, 1) . ' Mo';
        if ($bytes >= 1024)    return round($bytes / 1024, 1) . ' Ko';
        return $bytes . ' o';
    }
}
