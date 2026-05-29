<?php

namespace App\Exports;

use App\Models\Company;
use App\Models\ProductStock;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\RegistersEventListeners;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class StockExport implements FromArray, WithTitle, WithColumnWidths, WithEvents
{
    use \App\Exports\Concerns\HasCompanyHeader;
    use RegistersEventListeners;

    private const T_DOC_HEADER = 'doc_header';
    private const T_SUBTITLE   = 'subtitle';
    private const T_BLANK      = 'blank';
    private const T_COL_HEADER = 'col_header';
    private const T_SECTION    = 'section';
    private const T_DATA       = 'data';
    private const T_ALERT      = 'alert';
    private const T_RUPTURE    = 'rupture';
    private const T_TOTAL      = 'total';

    private array $rows     = [];
    private array $meta     = [];   // 1-indexed → type
    private int   $rowIdx   = 0;
    private array $dataRows = [];   // for number format
    private array $alertRows   = [];
    private array $ruptureRows = [];

    public function __construct(
        private ?int    $warehouseId,
        private ?string $search,
        private bool    $lowStockOnly,
    ) {
        $this->build();
    }

    public function title(): string { return 'État de stock'; }
    public function array(): array  { return $this->rows; }

    public function columnWidths(): array
    {
        return [
            'A' => 14,   // Référence
            'B' => 36,   // Désignation
            'C' => 20,   // Famille
            'D' => 20,   // Entrepôt
            'E' => 14,   // Unité
            'F' => 14,   // Qté physique
            'G' => 14,   // Qté réservée
            'H' => 14,   // Qté disponible
            'I' => 14,   // Seuil min
            'J' => 14,   // Seuil max
            'K' => 18,   // CMP unitaire
            'L' => 20,   // Valeur stock
            'M' => 14,   // Statut
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => fn(AfterSheet $e) => $this->applyStyles($e->sheet->getDelegate()),
        ];
    }

    /* ── Build ─────────────────────────────────────────────────────────────── */

    private function build(): void
    {
        $company = currentCompany();
        $date    = now()->format('d/m/Y');
        $time    = now()->format('H\hi');

        // ── Ligne 1 : en-tête document ───────────────────────────────────────
        $this->push([
            $this->companyNameCell($company),
            null, null, null,
            'ÉTAT DE STOCK',
            null, null, null, null, null, null, null,
            'Édité le ' . $date . ' à ' . $time,
        ], self::T_DOC_HEADER);

        // ── Ligne 2 : sous-titre / filtres ──────────────────────────────────
        $subtitle = 'Tous les entrepôts';
        if ($this->warehouseId) {
            $wh = \App\Models\Warehouse::find($this->warehouseId);
            $subtitle = 'Entrepôt : ' . ($wh?->name ?? $this->warehouseId);
        }
        if ($this->lowStockOnly) {
            $subtitle .= ' — Articles sous seuil uniquement';
        }

        $this->push([
            $this->companyLegalLine($company, $subtitle),
            null, null, null, null, null, null, null, null, null, null, null,
            'Date d\'arrêté : ' . $date,
        ], self::T_SUBTITLE);

        // ── Ligne 3 : vide ───────────────────────────────────────────────────
        $this->push(array_fill(0, 13, null), self::T_BLANK);

        // ── Ligne 4 : en-têtes colonnes ──────────────────────────────────────
        $this->push([
            'Référence',
            'Désignation',
            'Famille',
            'Entrepôt',
            'Unité',
            'Qté physique',
            'Qté réservée',
            'Qté disponible',
            'Seuil min',
            'Seuil max',
            'CMP unitaire (FCFA)',
            'Valeur stock (FCFA)',
            'Statut',
        ], self::T_COL_HEADER);

        // ── Données ──────────────────────────────────────────────────────────
        $stocks = $this->loadStocks();

        $totalPhysique   = 0;
        $totalReserve    = 0;
        $totalDisponible = 0;
        $totalValeur     = 0;
        $countAlert      = 0;
        $countRupture    = 0;

        foreach ($stocks as $stock) {
            $product   = $stock->product;
            $available = (float) $stock->quantity - (float) $stock->reserved_quantity;
            $threshold = (float) ($product?->stock_min ?? 0);
            $valeur    = (float) $stock->quantity * (float) $stock->avg_cost;

            // Statut
            if ($available <= 0) {
                $statut    = 'RUPTURE';
                $rowType   = self::T_RUPTURE;
                $countRupture++;
            } elseif ($threshold > 0 && $available <= $threshold) {
                $statut    = 'ALERTE';
                $rowType   = self::T_ALERT;
                $countAlert++;
            } else {
                $statut    = 'OK';
                $rowType   = self::T_DATA;
            }

            $this->push([
                $product?->reference ?? '—',
                $product?->name      ?? '—',
                $product?->family?->name ?? '—',
                $stock->warehouse?->name ?? '—',
                $product?->unit?->symbol ?? $product?->unit?->name ?? '—',
                (float) $stock->quantity,
                (float) $stock->reserved_quantity,
                $available,
                $threshold > 0 ? $threshold : null,
                (float) ($product?->stock_max ?? 0) > 0 ? (float) $product->stock_max : null,
                (float) $stock->avg_cost > 0 ? (float) $stock->avg_cost : null,
                $valeur > 0 ? $valeur : null,
                $statut,
            ], $rowType);

            if ($rowType === self::T_DATA)    $this->dataRows[]    = $this->rowIdx;
            if ($rowType === self::T_ALERT)   $this->alertRows[]   = $this->rowIdx;
            if ($rowType === self::T_RUPTURE) $this->ruptureRows[] = $this->rowIdx;

            $totalPhysique   += (float) $stock->quantity;
            $totalReserve    += (float) $stock->reserved_quantity;
            $totalDisponible += $available;
            $totalValeur     += $valeur;
        }

        // ── Totaux ───────────────────────────────────────────────────────────
        $this->push([
            'TOTAUX',
            $stocks->count() . ' article(s)  |  ' . $countAlert . ' alerte(s)  |  ' . $countRupture . ' rupture(s)',
            null, null, null,
            $totalPhysique,
            $totalReserve,
            $totalDisponible,
            null, null, null,
            $totalValeur,
            null,
        ], self::T_TOTAL);
    }

    private function push(array $cells, string $type): void
    {
        $this->rows[] = $cells;
        $this->rowIdx++;
        $this->meta[$this->rowIdx] = $type;
    }

    private function loadStocks()
    {
        $query = ProductStock::with(['product.family', 'product.unit', 'warehouse'])
            ->whereHas('product', fn($q) => $q->where('is_active', true));

        if ($this->warehouseId) {
            $query->where('warehouse_id', $this->warehouseId);
        }

        if ($this->search) {
            $s = '%' . $this->search . '%';
            $query->whereHas('product', fn($q) =>
                $q->where('name', 'like', $s)->orWhere('reference', 'like', $s)
            );
        }

        if ($this->lowStockOnly) {
            $query->whereHas('product', function ($q) {
                $q->where('is_active', true)->whereRaw('products.stock_min > 0');
            })->whereRaw('(product_stocks.quantity - product_stocks.reserved_quantity) <= (SELECT stock_min FROM products WHERE id = product_stocks.product_id)');
        }

        return $query
            ->orderByRaw('(SELECT name FROM products WHERE id = product_stocks.product_id)')
            ->lazy(1_000);
    }

    /* ── Styles ─────────────────────────────────────────────────────────────── */

    private function applyStyles(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $ws): void
    {
        $numFmt  = '#,##0.00';
        $numFmt0 = '#,##0';

        // Format numérique sur colonnes F-L pour lignes de données
        foreach (array_merge($this->dataRows, $this->alertRows, $this->ruptureRows) as $r) {
            foreach (['F', 'G', 'H', 'I', 'J'] as $col) {
                $ws->getStyle($col . $r)->getNumberFormat()->setFormatCode($numFmt);
            }
            foreach (['K', 'L'] as $col) {
                $ws->getStyle($col . $r)->getNumberFormat()->setFormatCode($numFmt0);
            }
        }

        // Format total
        $totalRow = array_key_last(array_filter($this->meta, fn($t) => $t === self::T_TOTAL));
        if ($totalRow) {
            foreach (['F', 'G', 'H', 'L'] as $col) {
                $ws->getStyle($col . $totalRow)->getNumberFormat()->setFormatCode($numFmt0);
            }
        }

        // Style par type de ligne
        foreach ($this->meta as $r => $type) {
            match ($type) {
                self::T_DOC_HEADER => $this->sDocHeader($ws, $r),
                self::T_SUBTITLE   => $this->sSubtitle($ws, $r),
                self::T_COL_HEADER => $this->sColHeader($ws, $r),
                self::T_DATA       => $this->sData($ws, $r),
                self::T_ALERT      => $this->sAlert($ws, $r),
                self::T_RUPTURE    => $this->sRupture($ws, $r),
                self::T_TOTAL      => $this->sTotal($ws, $r),
                default            => null,
            };
        }

        // Figer les volets sous la ligne d'en-têtes colonnes (ligne 4 = row 4 + freeze = row 5)
        $ws->freezePane('A5');

        // Paramètres d'impression
        $ws->getPageSetup()
            ->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE)
            ->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_A4)
            ->setFitToPage(true)
            ->setFitToWidth(1)
            ->setFitToHeight(0);

        $ws->getHeaderFooter()
            ->setOddHeader('&L&B' . 'État de Stock' . '&RPériode : ' . now()->format('d/m/Y'));
        $ws->getHeaderFooter()
            ->setOddFooter('&LÉdité le ' . now()->format('d/m/Y') . '&C&P / &N&R&F');

        $ws->setPrintGridlines(false);

        // Company header text wrapping
        $this->applyCompanyHeaderWrap($ws, 1, 2);

        // Répéter les 4 premières lignes sur chaque page imprimée
        $ws->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd(1, 4);

        // Légende couleurs en bas (après ligne totaux)
        $lastRow = $this->rowIdx + 2;
        $ws->setCellValue('A' . $lastRow, '■ Vert = Stock OK');
        $ws->setCellValue('D' . $lastRow, '■ Orange = Stock sous seuil d\'alerte');
        $ws->setCellValue('H' . $lastRow, '■ Rouge = Rupture de stock');
        $ws->getStyle('A' . $lastRow)->applyFromArray([
            'font' => ['size' => 8, 'color' => ['rgb' => '166534']],
        ]);
        $ws->getStyle('D' . $lastRow)->applyFromArray([
            'font' => ['size' => 8, 'color' => ['rgb' => '92400E']],
        ]);
        $ws->getStyle('H' . $lastRow)->applyFromArray([
            'font' => ['size' => 8, 'color' => ['rgb' => '991B1B']],
        ]);
    }

    /* ── Helpers de style ───────────────────────────────────────────────────── */

    private function sDocHeader($ws, int $r): void
    {
        $ws->mergeCells('A' . $r . ':D' . $r);
        $ws->mergeCells('E' . $r . ':L' . $r);
        $ws->getStyle('A' . $r . ':M' . $r)->applyFromArray([
            'font'      => ['bold' => true, 'size' => 13, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0F766E']],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $ws->getStyle('A' . $r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $ws->getStyle('E' . $r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $ws->getStyle('M' . $r)->applyFromArray([
            'font'      => ['size' => 9, 'italic' => true, 'color' => ['rgb' => 'CCFBF1']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0F766E']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $ws->getRowDimension($r)->setRowHeight(26);
    }

    private function sSubtitle($ws, int $r): void
    {
        $ws->mergeCells('A' . $r . ':L' . $r);
        $ws->getStyle('A' . $r . ':M' . $r)->applyFromArray([
            'font'      => ['italic' => true, 'size' => 9, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '134E4A']],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $ws->getStyle('A' . $r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $ws->getStyle('M' . $r)->applyFromArray([
            'font'      => ['size' => 9, 'italic' => true, 'color' => ['rgb' => '99F6E4']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '134E4A']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT],
        ]);
        $ws->getRowDimension($r)->setRowHeight(16);
    }

    private function sColHeader($ws, int $r): void
    {
        $ws->getStyle('A' . $r . ':M' . $r)->applyFromArray([
            'font'      => ['bold' => true, 'size' => 9, 'color' => ['rgb' => '134E4A']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'CCFBF1']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'borders'   => [
                'bottom' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => '0F766E']],
                'top'    => ['borderStyle' => Border::BORDER_THIN,   'color' => ['rgb' => '0F766E']],
            ],
        ]);
        $ws->getStyle('A' . $r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $ws->getStyle('B' . $r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $ws->getRowDimension($r)->setRowHeight(22);
    }

    private function sData($ws, int $r): void
    {
        $ws->getStyle('A' . $r . ':M' . $r)->applyFromArray([
            'font'      => ['size' => 9],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
            'borders'   => [
                'bottom' => ['borderStyle' => Border::BORDER_HAIR, 'color' => ['rgb' => 'E5E7EB']],
            ],
        ]);
        foreach (['F', 'G', 'H', 'I', 'J', 'K', 'L'] as $col) {
            $ws->getStyle($col . $r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        }
        $ws->getStyle('M' . $r)->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['rgb' => '166534']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $ws->getRowDimension($r)->setRowHeight(13);
    }

    private function sAlert($ws, int $r): void
    {
        $ws->getStyle('A' . $r . ':M' . $r)->applyFromArray([
            'font'      => ['size' => 9, 'color' => ['rgb' => '92400E']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFFBEB']],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
            'borders'   => [
                'bottom' => ['borderStyle' => Border::BORDER_HAIR, 'color' => ['rgb' => 'FDE68A']],
            ],
        ]);
        foreach (['F', 'G', 'H', 'I', 'J', 'K', 'L'] as $col) {
            $ws->getStyle($col . $r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        }
        $ws->getStyle('M' . $r)->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['rgb' => 'B45309']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FEF3C7']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $ws->getRowDimension($r)->setRowHeight(13);
    }

    private function sRupture($ws, int $r): void
    {
        $ws->getStyle('A' . $r . ':M' . $r)->applyFromArray([
            'font'      => ['size' => 9, 'color' => ['rgb' => '991B1B']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFF5F5']],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
            'borders'   => [
                'bottom' => ['borderStyle' => Border::BORDER_HAIR, 'color' => ['rgb' => 'FECACA']],
            ],
        ]);
        foreach (['F', 'G', 'H', 'I', 'J', 'K', 'L'] as $col) {
            $ws->getStyle($col . $r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        }
        $ws->getStyle('M' . $r)->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'DC2626']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $ws->getRowDimension($r)->setRowHeight(13);
    }

    private function sTotal($ws, int $r): void
    {
        $ws->mergeCells('B' . $r . ':E' . $r);
        $ws->getStyle('A' . $r . ':M' . $r)->applyFromArray([
            'font'      => ['bold' => true, 'size' => 10, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0F766E']],
            'borders'   => [
                'top' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => '134E4A']],
            ],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);
        foreach (['F', 'G', 'H', 'L'] as $col) {
            $ws->getStyle($col . $r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        }
        $ws->getStyle('B' . $r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $ws->getRowDimension($r)->setRowHeight(22);
    }
}
