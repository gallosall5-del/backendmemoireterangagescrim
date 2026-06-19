<?php

namespace App\Services\Export;

use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;
use Illuminate\Http\Response;

class WordExportService
{
    /**
     * Génère un fichier .docx et le retourne en téléchargement.
     * Passe par un fichier temporaire pour éviter la corruption du ZIP OOXML
     * par les octets parasites (CORS/session headers) que Laravel peut injecter
     * dans php://output avant la réponse.
     *
     * @param string $title    Titre du document
     * @param string $subtitle Sous-titre (module + période)
     * @param string $agent    Nom de l'agent ayant généré l'export
     * @param array  $headers  En-têtes du tableau
     * @param array  $rows     Lignes du tableau
     * @param string $filename Nom de fichier sans extension
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
            'marginTop'    => 800,
            'marginBottom' => 800,
            'marginLeft'   => 900,
            'marginRight'  => 900,
        ]);

        // En-tête document
        $section->addText(
            'TERANGA GESCRIM — Direction de la Sécurité Publique',
            ['bold' => true, 'size' => 13, 'color' => '1B4332'],
            ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER]
        );
        $section->addText(
            $title,
            ['bold' => true, 'size' => 14],
            ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER]
        );
        $section->addText(
            $subtitle,
            ['size' => 10, 'color' => '555555'],
            ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER]
        );
        $section->addText(
            'Généré le : ' . now()->format('d/m/Y H:i') . ' — par : ' . $agent,
            ['size' => 9, 'color' => '888888'],
            ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER]
        );
        $section->addTextBreak(1);

        // Tableau de données
        $colCount = count($headers);
        $colWidth = (int) floor(9000 / max($colCount, 1));

        $table = $section->addTable([
            'borderSize'  => 6,
            'borderColor' => 'CCCCCC',
            'cellMargin'  => 80,
        ]);

        // Ligne d'en-tête
        $table->addRow();
        foreach ($headers as $header) {
            $cell = $table->addCell($colWidth, ['bgColor' => '1B4332']);
            $cell->addText($header, ['color' => 'FFFFFF', 'bold' => true, 'size' => 9]);
        }

        // Lignes de données
        foreach ($rows as $i => $row) {
            $bgColor = ($i % 2 === 0) ? 'FFFFFF' : 'F5F5F5';
            $table->addRow();
            foreach ($row as $cell) {
                $c = $table->addCell($colWidth, ['bgColor' => $bgColor]);
                $c->addText((string) ($cell ?? '-'), ['size' => 9]);
            }
        }

        $section->addTextBreak(1);
        $section->addText(
            'Total : ' . count($rows) . ' enregistrement(s)',
            ['bold' => true, 'size' => 10]
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
