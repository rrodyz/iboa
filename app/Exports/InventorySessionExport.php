<?php

namespace App\Exports;

use App\Models\Company;
use App\Models\InventorySession;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\RegistersEventListeners;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class InventorySessionExport implements FromArray, WithTitle, WithColumnWidths, WithEvents
{
    use \App\Exports\Concerns\HasCompanyHeader;
    use RegistersEventListeners;

    // Row-type constants
    private const T_DOC_HEADER = 'doc_header';
    private const T_SUBTITLE   = 'subtitle';
    private const T_BLANK      = 'blank';
    private const T_COL_HEADER = 'col_header';
    private const T_DATA       = 'data';
    private const T_POS        = 'pos';
    private const T_NEG        = 'neg';
    private const T_TOTAL      = 'total';

    private array $rows    = [];
    private array $meta    = [];   // 1-indexed → type
    private int   $rowIdx  = 0;

    // Rows that need number formatting (by 1-based row index)
    private array $dataRows = [];

    public function __construct(private InventorySession $session) {}

    public function title(): string
    {
        return 'Inventaire ' . ($this->session->number ?? $this->session->id);
    }

    public function array(): array
    {
        $this->build();
        return $this->rows;
    }

    public function columnWidths(): array
    {
        return [
            'A' => 14,   // Référence
            'B' => 36,   // Désignation
            'C' => 14,   // Stock théorique
            'D' => 14,   // Qté comptée
            'E' => 14,   // Écart
            'F' => 16,   // Coût unitaire
            'G' => 18,   // Valeur écart
            'H' => 28,   // Notes
            'I' => 12,   // Statut
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => fn (AfterSheet $e) => $this->applyStyles($e->sheet->getDelegate()),
        ];
    }

    /* ── Build ─────────────────────────────────────────────────────────────── */

    private function build(): void
    {
        $company = Company::first();
        $date    = now()->format('d/m/Y');
        $time    = now()->format('H\hi');

        $warehouseName = $this->session->warehouse?->name ?? 'Tous entrepôts';
        $statusLabel   = $this->session->statusLabel();
        $typeLabel     = $this->session->typeLabel();

        // ── Row 1 : document header ──────────────────────────────────────────
        $this->push([
            $this->companyNameCell($company),
            null, null, null,
            'INVENTAIRE ' . strtoupper($this->session->number ?? '#' . $this->session->id),
            null, null, null,
            'Édité le ' . $date . ' à ' . $time,
        ], self::T_DOC_HEADER);

        // ── Row 2 : subtitle / session details ───────────────────────────────
        $subtitle = 'Entrepôt : ' . $warehouseName
                  . '   |   Type : ' . $typeLabel
                  . '   |   Statut : ' . $statusLabel
                  . ($this->session->started_at ? '   |   Démarré le : ' . $this->session->started_at->format('d/m/Y') : '');

        $this->push([
            $this->companyLegalLine($company, $subtitle),
            null, null, null, null, null, null, null,
            'Date arrêté : ' . $date,
        ], self::T_SUBTITLE);

        // ── Row 3 : blank ────────────────────────────────────────────────────
        $this->push(array_fill(0, 9, null), self::T_BLANK);

        // ── Row 4 : column headers ───────────────────────────────────────────
        $this->push([
            'Référence',
            'Désignation',
            'Stock théorique',
            'Qté comptée',
            'Écart',
            'Coût unitaire (FCFA)',
            'Valeur écart (FCFA)',
            'Notes',
            'Statut',
        ], self::T_COL_HEADER);

        // ── Data rows ────────────────────────────────────────────────────────
        $items = $this->session->items()->with('product')->orderBy('id')->get();

        $totalTheo        = 0.0;
        $totalCounted     = 0.0;
        $totalVariance    = 0.0;
        $totalVarianceVal = 0.0;

        foreach ($items as $item) {
            $theo     = (float) $item->theoretical_quantity;
            $counted  = $item->counted_quantity !== null ? (float) $item->counted_quantity : null;
            $variance = $counted !== null ? $counted - $theo : null;
            $varValue = $variance !== null ? $variance * (float) $item->unit_cost : null;

            if ($variance === null) {
                $statut   = 'Non compté';
                $rowType  = self::T_DATA;
            } elseif ($variance > 0) {
                $statut  = 'Excédent';
                $rowType = self::T_POS;
            } elseif ($variance < 0) {
                $statut  = 'Manquant';
                $rowType = self::T_NEG;
            } else {
                $statut  = 'Conforme';
                $rowType = self::T_DATA;
            }

            $this->push([
                $item->product?->reference ?? '—',
                $item->product?->name      ?? '—',
                $theo,
                $counted,
                $variance,
                (float) $item->unit_cost > 0 ? (float) $item->unit_cost : null,
                $varValue,
                $item->notes ?? '',
                $statut,
            ], $rowType);

            $this->dataRows[] = $this->rowIdx;

            $totalTheo        += $theo;
            $totalCounted     += $counted ?? 0.0;
            $totalVariance    += $variance ?? 0.0;
            $totalVarianceVal += $varValue ?? 0.0;
        }

        // ── Totals row ───────────────────────────────────────────────────────
        $counted = $items->filter(fn ($i) => $i->counted_quantity !== null)->count();
        $this->push([
            'TOTAUX',
            $items->count() . ' article(s)  |  ' . $counted . ' compté(s)',
            $totalTheo,
            $totalCounted,
            $totalVariance,
            null,
            $totalVarianceVal,
            null,
            null,
        ], self::T_TOTAL);
    }

    private function push(array $cells, string $type): void
    {
        $this->rows[] = $cells;
        $this->rowIdx++;
        $this->meta[$this->rowIdx] = $type;
    }

    /* ── Styles ─────────────────────────────────────────────────────────────── */

    private function applyStyles(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $ws): void
    {
        $numFmt  = '#,##0.00';
        $numFmt0 = '#,##0';

        // Number formats on data rows (cols C-G = columns 3-7)
        foreach ($this->dataRows as $r) {
            foreach (['C', 'D', 'E'] as $col) {
                $ws->getStyle($col . $r)->getNumberFormat()->setFormatCode($numFmt);
            }
            foreach (['F', 'G'] as $col) {
                $ws->getStyle($col . $r)->getNumberFormat()->setFormatCode($numFmt0);
            }
        }

        // Apply styles per row type
        foreach ($this->meta as $r => $type) {
            match ($type) {
                self::T_DOC_HEADER => $this->sDocHeader($ws, $r),
                self::T_SUBTITLE   => $this->sSubtitle($ws, $r),
                self::T_COL_HEADER => $this->sColHeader($ws, $r),
                self::T_DATA       => $this->sData($ws, $r),
                self::T_POS        => $this->sPos($ws, $r),
                self::T_NEG        => $this->sNeg($ws, $r),
                self::T_TOTAL      => $this->sTotal($ws, $r),
                default            => null,
            };
        }

        // Total row number formats
        $totalRow = array_key_last(array_filter($this->meta, fn ($t) => $t === self::T_TOTAL));
        if ($totalRow) {
            foreach (['C', 'D', 'E'] as $col) {
                $ws->getStyle($col . $totalRow)->getNumberFormat()->setFormatCode($numFmt);
            }
            $ws->getStyle('G' . $totalRow)->getNumberFormat()->setFormatCode($numFmt0);
        }

        // Freeze pane below column header row (row 4)
        $ws->freezePane('A5');

        // Print settings
        $ws->getPageSetup()
            ->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE)
            ->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_A4)
            ->setFitToPage(true)
            ->setFitToWidth(1)
            ->setFitToHeight(0);

        $ws->getHeaderFooter()
            ->setOddHeader('&L&BInventaire ' . ($this->session->number ?? $this->session->id) . '&REntrepôt : ' . ($this->session->warehouse?->name ?? '—'));
        $ws->getHeaderFooter()
            ->setOddFooter('&LÉdité le ' . now()->format('d/m/Y') . '&C&P / &N&R&F');

        $ws->setPrintGridlines(false);

        // Company header: enable text wrapping so multi-line address fits
        $this->applyCompanyHeaderWrap($ws, 1, 2);

        // Repeat header rows 1–4 on each printed page
        $ws->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd(1, 4);

        // Colour legend below totals
        $legend = $this->rowIdx + 2;
        $ws->setCellValue('A' . $legend, '■ Vert = Conforme / Excédent');
        $ws->setCellValue('D' . $legend, '■ Rouge = Manquant');
        $ws->setCellValue('G' . $legend, '■ Blanc = Non compté');
        foreach (['A', 'D', 'G'] as [$col]) {
            $ws->getStyle($col . $legend)->getFont()->setSize(8)->setItalic(true);
        }
        $ws->getStyle('A' . $legend)->getFont()->getColor()->setRGB('166534');
        $ws->getStyle('D' . $legend)->getFont()->getColor()->setRGB('991B1B');
        $ws->getStyle('G' . $legend)->getFont()->getColor()->setRGB('6B7280');
    }

    /* ── Per-type style helpers ─────────────────────────────────────────────── */

    private function sDocHeader(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $ws, int $r): void
    {
        $last = 'I';
        $ws->mergeCells('A' . $r . ':D' . $r);
        $ws->mergeCells('E' . $r . ':H' . $r);
        $ws->getStyle('A' . $r . ':' . $last . $r)->applyFromArray([
            'font'      => ['bold' => true, 'size' => 13, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0F766E']],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $ws->getStyle('A' . $r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $ws->getStyle('E' . $r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $ws->getStyle($last . $r)->applyFromArray([
            'font'      => ['size' => 9, 'italic' => true, 'color' => ['rgb' => 'CCFBF1']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0F766E']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $ws->getRowDimension($r)->setRowHeight(26);
    }

    private function sSubtitle(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $ws, int $r): void
    {
        $last = 'I';
        $ws->mergeCells('A' . $r . ':H' . $r);
        $ws->getStyle('A' . $r . ':' . $last . $r)->applyFromArray([
            'font'      => ['italic' => true, 'size' => 9, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '134E4A']],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $ws->getStyle('A' . $r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $ws->getStyle($last . $r)->applyFromArray([
            'font'      => ['size' => 9, 'italic' => true, 'color' => ['rgb' => '99F6E4']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '134E4A']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT],
        ]);
        $ws->getRowDimension($r)->setRowHeight(16);
    }

    private function sColHeader(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $ws, int $r): void
    {
        $ws->getStyle('A' . $r . ':I' . $r)->applyFromArray([
            'font'      => ['bold' => true, 'size' => 9, 'color' => ['rgb' => '134E4A']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'CCFBF1']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'borders'   => [
                'bottom' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => '0F766E']],
                'top'    => ['borderStyle' => Border::BORDER_THIN,   'color' => ['rgb' => '0F766E']],
            ],
        ]);
        foreach (['A', 'B', 'H'] as $col) {
            $ws->getStyle($col . $r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        }
        $ws->getRowDimension($r)->setRowHeight(22);
    }

    private function sData(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $ws, int $r): void
    {
        $ws->getStyle('A' . $r . ':I' . $r)->applyFromArray([
            'font'      => ['size' => 9],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
            'borders'   => ['bottom' => ['borderStyle' => Border::BORDER_HAIR, 'color' => ['rgb' => 'E5E7EB']]],
        ]);
        foreach (['C', 'D', 'E', 'F', 'G'] as $col) {
            $ws->getStyle($col . $r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        }
        $ws->getStyle('I' . $r)->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['rgb' => '166534']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $ws->getRowDimension($r)->setRowHeight(13);
    }

    private function sPos(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $ws, int $r): void
    {
        $ws->getStyle('A' . $r . ':I' . $r)->applyFromArray([
            'font'      => ['size' => 9, 'color' => ['rgb' => '166534']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F0FDF4']],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
            'borders'   => ['bottom' => ['borderStyle' => Border::BORDER_HAIR, 'color' => ['rgb' => 'BBF7D0']]],
        ]);
        foreach (['C', 'D', 'E', 'F', 'G'] as $col) {
            $ws->getStyle($col . $r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        }
        $ws->getStyle('I' . $r)->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['rgb' => '15803D']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $ws->getRowDimension($r)->setRowHeight(13);
    }

    private function sNeg(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $ws, int $r): void
    {
        $ws->getStyle('A' . $r . ':I' . $r)->applyFromArray([
            'font'      => ['size' => 9, 'color' => ['rgb' => '991B1B']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFF5F5']],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
            'borders'   => ['bottom' => ['borderStyle' => Border::BORDER_HAIR, 'color' => ['rgb' => 'FECACA']]],
        ]);
        foreach (['C', 'D', 'E', 'F', 'G'] as $col) {
            $ws->getStyle($col . $r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        }
        $ws->getStyle('I' . $r)->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'DC2626']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $ws->getRowDimension($r)->setRowHeight(13);
    }

    private function sTotal(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $ws, int $r): void
    {
        $ws->mergeCells('B' . $r . ':C' . $r);
        $ws->getStyle('A' . $r . ':I' . $r)->applyFromArray([
            'font'      => ['bold' => true, 'size' => 10, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0F766E']],
            'borders'   => ['top' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => '134E4A']]],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);
        foreach (['C', 'D', 'E', 'G'] as $col) {
            $ws->getStyle($col . $r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        }
        $ws->getStyle('B' . $r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $ws->getRowDimension($r)->setRowHeight(22);
    }
}
