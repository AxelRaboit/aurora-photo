<?php

declare(strict_types=1);

namespace Aurora\Module\Photo\Gallery\Service;

use Aurora\Module\Photo\Gallery\Entity\Gallery;
use Aurora\Module\Photo\Gallery\Entity\GalleryItemComment;
use Aurora\Module\Photo\Gallery\Entity\GalleryPick;
use Aurora\Module\Photo\Gallery\Enum\PickKindEnum;
use Aurora\Module\Photo\Gallery\Repository\GalleryFinalizationRepository;
use Aurora\Module\Photo\Gallery\Repository\GalleryItemCommentRepository;
use Aurora\Module\Photo\Gallery\Repository\GalleryPickRepository;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Builds an XLSX export of a gallery's selection: one sheet for each pick kind
 * (favorites / prints / discards) plus a Summary sheet and a Comments sheet.
 *
 * Streams the spreadsheet to the client without buffering on disk.
 */
final readonly class GalleryExportService
{
    private const string ACCENT_BG = 'FF1E3A8A';

    private const string ACCENT_TEXT = 'FFFFFFFF';

    private const string SUBTLE_BG = 'FFF1F5F9';

    public function __construct(
        private GalleryPickRepository $pickRepository,
        private GalleryItemCommentRepository $commentRepository,
        private GalleryFinalizationRepository $finalizationRepository,
    ) {}

    public function buildXlsxResponse(Gallery $gallery): Response
    {
        $spreadsheet = $this->build($gallery);
        $writer = new Xlsx($spreadsheet);

        $filename = sprintf('%s-selection.xlsx', $gallery->getSlug());

        $response = new StreamedResponse(static function () use ($writer): void {
            $writer->save('php://output');
        });
        $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $response->headers->set('Content-Disposition', sprintf('attachment; filename="%s"', $filename));
        $response->headers->set('Cache-Control', 'no-store, max-age=0');

        return $response;
    }

    private function build(Gallery $gallery): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getProperties()
            ->setTitle($gallery->getTitle())
            ->setSubject('Sélection client')
            ->setCreator('Aurora');

        $picks = $this->pickRepository->findAllForGallery((int) $gallery->getId());
        $byKind = [
            PickKindEnum::Favorite->value => [],
            PickKindEnum::Print->value => [],
            PickKindEnum::Discard->value => [],
        ];
        foreach ($picks as $pick) {
            $byKind[$pick->getKind()->value][] = $pick;
        }

        $this->buildSummarySheet($spreadsheet->getActiveSheet(), $gallery, $byKind);

        $kindLabels = [
            PickKindEnum::Favorite->value => 'Favoris',
            PickKindEnum::Print->value => 'À imprimer',
            PickKindEnum::Discard->value => 'À retirer',
        ];
        foreach ($kindLabels as $kindValue => $label) {
            $sheet = $spreadsheet->createSheet();
            $sheet->setTitle($label);
            $this->buildPicksSheet($sheet, $byKind[$kindValue]);
        }

        $comments = $this->commentRepository->findAllForGallery((int) $gallery->getId());
        if ([] !== $comments) {
            $commentsSheet = $spreadsheet->createSheet();
            $commentsSheet->setTitle('Commentaires');
            $this->buildCommentsSheet($commentsSheet, $comments);
        }

        $spreadsheet->setActiveSheetIndex(0);

        return $spreadsheet;
    }

    /** @param array<string, list<GalleryPick>> $byKind */
    private function buildSummarySheet(Worksheet $sheet, Gallery $gallery, array $byKind): void
    {
        $sheet->setTitle('Résumé');

        $sheet->setCellValue('A1', $gallery->getTitle());
        $sheet->mergeCells('A1:B1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(18);
        $sheet->getRowDimension(1)->setRowHeight(30);

        $finalizations = $this->finalizationRepository->findAllForGallery((int) $gallery->getId());
        $validatedBy = [];
        foreach ($finalizations as $finalization) {
            $label = mb_trim(sprintf('%s %s', $finalization->getVisitorName() ?? '', $finalization->getVisitorEmail() ? '<'.$finalization->getVisitorEmail().'>' : ''));
            $validatedBy[] = sprintf('%s — %s', $label ?: 'Anonyme', $finalization->getFinalizedAt()->format('d/m/Y H:i'));
        }

        $rows = [
            ['Slug', $gallery->getSlug()],
            ['Statut global', $gallery->isFinalized() ? 'Verrouillée' : 'Ouverte'],
            ['Validations visiteurs', (string) count($finalizations)],
            ['Détail des validations', [] === $validatedBy ? '—' : implode("\n", $validatedBy)],
            ['Photos dans la galerie', (string) $gallery->getItems()->count()],
            ['Favoris', (string) count($byKind[PickKindEnum::Favorite->value])],
            ['À imprimer', (string) count($byKind[PickKindEnum::Print->value])],
            ['À retirer', (string) count($byKind[PickKindEnum::Discard->value])],
        ];

        $row = 3;
        foreach ($rows as [$label, $value]) {
            $sheet->setCellValue('A'.$row, $label);
            $sheet->setCellValue('B'.$row, $value);
            $sheet->getStyle('A'.$row)->getFont()->setBold(true);
            $sheet->getStyle('A'.$row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::SUBTLE_BG);
            if (str_contains($value, "\n")) {
                $sheet->getStyle('B'.$row)->getAlignment()->setWrapText(true);
            }

            ++$row;
        }

        $sheet->getColumnDimension('A')->setWidth(28);
        $sheet->getColumnDimension('B')->setWidth(60);
    }

    /** @param list<GalleryPick> $picks */
    private function buildPicksSheet(Worksheet $sheet, array $picks): void
    {
        $headers = ['Photo #', 'Fichier', 'Position', 'Légende', 'Visiteur', 'Email', 'Date du pick'];
        $this->writeHeader($sheet, $headers);

        if ([] === $picks) {
            $sheet->setCellValue('A2', 'Aucun pick dans cette catégorie.');
            $sheet->mergeCells('A2:G2');
            $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('A2')->getFont()->setItalic(true)->getColor()->setARGB('FF888888');

            return;
        }

        $row = 2;
        foreach ($picks as $pick) {
            $item = $pick->getGalleryItem();
            $media = $item->getMedia();
            $sheet->setCellValue('A'.$row, '#'.$item->getNumber());
            $sheet->setCellValue('B'.$row, $media->getOriginalName());
            $sheet->setCellValue('C'.$row, $item->getPosition() + 1);
            $sheet->setCellValue('D'.$row, $item->getCaption() ?? '');
            $sheet->setCellValue('E'.$row, $pick->getVisitorName() ?? '');
            $sheet->setCellValue('F'.$row, $pick->getVisitorEmail() ?? '');
            $sheet->setCellValue('G'.$row, $pick->getPickedAt()->format('d/m/Y H:i'));
            ++$row;
        }

        $this->autosizeColumns($sheet, count($headers));
        $this->bandRows($sheet, 2, $row - 1, count($headers));
    }

    /** @param list<GalleryItemComment> $comments */
    private function buildCommentsSheet(Worksheet $sheet, array $comments): void
    {
        $headers = ['Photo #', 'Fichier', 'Visiteur', 'Email', 'Commentaire', 'Date'];
        $this->writeHeader($sheet, $headers);

        $row = 2;
        foreach ($comments as $comment) {
            $item = $comment->getGalleryItem();
            $media = $item->getMedia();
            $sheet->setCellValue('A'.$row, '#'.$item->getNumber());
            $sheet->setCellValue('B'.$row, $media->getOriginalName());
            $sheet->setCellValue('C'.$row, $comment->getVisitorName() ?? '');
            $sheet->setCellValue('D'.$row, $comment->getVisitorEmail() ?? '');
            $sheet->setCellValue('E'.$row, $comment->getContent());
            $sheet->setCellValue('F'.$row, $comment->getCreatedAt()->format('d/m/Y H:i'));
            $sheet->getStyle('E'.$row)->getAlignment()->setWrapText(true);
            ++$row;
        }

        $sheet->getColumnDimension('E')->setWidth(60);
        foreach (['A' => 10, 'B' => 30, 'C' => 24, 'D' => 28, 'F' => 18] as $col => $width) {
            $sheet->getColumnDimension($col)->setWidth($width);
        }

        $this->bandRows($sheet, 2, $row - 1, count($headers));
    }

    /** @param list<string> $headers */
    private function writeHeader(Worksheet $sheet, array $headers): void
    {
        foreach ($headers as $i => $header) {
            $cell = $this->columnLetter($i + 1).'1';
            $sheet->setCellValue($cell, $header);
        }

        $headerRange = 'A1:'.$this->columnLetter(count($headers)).'1';
        $style = $sheet->getStyle($headerRange);
        $style->getFont()->setBold(true)->getColor()->setARGB(self::ACCENT_TEXT);
        $style->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::ACCENT_BG);
        $style->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getRowDimension(1)->setRowHeight(22);
        $sheet->freezePane('A2');
    }

    private function bandRows(Worksheet $sheet, int $from, int $to, int $cols): void
    {
        if ($to < $from) {
            return;
        }

        $lastCol = $this->columnLetter($cols);
        for ($r = $from; $r <= $to; ++$r) {
            $range = 'A'.$r.':'.$lastCol.$r;
            if (0 === ($r - $from) % 2) {
                $sheet->getStyle($range)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::SUBTLE_BG);
            }

            $sheet->getStyle($range)->getBorders()->getBottom()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('FFE2E8F0');
        }
    }

    private function autosizeColumns(Worksheet $sheet, int $cols): void
    {
        for ($i = 1; $i <= $cols; ++$i) {
            $sheet->getColumnDimension($this->columnLetter($i))->setAutoSize(true);
        }
    }

    private function columnLetter(int $index): string
    {
        $letter = '';
        while ($index > 0) {
            $mod = ($index - 1) % 26;
            $letter = chr(65 + $mod).$letter;
            $index = (int) (($index - $mod) / 26);
        }

        return $letter;
    }
}
