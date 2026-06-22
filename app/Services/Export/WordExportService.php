<?php

namespace App\Services\Export;

use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\SimpleType\TblWidth;
use Illuminate\Http\Response;

class WordExportService
{
    // Palette DSP/ANASER
    private const COLOR_HEADER_BG  = '1B4332'; // vert DSP
    private const COLOR_HEADER_FG  = 'FFFFFF';
    private const COLOR_SUBCAT_BG  = 'D9D9D9'; // gris catégorie ANASER
    private const COLOR_DATA_ODD   = 'FFFF00'; // jaune données ANASER
    private const COLOR_DATA_EVEN  = 'FFFFFF';
    private const COLOR_TOTAL_BG   = 'C6F6D5'; // vert clair
    private const COLOR_TOTAL_FG   = '1B4332';
    private const COLOR_META_BG    = 'ECFDF5';
    private const COLOR_TITLE_FG   = '1B4332';

    /**
     * Génère un fichier .docx au format institutionnel DSP Sénégal.
     */
    public function download(
        string $title,
        string $subtitle,
        string $agent,
        array  $headers,
        array  $rows,
        string $filename
    ): Response {
        $word = new PhpWord();
        $word->setDefaultFontName('Arial');
        $word->setDefaultFontSize(10);

        $section = $word->addSection([
            'marginTop'    => 600,
            'marginBottom' => 600,
            'marginLeft'   => 800,
            'marginRight'  => 800,
            'orientation'  => \PhpOffice\PhpWord\Style\Section::ORIENTATION_LANDSCAPE,
        ]);

        // ── Bannière institutionnelle ──────────────────────────────────────
        $section->addText(
            'RÉPUBLIQUE DU SÉNÉGAL — MINISTÈRE DE L\'INTÉRIEUR',
            ['bold' => false, 'size' => 8, 'color' => '4a5568'],
            ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER]
        );
        $section->addText(
            'DIRECTION DE LA SÉCURITÉ PUBLIQUE — TERANGA GESCRIM',
            ['bold' => true, 'size' => 13, 'color' => self::COLOR_TITLE_FG],
            ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER]
        );
        $section->addText(
            strtoupper($title),
            ['bold' => true, 'size' => 12, 'color' => '2d3748'],
            ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER]
        );
        $section->addText(
            'Période : ' . $subtitle . '   |   Généré le : ' . now()->format('d/m/Y H:i') . '   |   Agent : ' . $agent,
            ['size' => 9, 'color' => '555555'],
            ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER]
        );
        $section->addTextBreak(1);

        // ── Tableau de données ─────────────────────────────────────────────
        $colCount  = count($headers);
        $pageWidth = 15000; // twips pour une page A4 paysage avec marges
        $colWidth  = (int) floor($pageWidth / max($colCount, 1));

        $table = $section->addTable([
            'borderSize'  => 4,
            'borderColor' => 'CCCCCC',
            'cellMargin'  => 60,
            'width'       => 100,
            'unit'        => TblWidth::PERCENT,
        ]);

        // Ligne d'en-tête — vert DSP
        $table->addRow(400);
        foreach ($headers as $header) {
            $cell = $table->addCell($colWidth, [
                'bgColor'   => self::COLOR_HEADER_BG,
                'valign'    => 'center',
            ]);
            $cell->addText(
                (string) $header,
                ['color' => self::COLOR_HEADER_FG, 'bold' => true, 'size' => 8],
                ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER]
            );
        }

        // Lignes de données — alternance jaune/blanc (référence ANASER)
        foreach ($rows as $i => $row) {
            $bgColor = ($i % 2 === 0) ? self::COLOR_DATA_ODD : self::COLOR_DATA_EVEN;
            $table->addRow(300);
            foreach ($row as $col => $value) {
                $c = $table->addCell($colWidth, [
                    'bgColor' => $bgColor,
                    'valign'  => 'center',
                ]);
                $align = ($col === 0) ? \PhpOffice\PhpWord\SimpleType\Jc::CENTER : \PhpOffice\PhpWord\SimpleType\Jc::LEFT;
                $c->addText((string) ($value ?? '-'), ['size' => 8], ['alignment' => $align]);
            }
        }

        // Ligne de totaux — gris catégorie ANASER
        if (!empty($rows)) {
            $table->addRow(360);
            $firstTotal = true;
            foreach ($headers as $idx => $h) {
                $c = $table->addCell($colWidth, [
                    'bgColor' => self::COLOR_SUBCAT_BG,
                    'valign'  => 'center',
                ]);
                if ($firstTotal) {
                    $c->addText('TOTAUX', ['bold' => true, 'size' => 8.5, 'color' => self::COLOR_TOTAL_FG]);
                    $firstTotal = false;
                } else {
                    $c->addText('', ['size' => 8]);
                }
            }
        }

        $section->addTextBreak(1);
        $section->addText(
            'Total : ' . count($rows) . ' enregistrement(s)',
            ['bold' => true, 'size' => 10, 'color' => self::COLOR_TITLE_FG]
        );
        $section->addText(
            'Teranga GESCRIM — Rapport confidentiel — Accès réservé au personnel autorisé',
            ['size' => 8, 'color' => 'AAAAAA'],
            ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER]
        );

        // Écrire dans un fichier temporaire pour garantir l'intégrité du ZIP OOXML
        $tmpPath  = tempnam(sys_get_temp_dir(), 'gescrim_docx_');
        $writer   = IOFactory::createWriter($word, 'Word2007');
        $writer->save($tmpPath);

        $content  = file_get_contents($tmpPath);
        unlink($tmpPath);

        $fullName = $filename . '_' . now()->format('Y-m-d') . '.docx';

        return response($content, 200, [
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'Content-Disposition' => 'attachment; filename="' . $fullName . '"',
            'Content-Length'      => strlen($content),
            'Cache-Control'       => 'no-cache, no-store, must-revalidate',
            'Pragma'              => 'no-cache',
        ]);
    }
}
