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

class SalesPerformanceExport implements FromArray, WithTitle, WithColumnWidths, WithEvents
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

    private const COLS     = 7; // A–G
    private const LAST_COL = 'G';
    private const COLOR    = '7C3AED';

    private array $rows   = [];
    private array $meta   = [];
    private int   $rowIdx = 0;

    private array $dataRowNums = [];

    public function __construct(
        private Collection $perUser,
        private string $from,
        private string $to
    ) {
        $this->build();
    }

    public function title(): string { return 'Performance '.$this->from.' au '.$this->to; }
    public function array(): array  { return $this->rows; }

    public function columnWidths(): array
    {
        return [
            'A' => 26, // Commercial
            'B' => 14, // Nb Factures
            'C' => 14, // Nb Clients
            'D' => 20, // CA Total (FCFA)
            'E' => 20, // Encaissé (FCFA)
            'F' => 18, // Reste (FCFA)
            'G' => 20, // Panier moyen (FCFA)
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

        $totalNbFact   = (int) $this->perUser->sum('nb_factures');
        $totalNbClients = (int) $this->perUser->sum('nb_clients');
        $totalCa       = (int) $this->perUser->sum('ca_total');
        $totalEncaisse = (int) $this->perUser->sum('encaisse');

        // ── Row 1 : document header ───────────────────────────────────────────
        $this->push(
            [$this->companyNameCell($company), null, null, 'PERFORMANCE COMMERCIALE', null, null, 'Au '.now()->format('d/m/Y')],
            self::T_HEADER
        );

        // ── Row 2 : period ────────────────────────────────────────────────────
        $periodText = 'Période : '.$this->from.' → '.$this->to;
        $this->push(
            [$this->companyLegalLine($company, $periodText), null, null, null, null, null, null],
            self::T_PERIOD
        );

        // ── Row 3 : summary totals ────────────────────────────────────────────
        $this->push([
            $totalNbFact.' facture(s)',
            null,
            null,
            'CA total : '.$totalCa,
            'Encaissé : '.$totalEncaisse,
            null,
            null,
        ], self::T_TOTALS);

        // ── Row 4 : blank ─────────────────────────────────────────────────────
        $this->push(array_fill(0, self::COLS, null), self::T_BLANK);

        // ── Row 5 : column headers ────────────────────────────────────────────
        $this->push([
            'Commercial', 'Nb Factures', 'Nb Clients', 'CA Total (FCFA)', 'Encaissé (FCFA)', 'Reste (FCFA)', 'Panier moyen (FCFA)',
        ], self::T_COLHDR);

        // ── Data rows ─────────────────────────────────────────────────────────
        foreach ($this->perUser as $row) {
            $this->push([
                optional($row->creator)->name ?? 'Utilisateur #'.$row->created_by,
                $row->nb_factures,
                $row->nb_clients,
                (int) $row->ca_total,
                (int) $row->encaisse,
                (int) $row->reste,
                (int) $row->panier_moyen,
            ], self::T_DATA);

            $this->dataRowNums[] = $this->rowIdx;
        }

        // ── Footer total ──────────────────────────────────────────────────────
        $this->push([
            'TOTAL — '.$this->perUser->count().' commercial(aux)',
            $totalNbFact,
            $totalNbClients,
            $totalCa,
            $totalEncaisse,
            null,
            null,
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
        $numCols = ['D', 'E', 'F', 'G'];

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
        $ws->getHeaderFooter()->setOddHeader('&L&BPerformance Commerciale&R&P / &N');
        $ws->getHeaderFooter()->setOddFooter('&LÉdité le '.now()->format('d/m/Y').'&R&F');

        // Company header text wrapping
        $this->applyCompanyHeaderWrap($ws, 1, 2);
    }

    private function styleHeader(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $ws, int $r, string $lastCol): void
    {
        $ws->mergeCells("A{$r}:B{$r}");
        $ws->mergeCells("C{$r}:E{$r}");
        $ws->mergeCells("F{$r}:{$lastCol}{$r}");

        $ws->getStyle("A{$r}:{$lastCol}{$r}")->applyFromArray([
            'font'      => ['bold' => true, 'size' => 12, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::COLOR]],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $ws->getStyle("A{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $ws->getStyle("C{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $ws->getStyle("F{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $ws->getRowDimension($r)->setRowHeight(26);
    }

    private function stylePeriod(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $ws, int $r, string $lastCol): void
    {
        $ws->mergeCells("A{$r}:{$lastCol}{$r}");
        $ws->getStyle("A{$r}:{$lastCol}{$r}")->applyFromArray([
            'font'      => ['italic' => true, 'size' => 9, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '6D28D9']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $ws->getRowDimension($r)->setRowHeight(15);
    }

    private function styleTotals(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $ws, int $r, string $lastCol, string $numFmt): void
    {
        $ws->mergeCells("A{$r}:C{$r}");
        $ws->mergeCells("F{$r}:{$lastCol}{$r}");

        $ws->getStyle("A{$r}:{$lastCol}{$r}")->applyFromArray([
            'font'      => ['bold' => true, 'size' => 9, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '5B21B6']],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $ws->getStyle("A{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        foreach (['D', 'E'] as $c) {
            $ws->getStyle("{$c}{$r}")->applyFromArray([
                'font'      => ['bold' => true, 'size' => 10, 'color' => ['rgb' => 'EDE9FE']],
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
                'bottom' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => '5B21B6']],
                'top'    => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => '5B21B6']],
            ],
        ]);
        $ws->getStyle("A{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        foreach (['B', 'C', 'D', 'E', 'F', 'G'] as $c) {
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
        foreach (['B', 'C', 'D', 'E', 'F', 'G'] as $c) {
            $ws->getStyle("{$c}{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        }
        $ws->getRowDimension($r)->setRowHeight(14);
    }

    private function styleFooter(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $ws, int $r, string $lastCol, string $numFmt): void
    {
        $ws->mergeCells("A{$r}:A{$r}");
        $ws->getStyle("A{$r}:{$lastCol}{$r}")->applyFromArray([
            'font'      => ['bold' => true, 'size' => 10, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4C1D95']],
            'borders'   => ['top' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => '5B21B6']]],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $ws->getStyle("A{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        foreach (['D', 'E', 'F', 'G'] as $c) {
            $ws->getStyle("{$c}{$r}")->applyFromArray([
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT],
            ]);
            $ws->getStyle("{$c}{$r}")->getNumberFormat()->setFormatCode($numFmt);
        }
        $ws->getRowDimension($r)->setRowHeight(20);
    }
}
