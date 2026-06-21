<?php

namespace App\Services\Export;

use Illuminate\Http\Response;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

class ExcelExportService
{
    // Palette issue des fichiers de référence DSP/ANASER
    private const COLOR_HEADER_BG   = '1B4332'; // vert DSP
    private const COLOR_HEADER_FG   = 'FFFFFF';
    private const COLOR_SUBHEADER   = 'D9D9D9'; // gris catégorie (ANASER)
    private const COLOR_DATA_BG     = 'FFFF00'; // jaune données (ANASER)
    private const COLOR_DATA_ALT    = 'FFFFFF'; // blanc lignes paires
    private const COLOR_TOTAL_BG    = 'C6F6D5'; // vert clair ligne total
    private const COLOR_TOTAL_FG    = '1B4332';
    private const COLOR_TITLE_FG    = '1B4332';
    private const COLOR_META_BG     = 'ECFDF5'; // vert très clair (meta)
    private const COLOR_BORDER      = 'CCCCCC';

    /**
     * Génère un fichier .xlsx stylé (format institutionnel DSP Sénégal).
     *
     * @param string $title    Titre du rapport
     * @param string $subtitle Sous-titre (module + période)
     * @param string $agent    Nom de l'agent
     * @param array  $headers  En-têtes de colonnes
     * @param array  $rows     Données
     * @param array  $totals   Ligne de totaux optionnelle (même longueur que $headers)
     * @param string $filename Nom de fichier sans extension
     */
    public function download(
        string $title,
        string $subtitle,
        string $agent,
        array  $headers,
        array  $rows,
        array  $totals = [],
        string $filename = 'rapport'
    ): Response {
        $spreadsheet = new Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Rapport');

        $colCount  = count($headers);
        $lastColLetter = Coordinate::stringFromColumnIndex($colCount);

        // ── Ligne 1 : Entête institutionnel ────────────────────────────────
        $sheet->mergeCells("A1:{$lastColLetter}1");
        $sheet->setCellValue('A1', 'DIRECTION DE LA SÉCURITÉ PUBLIQUE — SÉNÉGAL (TERANGA GESCRIM)');
        $this->styleRange($sheet, "A1:{$lastColLetter}1", [
            'font'      => ['bold' => true, 'size' => 13, 'color' => ['argb' => 'FF' . self::COLOR_TITLE_FG]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFECFDF5']],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(22);

        // ── Ligne 2 : Titre du rapport ─────────────────────────────────────
        $sheet->mergeCells("A2:{$lastColLetter}2");
        $sheet->setCellValue('A2', strtoupper($title));
        $this->styleRange($sheet, "A2:{$lastColLetter}2", [
            'font'      => ['bold' => true, 'size' => 12, 'color' => ['argb' => 'FF' . self::COLOR_TITLE_FG]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFECFDF5']],
        ]);
        $sheet->getRowDimension(2)->setRowHeight(18);

        // ── Ligne 3 : Période ──────────────────────────────────────────────
        $sheet->mergeCells("A3:{$lastColLetter}3");
        $sheet->setCellValue('A3', "Période : {$subtitle}     |     Généré le : " . now()->format('d/m/Y H:i') . "     |     Agent : {$agent}");
        $this->styleRange($sheet, "A3:{$lastColLetter}3", [
            'font'      => ['size' => 9, 'color' => ['argb' => 'FF555555']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFECFDF5']],
        ]);
        $sheet->getRowDimension(3)->setRowHeight(14);

        // ── Ligne 4 : En-tête de colonnes ─────────────────────────────────
        $headerRow = 4;
        foreach ($headers as $col => $header) {
            $letter = Coordinate::stringFromColumnIndex($col + 1);
            $sheet->setCellValue("{$letter}{$headerRow}", $header);
        }
        $this->styleRange($sheet, "A{$headerRow}:{$lastColLetter}{$headerRow}", [
            'font'      => ['bold' => true, 'size' => 9, 'color' => ['argb' => 'FF' . self::COLOR_HEADER_FG]],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF' . self::COLOR_HEADER_BG]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FF' . self::COLOR_BORDER]]],
        ]);
        $sheet->getRowDimension($headerRow)->setRowHeight(16);

        // ── Lignes de données ──────────────────────────────────────────────
        foreach ($rows as $ri => $row) {
            $currentRow = $headerRow + 1 + $ri;
            // Alterner : lignes paires en blanc, lignes impaires en jaune (référence ANASER)
            $bgArgb = ($ri % 2 === 0) ? ('FF' . self::COLOR_DATA_BG) : ('FF' . self::COLOR_DATA_ALT);

            foreach ($row as $col => $value) {
                $letter = Coordinate::stringFromColumnIndex($col + 1);
                $cell   = $sheet->getCell("{$letter}{$currentRow}");

                // Détecter si c'est la colonne # (index 0) pour centrer
                if ($col === 0) {
                    $cell->setValue($value);
                    $sheet->getStyle("{$letter}{$currentRow}")->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_CENTER);
                } else {
                    $cell->setValue($value);
                }
            }

            $this->styleRange($sheet, "A{$currentRow}:{$lastColLetter}{$currentRow}", [
                'font'    => ['size' => 9],
                'fill'    => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $bgArgb]],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFDDDDDD']]],
                'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
            ]);
            $sheet->getRowDimension($currentRow)->setRowHeight(14);
        }

