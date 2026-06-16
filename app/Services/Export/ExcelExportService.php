<?php

namespace App\Services\Export;

use Illuminate\Database\Eloquent\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExcelExportService
{
    /**
     * Génère un fichier CSV compatible Excel (séparateur ;, BOM UTF-8, dates formatées).
     *
     * @param array  $headers  En-têtes de colonnes
     * @param array  $rows     Tableau de tableaux de valeurs
     * @param string $filename Nom de fichier sans extension
     */
    public function download(array $headers, array $rows, string $filename): StreamedResponse
    {
        $fullName = $filename . '_' . now()->format('Y-m-d') . '.csv';

        $responseHeaders = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $fullName . '"',
            'Cache-Control'       => 'no-cache, no-store',
        ];

        return response()->stream(function () use ($headers, $rows) {
            $handle = fopen('php://output', 'w');
            // BOM UTF-8 pour compatibilité Excel
            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($handle, $headers, ';');
            foreach ($rows as $row) {
                fputcsv($handle, $row, ';');
            }
            fclose($handle);
        }, 200, $responseHeaders);
    }
}
