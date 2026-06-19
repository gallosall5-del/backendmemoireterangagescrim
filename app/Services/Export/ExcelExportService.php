<?php

namespace App\Services\Export;

use Illuminate\Http\Response;

class ExcelExportService
{
    /**
     * Génère un fichier CSV compatible Excel (séparateur ;, BOM UTF-8).
     * Construit le contenu en mémoire pour éviter la corruption par les middleware Laravel.
     *
     * @param array  $headers  En-têtes de colonnes
     * @param array  $rows     Tableau de tableaux de valeurs
     * @param string $filename Nom de fichier sans extension
     */
    public function download(array $headers, array $rows, string $filename): Response
    {
        // Construire le CSV via php://temp (en mémoire, pas php://output)
        $fp = fopen('php://temp', 'r+');
        fputcsv($fp, $headers, ';');
        foreach ($rows as $row) {
            fputcsv($fp, array_map(fn($v) => (string) ($v ?? ''), $row), ';');
        }
        rewind($fp);
        $csv = stream_get_contents($fp);
        fclose($fp);

        // BOM UTF-8 + contenu CSV
        $content = chr(0xEF) . chr(0xBB) . chr(0xBF) . $csv;
        $fullName = $filename . '_' . now()->format('Y-m-d') . '.csv';

        return response($content, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $fullName . '"',
            'Content-Length'      => strlen($content),
            'Cache-Control'       => 'no-cache, no-store, must-revalidate',
            'Pragma'              => 'no-cache',
        ]);
    }
}
