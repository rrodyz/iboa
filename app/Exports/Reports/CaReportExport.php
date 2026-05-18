<?php

namespace App\Exports\Reports;

use App\Models\Company;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\RegistersEventListeners;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class CaReportExport implements FromArray, WithTitle, WithColumnWidths, WithEvents
{
    use \App\Exports\Concerns\HasCompanyHeader;
    use RegistersEventListeners;

    private const T_HEADER = 'header';
    private const T_PERIOD = 'period';
    private const T_TOTALS = 'totals';
    private const T_BLANK  = 'blank';
    private const T_COLHDR = 'colhdr';
    private const T_DATA   = 'data';
    private const T_FOOTER = 'footer';

    private const COLS     = 5; // A–E
    private const LAST_COL = 'E';
    private const COLOR    = '4F46E5';

    private array $rows   = [];
    private array $meta   = [];
    private int   $rowIdx = 0;

    private array $dataRowNums = [];

    public function __construct(
        private Collection $serie,
        private object $totals,
        private string $from,
        private string $to
    ) {
        $this->build();
    }

    public function title(): string { return 'CA '.$this->from.' au '.$this->to; }
    public function array(): array  { return $this->rows; }

    public function columnWidths(): array
    {
        return [
            'A' => 20, // Période
            'B' => 16, // Nb Factures
            'C' => 20, // CA HT (FCFA)
            'D' => 20, // CA TTC (FCFA)
            'E' => 20, // Encaissé (FCFA)
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => fn(AfterSheet $e) => $this->applyStyles($e->sheet->getDelegate()),
        ];
    }

    // -------------------------------------------------------------------------
    // Data builder
    // -------------------------------------------------------------------------

    private function build(): void
    {
        $company = Company::first();

        $totalHt        = (int) $this->totals->total_ht;
        $totalTtc       = (int) $this->totals->total_ttc;
        $totalEncaisse  = (int) $this->totals->encaisse;
        $totalNbFact    = (int) $this->totals->nb_factures;

        // ── Row 1 : document header ───────────────────────────────────────────
        $this->push(
            [$this->companyNameCell($company), null, "CHIFFRE D'AFFAIRES", null, 'Au '.now()->format('d/m/Y')],
            self::T_HEADER
        );

        // ── Row 2 : period ────────────────────────────────────────────────────
        $periodText = 'Période : '.$this->from.' → '.$this->to;
        $this->push(
            [$this->companyLegalLine($company, $periodText), null, null, null, null],
            self::T_PERIOD
        );

        // ── Row 3 : summary totals ────────────────────────────────────────────
        $this->push([
            $totalNbFact.' facture(s)',
            null,
            'CA HT : '.$totalHt,
            'CA TTC : '.$totalTtc,
            'Encaissé : '.$totalEncaisse,
        ], self::T_TOTALS);

        // ── Row 4 : blank ─────────────────────────────────────────────────────
        $this->push(array_fill(0, self::COLS, null), self::T_BLANK);

        // ── Row 5 : column headers ────────────────────────────────────────────
        $this->push([
            'Période', 'Nb Factures', 'CA HT (FCFA)', 'CA TTC (FCFA)', 'Encaissé (FCFA)',
        ], self::T_COLHDR);

        // ── Data rows ─────────────────────────────────────────────────────────
        foreach ($this->serie as $row) {
            $this->push([
                $row->label,
                $row->nb,
                (int) $row->ht,
                (int) $row->ttc,
                (int) $row->encaisse,
            ], self::T_DATA);

            $this->dataRowNums[] = $this->rowIdx;
        }

        // ── Footer total ──────────────────────────────────────────────────────
        $this->push([
            'TOTAL — '.$totalNbFact.' facture(s)',
            $totalNbFact,
            $totalHt,
            $totalTtc,
            $totalEncaisse,
        ], self::T_FOOTER);
    }

    private function push(array $cells, string $type): void
    {
        $this->rows[]                = $cells;
        $this->meta[++$this->rowIdx] = $type;
    }

    // -------------------------------------------------------------------------
    // Styles (AfterSheet)
    // -------------------------------------------------------------------------

    private function applyStyles(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $ws): void
    {
        $lastCol = self::LAST_COL;
        $numFmt  = '#,##0';
        $numCols = ['C', 'D', 'E'];

        foreach ($this->dataRowNums as $r) {
            foreach ($numCols as $c) {
                $ws->getStyle("{$c}{$r}")->getNumberFormat()->setFormatCode($numFmt);
            }
        }

        foreach ($this->meta as $r => $type) {
            match ($type) {
                self::T_HEADER => $this->styleHeader($ws, $r, $lastCol),
                self::T_PERIOD => $this->stylePeriod($ws, $r, $lastCol),
                self::T_TOTALS => $this->styleTotals($ws, $r, $lastCol, $numFmt),
                self::T_COLHDR => $this->styleColHeader($ws, $r, $lastCol),
                self::T_DATA   => $this->styleData($ws, $r),
                self::T_FOOTER => $this->styleFooter($ws, $r, $lastCol, $numFmt),
                default        => null,
            };
        }

        $ws->freezePane('A6');

        $ws->getPageSetup()
            ->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE)
            ->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_A4)
            ->setFitToPage(true)->setFitToWidth(1)->setFitToHeight(0);
        $ws->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd(1, 5);
        $ws->getHeaderFooter()->setOddHeader("&L&BChiffre d'Affaires&R&P / &N");
        $ws->getHeaderFooter()->setOddFooter('&LÉdité le '.now()->format('d/m/Y').'&R&F');

        // Company header text wrapping
        $this->applyCompanyHeaderWrap($ws, 1, 2);
    }

    private function styleHeader(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $ws, int $r, string $lastCol): void
    {
        $ws->mergeCells("A{$r}:A{$r}");
        $ws->mergeCells("B{$r}:D{$r}");
        $ws->mergeCells("E{$r}:{$lastCol}{$r}");

        $ws->getStyle("A{$r}:{$lastCol}{$r}")->applyFromArray([
            'font'      => ['bold' => true, 'size' => 12, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::COLOR]],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $ws->getStyle("A{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $ws->getStyle("B{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $ws->getStyle("E{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
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
        $ws->getRowDimension($r)->setRowHeight(15);
    }

    private function styleTotals(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $ws, int $r, string $lastCol, string $numFmt): void
    {
        $ws->mergeCells("A{$r}:B{$r}");

        $ws->getStyle("A{$r}:{$lastCol}{$r}")->applyFromArray([
            'font'      => ['bold' => true, 'size' => 9, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '3730A3']],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $ws->getStyle("A{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        foreach (['C', 'D', 'E'] as $c) {
            $ws->getStyle("{$c}{$r}")->applyFromArray([
                'font'      => ['bold' => true, 'size' => 10, 'color' => ['rgb' => 'E0E7FF']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT],
            ]);
        }
        $ws->getRowDimension($r)->setRowHeight(17);
    }

    private function styleColHeader(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $ws, int $r, string $lastCol): void
    {
        $ws->getStyle("A{$r}:{$lastCol}{$r}")->applyFromArray([
            'font'      => ['bold' => true, 'size' => 9, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::COLOR]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'borders'   => [
                'bottom' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => '3730A3']],
                'top'    => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => '3730A3']],
            ],
        ]);
        $ws->getStyle("A{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        foreach (['C', 'D', 'E'] as $c) {
            $ws->getStyle("{$c}{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        }
        $ws->getRowDimension($r)->setRowHeight(20);
    }

    private function styleData(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $ws, int $r): void
    {
        $ws->getStyle("A{$r}:".self::LAST_COL."{$r}")->applyFromArray([
            'font'      => ['size' => 9],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
            'borders'   => ['bottom' => ['borderStyle' => Border::BORDER_HAIR, 'color' => ['rgb' => 'E5E7EB']]],
        ]);
        foreach (['B', 'C', 'D', 'E'] as $c) {
            $ws->getStyle("{$c}{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        }
        $ws->getRowDimension($r)->setRowHeight(14);
    }

    private function styleFooter(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $ws, int $r, string $lastCol, string $numFmt): void
    {
        $ws->mergeCells("A{$r}:A{$r}");
        $ws->getStyle("A{$r}:{$lastCol}{$r}")->applyFromArray([
            'font'      => ['bold' => true, 'size' => 10, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '312E81']],
            'borders'   => ['top' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => '3730A3']]],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $ws->getStyle("A{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        foreach (['C', 'D', 'E'] as $c) {
            $ws->getStyle("{$c}{$r}")->applyFromArray([
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT],
            ]);
            $ws->getStyle("{$c}{$r}")->getNumberFormat()->setFormatCode($numFmt);
        }
        $ws->getRowDimension($r)->setRowHeight(20);
    }
}
