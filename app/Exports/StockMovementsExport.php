<?php

namespace App\Exports;

use App\Models\Company;
use App\Models\StockMovement;
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

class StockMovementsExport implements FromArray, WithTitle, WithColumnWidths, WithEvents
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

    private const COLS     = 12; // A–L
    private const LAST_COL = 'L';
    private const COLOR    = '0F766E';

    private array $rows   = [];
    private array $meta   = [];
    private int   $rowIdx = 0;

    private array $dataRowNums = [];

    public function __construct(private array $filters = [])
    {
        $this->build();
    }

    public function title(): string { return 'Mouvements de stock'; }
    public function array(): array  { return $this->rows; }

    public function columnWidths(): array
    {
        return [
            'A' => 14, // Date
            'B' => 28, // Produit
            'C' => 16, // Référence
            'D' => 20, // Entrepôt
            'E' => 22, // Type mouvement
            'F' => 12, // Quantité
            'G' => 16, // P.U. (FCFA)
            'H' => 18, // Total (FCFA)
            'I' => 22, // Méthode valorisation
            'J' => 18, // CMP après (FCFA)
            'K' => 16, // N° lot
            'L' => 20, // Créé par
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
        $company  = Company::first();
        $dateFrom = $this->filters['date_from'] ?? null;
        $dateTo   = $this->filters['date_to']   ?? null;

        $movements = StockMovement::with(['product', 'warehouse', 'createdBy'])
            ->orderByDesc('occurred_at')
            ->when(!empty($this->filters['product_id']),   fn($q) => $q->where('product_id',   $this->filters['product_id']))
            ->when(!empty($this->filters['warehouse_id']), fn($q) => $q->where('warehouse_id', $this->filters['warehouse_id']))
            ->when(!empty($this->filters['type']),         fn($q) => $q->where('type',         $this->filters['type']))
            ->when($dateFrom,                              fn($q) => $q->whereDate('occurred_at', '>=', $dateFrom))
            ->when($dateTo,                                fn($q) => $q->whereDate('occurred_at', '<=', $dateTo))
            ->get();

        $typeLabels = [
            'entree'             => 'Entrée',
            'sortie'             => 'Sortie',
            'transfert'          => 'Transfert',
            'ajustement'         => 'Ajustement',
            'retour_client'      => 'Retour client',
            'retour_fournisseur' => 'Retour fournisseur',
        ];

        $totalEntrees = $movements->where('type', 'entree')->sum('quantity');
        $totalSorties = $movements->where('type', 'sortie')->sum('quantity');

        // ── Row 1 : document header ───────────────────────────────────────────
        $this->push(
            [$this->companyNameCell($company), null, null, null, null, 'MOUVEMENTS DE STOCK', null, null, null, null, null, 'Au '.now()->format('d/m/Y')],
            self::T_HEADER
        );

        // ── Row 2 : period + filters ──────────────────────────────────────────
        $period = ($dateFrom || $dateTo)
            ? 'Période : '.($dateFrom ? \Carbon\Carbon::parse($dateFrom)->format('d/m/Y') : '—')
              .' → '.($dateTo ? \Carbon\Carbon::parse($dateTo)->format('d/m/Y') : "aujourd'hui")
            : 'Toutes les dates';

        $this->push(
            [$this->companyLegalLine($company, $period), null, null, null, null, null, null, null, null, null, null, null],
            self::T_PERIOD
        );

        // ── Row 3 : summary totals ────────────────────────────────────────────
        $this->push([
            $movements->count().' mouvement(s)', null,
            'Entrées :', (float) $totalEntrees, null,
            'Sorties :', (float) $totalSorties, null,
            null, null, null, null,
        ], self::T_TOTALS);

        // ── Row 4 : blank ─────────────────────────────────────────────────────
        $this->push(array_fill(0, self::COLS, null), self::T_BLANK);

        // ── Row 5 : column headers ────────────────────────────────────────────
        $this->push([
            'Date', 'Produit', 'Référence', 'Entrepôt', 'Type mouvement', 'Quantité',
            'P.U. (FCFA)', 'Total (FCFA)', 'Méthode valorisation', 'CMP après (FCFA)', 'N° lot', 'Créé par',
        ], self::T_COLHDR);

        // ── Data rows ─────────────────────────────────────────────────────────
        foreach ($movements as $movement) {
            $this->push([
                $movement->occurred_at?->format('d/m/Y'),
                $movement->product?->name ?? '—',
                $movement->product?->reference ?? '—',
                $movement->warehouse?->name ?? '—',
                $typeLabels[$movement->type] ?? $movement->type,
                (float) $movement->quantity,
                (int) $movement->unit_cost,
                (int) $movement->total_cost,
                strtoupper($movement->valuation_method ?? 'CMP'),
                (int) $movement->avg_cost_after,
                $movement->lot_number ?? '—',
                $movement->createdBy?->name ?? '—',
            ], self::T_DATA);

            $this->dataRowNums[] = $this->rowIdx;
        }

        // ── Footer total ──────────────────────────────────────────────────────
        $this->push([
            'TOTAL — '.$movements->count().' mouvement(s)',
            null, null, null, null,
            null, null, null, null, null, null, null,
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
        $numCols = ['F', 'G', 'H', 'J'];

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
        $ws->getHeaderFooter()->setOddHeader('&L&BMouvements de Stock&R&P / &N');
        $ws->getHeaderFooter()->setOddFooter('&LÉdité le '.now()->format('d/m/Y').'&R&F');

        // Company header text wrapping
        $this->applyCompanyHeaderWrap($ws, 1, 2);
    }

    private function styleHeader(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $ws, int $r, string $lastCol): void
    {
        $ws->mergeCells("A{$r}:B{$r}");
        $ws->mergeCells("C{$r}:J{$r}");
        $ws->mergeCells("K{$r}:{$lastCol}{$r}");

        $ws->getStyle("A{$r}:{$lastCol}{$r}")->applyFromArray([
            'font'      => ['bold' => true, 'size' => 12, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::COLOR]],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $ws->getStyle("A{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $ws->getStyle("C{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $ws->getStyle("K{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $ws->getRowDimension($r)->setRowHeight(26);
    }

    private function stylePeriod(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $ws, int $r, string $lastCol): void
    {
        $ws->mergeCells("A{$r}:{$lastCol}{$r}");
        $ws->getStyle("A{$r}:{$lastCol}{$r}")->applyFromArray([
            'font'      => ['italic' => true, 'size' => 9, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0D9488']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $ws->getRowDimension($r)->setRowHeight(15);
    }

    private function styleTotals(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $ws, int $r, string $lastCol, string $numFmt): void
    {
        $ws->mergeCells("A{$r}:B{$r}");
        $ws->mergeCells("E{$r}:E{$r}");
        $ws->mergeCells("G{$r}:{$lastCol}{$r}");

        $ws->getStyle("A{$r}:{$lastCol}{$r}")->applyFromArray([
            'font'      => ['bold' => true, 'size' => 9, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0D9488']],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $ws->getStyle("A{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        foreach (['C', 'F'] as $c) {
            $ws->getStyle("{$c}{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        }
        foreach (['D', 'G'] as $c) {
            $ws->getStyle("{$c}{$r}")->applyFromArray([
                'font'      => ['bold' => true, 'size' => 10, 'color' => ['rgb' => 'CCFBF1']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT],
            ]);
            $ws->getStyle("{$c}{$r}")->getNumberFormat()->setFormatCode($numFmt);
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
                'bottom' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => '0D9488']],
                'top'    => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => '0D9488']],
            ],
        ]);
        foreach (['A', 'B', 'C', 'D', 'E', 'I', 'K', 'L'] as $c) {
            $ws->getStyle("{$c}{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        }
        foreach (['F', 'G', 'H', 'J'] as $c) {
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
        foreach (['F', 'G', 'H', 'J'] as $c) {
            $ws->getStyle("{$c}{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        }
        $ws->getStyle('E'.$r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $ws->getRowDimension($r)->setRowHeight(14);
    }

    private function styleFooter(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $ws, int $r, string $lastCol, string $numFmt): void
    {
        $ws->mergeCells("A{$r}:{$lastCol}{$r}");
        $ws->getStyle("A{$r}:{$lastCol}{$r}")->applyFromArray([
            'font'      => ['bold' => true, 'size' => 10, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0F766E']],
            'borders'   => ['top' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => '0D9488']]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $ws->getRowDimension($r)->setRowHeight(20);
    }
}
