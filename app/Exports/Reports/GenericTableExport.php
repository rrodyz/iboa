<?php

namespace App\Exports\Reports;

use App\Models\Company;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\RegistersEventListeners;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * Export Excel générique réutilisable pour tous les états.
 * Prend des colonnes, des lignes et des options de style.
 */
class GenericTableExport implements FromArray, WithTitle, WithColumnWidths, WithEvents
{
    use RegistersEventListeners;

    private const COLOR    = '4F46E5';
    private const DARK     = '312E81';
    private const DARKER   = '1E1B4B';

    private array $rows   = [];
    private array $meta   = [];
    private int   $rowIdx = 0;
    private array $dataRowNums = [];
    private int   $nbCols;
    private string $lastCol;

    public function __construct(
        private string $sheetTitle,
        private array  $headers,
        private array  $data,            // array of arrays (each row = one array of values)
        private string $from  = '',
        private string $to    = '',
        private array  $numericColIdxs = [], // 0-based column indexes to format as #,##0
        private array  $colWidths = [],
        private ?array $totals = null,   // optional footer totals row
    ) {
        $this->nbCols  = count($headers);
        $this->lastCol = chr(ord('A') + $this->nbCols - 1);
        if ($this->nbCols > 26) {
            // For more than 26 cols, use AA, AB... not needed for current reports
            $this->lastCol = 'Z';
        }
        $this->build();
    }

    public function title(): string { return mb_substr($this->sheetTitle, 0, 31); }
    public function array(): array  { return $this->rows; }

