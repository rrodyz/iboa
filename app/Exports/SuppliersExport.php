<?php

namespace App\Exports;

use App\Models\Company;
use App\Models\Supplier;
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

class SuppliersExport implements FromArray, WithTitle, WithColumnWidths, WithEvents
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

    private const COLS     = 16; // A–P
    private const LAST_COL = 'P';
    private const COLOR    = 'E97317';

    private array $rows   = [];
    private array $meta   = [];
    private int   $rowIdx = 0;

    private array $dataRowNums = [];

    public function __construct(private array $filters = [])
    {
        $this->build();
    }

    public function title(): string { return 'Fournisseurs'; }
    public function array(): array  { return $this->rows; }

    public function columnWidths(): array
    {
        return [
            'A' => 14, // Code
            'B' => 28, // Nom
            'C' => 26, // Email
            'D' => 16, // Téléphone
            'E' => 16, // Mobile/Tél.2
            'F' => 28, // Adresse
            'G' => 16, // Ville
            'H' => 14, // Pays
            'I' => 14, // IFU
            'J' => 16, // RCCM
            'K' => 22, // Site web
            'L' => 12, // Note (/5)
            'M' => 16, // Délai moyen (j)
            'N' => 18, // Solde dû
            'O' => 12, // Statut
            'P' => 14, // Créé le
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
        $company = currentCompany();

        $suppliers = Supplier::orderBy('name')
            ->when(!empty($this->filters['search']), function ($q) {
                $s = '%' . $this->filters['search'] . '%';
                $q->where(fn($q2) =>
                    $q2->where('name', 'like', $s)
                       ->orWhere('code', 'like', $s)
                       ->orWhere('email', 'like', $s)
                );
            })
            ->when(
                isset($this->filters['is_active']) && $this->filters['is_active'] !== '',
                fn($q) => $q->where('is_active', (bool) $this->filters['is_active'])
            )
            ->get();

        $totalSolde = (int) $suppliers->sum('balance');

        // ── Row 1 : document header ───────────────────────────────────────────
        $this->push(
            [$this->companyNameCell($company), null, null, null, null, null, null, 'RÉPERTOIRE FOURNISSEURS', null, null, null, null, null, null, null, 'Au '.now()->format('d/m/Y')],
            self::T_HEADER
        );

        // ── Row 2 : period + filters ──────────────────────────────────────────
        $parts = ['Export du répertoire fournisseurs'];
        if (!empty($this->filters['search'])) {
            $parts[] = 'Recherche : "'.$this->filters['search'].'"';
        }
        if (isset($this->filters['is_active']) && $this->filters['is_active'] !== '') {
            $parts[] = $this->filters['is_active'] ? 'Actifs seulement' : 'Inactifs seulement';
        }
        $period = implode(' | ', $parts);

        $this->push(
            [$this->companyLegalLine($company, $period), null, null, null, null, null, null, null, null, null, null, null, null, null, null, null],
            self::T_PERIOD
        );

        // ── Row 3 : summary totals ────────────────────────────────────────────
        $this->push([
            $suppliers->count().' fournisseur(s)', null, null, null, null, null, null,
            null, null, null, null, null, null,
            'Solde total dû :', $totalSolde, null,
        ], self::T_TOTALS);

        // ── Row 4 : blank ─────────────────────────────────────────────────────
        $this->push(array_fill(0, self::COLS, null), self::T_BLANK);

        // ── Row 5 : column headers ────────────────────────────────────────────
        $this->push([
            'Code', 'Nom', 'Email', 'Téléphone', 'Mobile / Tél. 2', 'Adresse',
            'Ville', 'Pays', 'IFU', 'RCCM', 'Site web', 'Note (/5)',
            'Délai moyen (j)', 'Solde dû (FCFA)', 'Statut', 'Créé le',
        ], self::T_COLHDR);

        // ── Data rows ─────────────────────────────────────────────────────────
        foreach ($suppliers as $supplier) {
            $this->push([
                $supplier->code ?? '—',
                $supplier->name,
                $supplier->email ?? '—',
                $supplier->phone ?? '—',
                $supplier->phone2 ?? '—',
                $supplier->address ?? '—',
                $supplier->city ?? '—',
                $supplier->country ?? '—',
                $supplier->ifu ?? '—',
                $supplier->rccm ?? '—',
                $supplier->website ?? '—',
                $supplier->rating ?? '—',
                $supplier->avg_delivery_days ?? '—',
                (int) ($supplier->balance ?? 0),
                $supplier->is_active ? 'Actif' : 'Inactif',
                $supplier->created_at->format('d/m/Y'),
            ], self::T_DATA);

            $this->dataRowNums[] = $this->rowIdx;
        }

        // ── Footer total ──────────────────────────────────────────────────────
        $this->push([
            'TOTAL — '.$suppliers->count().' fournisseur(s)',
            null, null, null, null, null, null,
            null, null, null, null, null, null,
            $totalSolde, null, null,
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
        $numCols = ['N'];

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
        $ws->getHeaderFooter()->setOddHeader('&L&BRépertoire Fournisseurs&R&P / &N');
        $ws->getHeaderFooter()->setOddFooter('&LÉdité le '.now()->format('d/m/Y').'&R&F');

        // Company header text wrapping
        $this->applyCompanyHeaderWrap($ws, 1, 2);
    }

    private function styleHeader(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $ws, int $r, string $lastCol): void
    {
        $ws->mergeCells("A{$r}:C{$r}");
        $ws->mergeCells("D{$r}:M{$r}");
        $ws->mergeCells("N{$r}:{$lastCol}{$r}");

        $ws->getStyle("A{$r}:{$lastCol}{$r}")->applyFromArray([
            'font'      => ['bold' => true, 'size' => 12, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::COLOR]],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $ws->getStyle("A{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $ws->getStyle("D{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $ws->getStyle("N{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $ws->getRowDimension($r)->setRowHeight(26);
    }

    private function stylePeriod(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $ws, int $r, string $lastCol): void
    {
        $ws->mergeCells("A{$r}:{$lastCol}{$r}");
        $ws->getStyle("A{$r}:{$lastCol}{$r}")->applyFromArray([
            'font'      => ['italic' => true, 'size' => 9, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'C2610C']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $ws->getRowDimension($r)->setRowHeight(15);
    }

    private function styleTotals(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $ws, int $r, string $lastCol, string $numFmt): void
    {
        $ws->mergeCells("A{$r}:M{$r}");
        $ws->mergeCells("O{$r}:{$lastCol}{$r}");

        $ws->getStyle("A{$r}:{$lastCol}{$r}")->applyFromArray([
            'font'      => ['bold' => true, 'size' => 9, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'A34A08']],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $ws->getStyle("A{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $ws->getStyle("O{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $ws->getStyle("N{$r}")->applyFromArray([
            'font'      => ['bold' => true, 'size' => 10, 'color' => ['rgb' => 'FFEDD5']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT],
        ]);
        $ws->getStyle("N{$r}")->getNumberFormat()->setFormatCode($numFmt);
        $ws->getRowDimension($r)->setRowHeight(17);
    }

    private function styleColHeader(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $ws, int $r, string $lastCol): void
    {
        $ws->getStyle("A{$r}:{$lastCol}{$r}")->applyFromArray([
            'font'      => ['bold' => true, 'size' => 9, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::COLOR]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'borders'   => [
                'bottom' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => 'A34A08']],
                'top'    => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => 'A34A08']],
            ],
        ]);
        foreach (['A', 'B', 'C', 'F', 'G', 'H', 'I', 'J', 'K'] as $c) {
            $ws->getStyle("{$c}{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        }
        $ws->getStyle("N{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $ws->getRowDimension($r)->setRowHeight(20);
    }

    private function styleData(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $ws, int $r): void
    {
        $ws->getStyle("A{$r}:".self::LAST_COL."{$r}")->applyFromArray([
            'font'      => ['size' => 9],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
            'borders'   => ['bottom' => ['borderStyle' => Border::BORDER_HAIR, 'color' => ['rgb' => 'E5E7EB']]],
        ]);
        foreach (['L', 'M', 'N'] as $c) {
            $ws->getStyle("{$c}{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        }
        $ws->getStyle('O'.$r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $ws->getRowDimension($r)->setRowHeight(14);
    }

    private function styleFooter(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $ws, int $r, string $lastCol, string $numFmt): void
    {
        $ws->mergeCells("A{$r}:M{$r}");
        $ws->getStyle("A{$r}:{$lastCol}{$r}")->applyFromArray([
            'font'      => ['bold' => true, 'size' => 10, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '7C2D00']],
            'borders'   => ['top' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => 'A34A08']]],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $ws->getStyle("A{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $ws->getStyle("N{$r}")->applyFromArray([
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT],
        ]);
        $ws->getStyle("N{$r}")->getNumberFormat()->setFormatCode($numFmt);
        $ws->getRowDimension($r)->setRowHeight(20);
    }
}