        // ── Ligne de totaux ────────────────────────────────────────────────
        if (!empty($totals)) {
            $totalRow = $headerRow + 1 + count($rows);
            foreach ($totals as $col => $value) {
                $letter = Coordinate::stringFromColumnIndex($col + 1);
                $sheet->setCellValue("{$letter}{$totalRow}", $value);
            }
            $this->styleRange($sheet, "A{$totalRow}:{$lastColLetter}{$totalRow}", [
                'font'    => ['bold' => true, 'size' => 9, 'color' => ['argb' => 'FF' . self::COLOR_TOTAL_FG]],
                'fill'    => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF' . self::COLOR_TOTAL_BG]],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FF' . self::COLOR_BORDER]]],
                'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
            ]);
            $sheet->getRowDimension($totalRow)->setRowHeight(15);
        }

        // ── Ajustement automatique de la largeur des colonnes ──────────────
        foreach (range(1, $colCount) as $colIndex) {
            $letter = Coordinate::stringFromColumnIndex($colIndex);
            $sheet->getColumnDimension($letter)->setAutoSize(true);
        }

        // ── Figer les volets (en-tête + 3 lignes méta) ────────────────────
        $sheet->freezePane("A{$headerRow}");

        // ── Écrire en mémoire ──────────────────────────────────────────────
        $tmpPath = tempnam(sys_get_temp_dir(), 'gescrim_xlsx_');
        $writer  = new Xlsx($spreadsheet);
        $writer->save($tmpPath);
        $content  = file_get_contents($tmpPath);
        unlink($tmpPath);

        $fullName = $filename . '_' . now()->format('Y-m-d') . '.xlsx';

        return response($content, 200, [
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $fullName . '"',
            'Content-Length'      => strlen($content),
            'Cache-Control'       => 'no-cache, no-store, must-revalidate',
            'Pragma'              => 'no-cache',
        ]);
    }

    private function styleRange(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, string $range, array $styles): void
    {
        $style = $sheet->getStyle($range);
        if (isset($styles['font']))      $style->getFont()->applyFromArray($styles['font']);
        if (isset($styles['alignment'])) $style->getAlignment()->applyFromArray($styles['alignment']);
        if (isset($styles['borders']))   $style->getBorders()->applyFromArray($styles['borders']);
        if (isset($styles['fill']))      $style->getFill()->applyFromArray($styles['fill']);
    }
}
