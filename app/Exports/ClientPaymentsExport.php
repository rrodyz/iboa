<?php

namespace App\Exports;

use App\Models\ClientPayment;
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
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class ClientPaymentsExport implements FromArray, WithTitle, WithColumnWidths, WithEvents
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

    private const COLS     = 10; // A–J
    private const LAST_COL = 'J';

    private array $rows   = [];
    private array $meta   = [];
    private int   $rowIdx = 0;

    // tracks data rows for number formatting
    private array $dataRowNums = [];

    public function __construct(private array $filters = [])
    {
        $this->build();
    }

    public function title(): string   { return 'Encaissements'; }
    public function array(): array    { return $this->rows; }

    public function columnWidths(): array
    {
        return [
            'A' => 18, // N° Paiement
            'B' => 28, // Client
            'C' => 14, // Date
            'D' => 20, // Mode paiement
            'E' => 22, // Compte
            'F' => 16, // Montant
            'G' => 16, // Alloué
            'H' => 18, // Non alloué
            'I' => 20, // Référence
            'J' => 14, // Statut
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

        // ── Query ────────────────────────────────────────────────────────────
        $payments = ClientPayment::with(['client', 'paymentMethod', 'cashAccount', 'allocations'])
            ->when(!empty($this->filters['client_id']),         fn($q) => $q->where('client_id',          $this->filters['client_id']))
            ->when(!empty($this->filters['payment_method_id']), fn($q) => $q->where('payment_method_id',  $this->filters['payment_method_id']))
            ->when($dateFrom,                                   fn($q) => $q->whereDate('payment_date',   '>=', $dateFrom))
            ->when($dateTo,                                     fn($q) => $q->whereDate('payment_date',   '<=', $dateTo))
            ->when(!empty($this->filters['search']), fn($q) => $q->where(fn($sq) =>
                $sq->where('number', 'like', '%'.$this->filters['search'].'%')
                   ->orWhereHas('client', fn($c) => $c->where('name', 'like', '%'.$this->filters['search'].'%'))
            ))
            ->orderByDesc('payment_date')
            ->get();

        $totalAmount      = (int) $payments->sum('amount');
        $totalAllocated   = (int) $payments->sum('allocated_amount');
        $totalUnallocated = (int) $payments->sum('unallocated_amount');

        // ── Row 1 : document header ───────────────────────────────────────────
        $this->push(
            [$this->companyNameCell($company), null, null, null, 'ENCAISSEMENTS CLIENTS', null, null, null, null, 'Au '.now()->format('d/m/Y')],
            self::T_HEADER
        );

        // ── Row 2 : period + filters ──────────────────────────────────────────
        $period = ($dateFrom || $dateTo)
            ? 'Période : '.($dateFrom ? \Carbon\Carbon::parse($dateFrom)->format('d/m/Y') : '—')
              .' → '.($dateTo ? \Carbon\Carbon::parse($dateTo)->format('d/m/Y') : "aujourd'hui")
            : 'Toutes les dates';

        $this->push(
            [$this->companyLegalLine($company, $period), null, null, null, null, null, null, null, null, null],
            self::T_PERIOD
        );

        // ── Row 3 : summary totals ────────────────────────────────────────────
        $this->push([
            $payments->count().' encaissement(s)', null, null,
            'Total encaissé :', $totalAmount, null,
            'Non alloué :', $totalUnallocated, null, null,
        ], self::T_TOTALS);

        // ── Row 4 : blank ─────────────────────────────────────────────────────
        $this->push(array_fill(0, self::COLS, null), self::T_BLANK);

        // ── Row 5 : column headers ────────────────────────────────────────────
        $this->push([
            'N° Paiement', 'Client', 'Date paiement', 'Mode de paiement', 'Compte',
            'Montant (FCFA)', 'Alloué (FCFA)', 'Non alloué (FCFA)', 'Référence', 'Statut',
        ], self::T_COLHDR);

        // ── Data rows ─────────────────────────────────────────────────────────
        $statusLabels = [
            'en_attente' => 'En attente',
            'confirme'   => 'Confirmé',
            'rejete'     => 'Rejeté',
            'annule'     => 'Annulé',
        ];

        foreach ($payments as $payment) {
            $this->push([
                $payment->number,
                $payment->client?->name ?? '—',
                $payment->payment_date?->format('d/m/Y'),
                $payment->paymentMethod?->name ?? '—',
                $payment->cashAccount?->name ?? '—',
                (int) $payment->amount,
                (int) $payment->allocated_amount,
                (int) $payment->unallocated_amount,
                $payment->reference ?? '—',
                $statusLabels[$payment->status] ?? $payment->status,
            ], self::T_DATA);

            $this->dataRowNums[] = $this->rowIdx;
        }

        // ── Footer total ──────────────────────────────────────────────────────
        $this->push([
            'TOTAL — '.$payments->count().' encaissement(s)',
            null, null, null, null,
            $totalAmount, $totalAllocated, $totalUnallocated,
            null, null,
        ], self::T_FOOTER);
    }

    private function push(array $cells, string $type): void
    {
        $this->rows[]              = $cells;
        $this->meta[++$this->rowIdx] = $type;
    }

    // -------------------------------------------------------------------------
    // Styles (AfterSheet)
    // -------------------------------------------------------------------------

    private function applyStyles(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $ws): void
    {
        $lastCol = self::LAST_COL;
        $numFmt  = '#,##0';
        $numCols = ['F', 'G', 'H'];

        // Number format on data rows
        foreach ($this->dataRowNums as $r) {
            foreach ($numCols as $c) {
                $ws->getStyle("{$c}{$r}")->getNumberFormat()->setFormatCode($numFmt);
            }
        }

        // Per-row styling
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

        // Freeze pane below header block + column headers
        $ws->freezePane('A6');

        // Print settings
        $ws->getPageSetup()
            ->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE)
            ->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_A4)
            ->setFitToPage(true)->setFitToWidth(1)->setFitToHeight(0);
        $ws->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd(1, 5);
        $ws->getHeaderFooter()
            ->setOddHeader('&L&BEncaissements Clients&R&P / &N');
        $ws->getHeaderFooter()
            ->setOddFooter('&LÉdité le '.now()->format('d/m/Y').'&R&F');

        // Company header text wrapping
        $this->applyCompanyHeaderWrap($ws, 1, 2);
    }

    private function styleHeader(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $ws, int $r, string $lastCol): void
    {
        $ws->mergeCells("A{$r}:C{$r}");
        $ws->mergeCells("D{$r}:H{$r}");
        $ws->mergeCells("I{$r}:{$lastCol}{$r}");

        $ws->getStyle("A{$r}:{$lastCol}{$r}")->applyFromArray([
            'font'      => ['bold' => true, 'size' => 12, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '065F46']],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $ws->getStyle("A{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $ws->getStyle("D{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $ws->getStyle("I{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $ws->getRowDimension($r)->setRowHeight(26);
    }

    private function stylePeriod(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $ws, int $r, string $lastCol): void
    {
        $ws->mergeCells("A{$r}:{$lastCol}{$r}");
        $ws->getStyle("A{$r}:{$lastCol}{$r}")->applyFromArray([
            'font'      => ['italic' => true, 'size' => 9, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '059669']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $ws->getRowDimension($r)->setRowHeight(15);
    }

    private function styleTotals(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $ws, int $r, string $lastCol, string $numFmt): void
    {
        $ws->mergeCells("A{$r}:C{$r}");
        $ws->mergeCells("D{$r}:D{$r}");
        $ws->mergeCells("F{$r}:F{$r}");
        $ws->mergeCells("G{$r}:{$lastCol}{$r}");

        $ws->getStyle("A{$r}:{$lastCol}{$r}")->applyFromArray([
            'font'      => ['bold' => true, 'size' => 9, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '047857']],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $ws->getStyle("A{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        foreach (['D', 'G'] as $c) {
            $ws->getStyle("{$c}{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        }
        foreach (['E', 'H'] as $c) {
            $ws->getStyle("{$c}{$r}")->applyFromArray([
                'font'      => ['bold' => true, 'size' => 10, 'color' => ['rgb' => 'D1FAE5']],
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
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '059669']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'borders'   => [
                'bottom' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => '065F46']],
                'top'    => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => '065F46']],
            ],
        ]);
        foreach (['A', 'B', 'D', 'E', 'I'] as $c) {
            $ws->getStyle("{$c}{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        }
        foreach (['F', 'G', 'H'] as $c) {
            $ws->getStyle("{$c}{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        }
        $ws->getRowDimension($r)->setRowHeight(20);
    }

    private function styleData(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $ws, int $r): void
    {
        $ws->getStyle("A{$r}:J{$r}")->applyFromArray([
            'font'      => ['size' => 9],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
            'borders'   => ['bottom' => ['borderStyle' => Border::BORDER_HAIR, 'color' => ['rgb' => 'E5E7EB']]],
        ]);
        foreach (['F', 'G', 'H'] as $c) {
            $ws->getStyle("{$c}{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        }
        $ws->getStyle('J'.$r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $ws->getRowDimension($r)->setRowHeight(14);
    }

    private function styleFooter(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $ws, int $r, string $lastCol, string $numFmt): void
    {
        $ws->mergeCells("A{$r}:E{$r}");
        $ws->getStyle("A{$r}:{$lastCol}{$r}")->applyFromArray([
            'font'      => ['bold' => true, 'size' => 10, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '065F46']],
            'borders'   => ['top' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => '065F46']]],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $ws->getStyle("A{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        foreach (['F', 'G', 'H'] as $c) {
            $ws->getStyle("{$c}{$r}")->applyFromArray([
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT],
            ]);
            $ws->getStyle("{$c}{$r}")->getNumberFormat()->setFormatCode($numFmt);
        }
        $ws->getRowDimension($r)->setRowHeight(20);
    }
}