    public function columnWidths(): array
    {
        if (!empty($this->colWidths)) {
            return $this->colWidths;
        }
        // Default: distribute evenly
        $widths = [];
        for ($i = 0; $i < $this->nbCols; $i++) {
            $widths[chr(ord('A') + $i)] = 18;
        }
        return $widths;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => fn(AfterSheet $e) => $this->applyStyles($e->sheet->getDelegate()),
        ];
    }

    // -------------------------------------------------------------------------
    private function build(): void
    {
        $company = currentCompany();
        $period  = $this->from && $this->to
            ? 'Période : ' . $this->from . ' → ' . $this->to
            : 'Édité le ' . now()->format('d/m/Y H:i');

        // Row 1 : header band
        $row = array_fill(0, $this->nbCols, null);
        $row[0] = $company?->name ?? 'ERP';
        if ($this->nbCols > 2) {
            $mid = (int)floor($this->nbCols / 2);
            $row[$mid] = mb_strtoupper($this->sheetTitle);
        }
        $row[$this->nbCols - 1] = 'Au ' . now()->format('d/m/Y');
        $this->push($row, 'header');

        // Row 2 : period/subtitle
        $row2    = array_fill(0, $this->nbCols, null);
        $row2[0] = ($company ? ($company->ifu ? 'IFU : '.$company->ifu.' | ' : '') . ($company->phone ?? '') : '') . ' | ' . $period;
        $this->push($row2, 'period');

        // Row 3 : blank
        $this->push(array_fill(0, $this->nbCols, null), 'blank');

        // Row 4 : column headers
        $this->push($this->headers, 'colhdr');

        // Data rows
        foreach ($this->data as $rowData) {
            $this->push(array_values((array) $rowData), 'data');
            $this->dataRowNums[] = $this->rowIdx;
        }

        // Footer totals
        if ($this->totals !== null) {
            $this->push(array_values($this->totals), 'footer');
        }
    }

    private function push(array $cells, string $type): void
    {
        $this->rows[]                = $cells;
        $this->meta[++$this->rowIdx] = $type;
    }

    // -------------------------------------------------------------------------
    private function applyStyles(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $ws): void
    {
        $lastCol = $this->lastCol;

        foreach ($this->meta as $r => $type) {
            match ($type) {
                'header' => $this->styleHeader($ws, $r, $lastCol),
                'period' => $this->stylePeriod($ws, $r, $lastCol),
                'colhdr' => $this->styleColHeader($ws, $r, $lastCol),
                'data'   => $this->styleData($ws, $r, $lastCol),
                'footer' => $this->styleFooter($ws, $r, $lastCol),
                default  => null,
            };
        }

        // Format numeric columns in data rows
        if (!empty($this->numericColIdxs)) {
            $numFmt = '#,##0';
            foreach ($this->dataRowNums as $r) {
                foreach ($this->numericColIdxs as $idx) {
                    $col = chr(ord('A') + $idx);
                    $ws->getStyle("{$col}{$r}")->getNumberFormat()->setFormatCode($numFmt);
                }
            }
        }

        $ws->freezePane('A5');

        $ws->getPageSetup()
            ->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE)
            ->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_A4)
            ->setFitToPage(true)->setFitToWidth(1)->setFitToHeight(0);
        $ws->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd(1, 4);
        $ws->getHeaderFooter()->setOddHeader('&L&B' . $this->sheetTitle . '&R&P / &N');
        $ws->getHeaderFooter()->setOddFooter('&LÉdité le ' . now()->format('d/m/Y') . '&R&F');
    }

    private function styleHeader(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $ws, int $r, string $lastCol): void
    {
        if ($this->nbCols > 2) {
            $mid     = chr(ord('A') + (int)floor($this->nbCols / 2));
            $midNext = chr(ord($mid) + 2);
            $ws->mergeCells("A{$r}:B{$r}");
            $ws->mergeCells("{$mid}{$r}:{$midNext}{$r}");
        }
        $ws->getStyle("A{$r}:{$lastCol}{$r}")->applyFromArray([
            'font'      => ['bold' => true, 'size' => 12, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::COLOR]],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $ws->getStyle("A{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $ws->getStyle("{$lastCol}{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $ws->getRowDimension($r)->setRowHeight(26);
    }

    private function stylePeriod(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $ws, int $r, string $lastCol): void
    {
        $ws->mergeCells("A{$r}:{$lastCol}{$r}");
        $ws->getStyle("A{$r}:{$lastCol}{$r}")->applyFromArray([
            'font'      => ['italic' => true, 'size' => 9, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4338CA']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $ws->getRowDimension($r)->setRowHeight(14);
    }

    private function styleColHeader(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $ws, int $r, string $lastCol): void
    {
        $ws->getStyle("A{$r}:{$lastCol}{$r}")->applyFromArray([
            'font'      => ['bold' => true, 'size' => 9, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::COLOR]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'borders'   => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '3730A3']],
            ],
        ]);
        $ws->getStyle("A{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $ws->getRowDimension($r)->setRowHeight(22);
    }

    private function styleData(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $ws, int $r, string $lastCol): void
    {
        $ws->getStyle("A{$r}:{$lastCol}{$r}")->applyFromArray([
            'font'      => ['size' => 8],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
            'borders'   => ['bottom' => ['borderStyle' => Border::BORDER_HAIR, 'color' => ['rgb' => 'E5E7EB']]],
        ]);
        $ws->getRowDimension($r)->setRowHeight(13);
    }

    private function styleFooter(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $ws, int $r, string $lastCol): void
    {
        $ws->getStyle("A{$r}:{$lastCol}{$r}")->applyFromArray([
            'font'      => ['bold' => true, 'size' => 9, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::DARK]],
            'borders'   => ['top' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => '3730A3']]],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $ws->getStyle("A{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $ws->getRowDimension($r)->setRowHeight(18);

        if (!empty($this->numericColIdxs)) {
            foreach ($this->numericColIdxs as $idx) {
                $col = chr(ord('A') + $idx);
                $ws->getStyle("{$col}{$r}")->applyFromArray([
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT],
                ]);
                $ws->getStyle("{$col}{$r}")->getNumberFormat()->setFormatCode('#,##0');
            }
        }
    }
}
