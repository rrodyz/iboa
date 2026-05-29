<?php

namespace App\Exports\Sales;

use App\Models\Company;
use App\Models\Quote;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\RegistersEventListeners;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class QuoteExport implements FromArray, WithTitle, WithColumnWidths, WithEvents
{
    use \App\Exports\Concerns\HasCompanyHeader;
    use RegistersEventListeners;

    private const T_DOC_HEADER = 'doc_header';
    private const T_PERIOD     = 'period';
    private const T_BLANK      = 'blank';
    private const T_COL_HEADER = 'col_header';
    private const T_DATA       = 'data';
    private const T_TOTAL      = 'total';

    private const STATUS_LABELS = [
        'brouillon' => 'Brouillon',
        'envoye'    => 'Envoyé',
        'accepte'   => 'Accepté',
        'refuse'    => 'Refusé',
        'expire'    => 'Expiré',
        'annule'    => 'Annulé',
    ];

    // Status → [background RGB, font RGB]
    private const STATUS_COLORS = [
        'brouillon' => ['F3F4F6', '374151'],
        'envoye'    => ['DBEAFE', '1D4ED8'],
        'accepte'   => ['DCFCE7', '15803D'],
        'refuse'    => ['FEE2E2', 'B91C1C'],
        'expire'    => ['FFEDD5', 'C2410C'],
        'annule'    => ['F3E8FF', '7E22CE'],
    ];

    private array $rows       = [];
    private array $meta       = [];
    private int   $rowIdx     = 0;
    private array $dataRows   = [];
    private array $statusRows = []; // rowIdx → status

    public function __construct(private array $filters = [])
    {
        $this->build();
    }

    public function title(): string { return 'Devis'; }

    public function array(): array { return $this->rows; }

    public function columnWidths(): array
    {
        return [
            'A' => 14,   // N° Devis
            'B' => 18,   // Référence
            'C' => 30,   // Client
            'D' => 14,   // Date émission
            'E' => 14,   // Date validité
            'F' => 18,   // Total HT
            'G' => 16,   // Remise
            'H' => 16,   // TVA
            'I' => 18,   // Total TTC
            'J' => 16,   // Statut
            'K' => 20,   // Créé par
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => fn(AfterSheet $e) => $this->applyStyles($e->sheet->getDelegate()),
        ];
    }

    /* ── Builder ──────────────────────────────────────────────────────────── */

    private function build(): void
    {
        $company = currentCompany();
        $cols    = 11;

        // ── Document header ──────────────────────────────────────────────────
        $this->push(array_merge(
            [$this->companyNameCell($company)],
            array_fill(0, 4, null),
            ['LISTE DES DEVIS'],
            array_fill(0, 3, null),
            ['Période : ' . $this->periodLabel()],
            [null],
        ), self::T_DOC_HEADER);

        $this->push(array_merge(
            [$this->companyLegalLine($company, $this->subTitle())],
            array_fill(0, 8, null),
            ['Édition du ' . now()->format('d/m/Y à H\hi')],
            [null],
        ), self::T_PERIOD);

        $this->push(array_fill(0, $cols, null), self::T_BLANK);

        // ── Column headers ───────────────────────────────────────────────────
        $this->push([
            'N° Devis', 'Référence', 'Client', 'Date émission', 'Date validité',
            'Total HT (FCFA)', 'Remise (FCFA)', 'TVA (FCFA)', 'Total TTC (FCFA)',
            'Statut', 'Créé par',
        ], self::T_COL_HEADER);

        // ── Data ─────────────────────────────────────────────────────────────
        $quotes = $this->loadQuotes();

        $totHt      = 0;
        $totRemise  = 0;
        $totTva     = 0;
        $totTtc     = 0;

        foreach ($quotes as $quote) {
            $ht     = (int) $quote->subtotal_ht;
            $remise = (int) $quote->total_discount;
            $tva    = (int) $quote->total_tax;
            $ttc    = (int) $quote->total_ttc;

            $this->push([
                $quote->number,
                $quote->reference ?? '',
                $quote->client?->name ?? '—',
                $quote->issued_at?->format('d/m/Y') ?? '—',
                $quote->expires_at?->format('d/m/Y') ?? '—',
                $ht    ?: null,
                $remise ?: null,
                $tva   ?: null,
                $ttc   ?: null,
                self::STATUS_LABELS[$quote->status] ?? $quote->status,
                $quote->createdBy?->name ?? '—',
            ], self::T_DATA);

            $this->dataRows[]                   = $this->rowIdx;
            $this->statusRows[$this->rowIdx]    = $quote->status;

            $totHt     += $ht;
            $totRemise += $remise;
            $totTva    += $tva;
            $totTtc    += $ttc;
        }

        // ── Totals row ───────────────────────────────────────────────────────
        $this->push([
            'TOTAL', null, null, null, null,
            $totHt     ?: null,
            $totRemise ?: null,
            $totTva    ?: null,
            $totTtc    ?: null,
            null, null,
        ], self::T_TOTAL);
    }

    private function push(array $cells, string $type): void
    {
        $this->rows[] = $cells;
        $this->rowIdx++;
        $this->meta[$this->rowIdx] = $type;
    }

    private function periodLabel(): string
    {
        $from = !empty($this->filters['date_from']) ? date('d/m/Y', strtotime($this->filters['date_from'])) : '—';
        $to   = !empty($this->filters['date_to'])   ? date('d/m/Y', strtotime($this->filters['date_to']))   : '—';
        return 'du ' . $from . ' au ' . $to;
    }

    private function subTitle(): string
    {
        $parts = [];
        if (!empty($this->filters['status'])) {
            $parts[] = 'Statut : ' . (self::STATUS_LABELS[$this->filters['status']] ?? $this->filters['status']);
        }
        if (!empty($this->filters['search'])) {
            $parts[] = 'Recherche : "' . $this->filters['search'] . '"';
        }
        return $parts ? implode('  •  ', $parts) : 'Tous les devis';
    }

    private function loadQuotes()
    {
        $q = Quote::with(['client', 'createdBy'])->orderByDesc('issued_at')->orderByDesc('id');

        if (!empty($this->filters['status'])) {
            $q->where('status', $this->filters['status']);
        }
        if (!empty($this->filters['client_id'])) {
            $q->where('client_id', $this->filters['client_id']);
        }
        if (!empty($this->filters['date_from'])) {
            $q->whereDate('issued_at', '>=', $this->filters['date_from']);
        }
        if (!empty($this->filters['date_to'])) {
            $q->whereDate('issued_at', '<=', $this->filters['date_to']);
        }
        if (!empty($this->filters['search'])) {
            $s = '%' . $this->filters['search'] . '%';
            $q->where(fn($q2) =>
                $q2->where('number', 'like', $s)
                   ->orWhereHas('client', fn($qc) =>
                       $qc->where('name', 'like', $s)
                          ->orWhere('trade_name', 'like', $s))
            );
        }

        return $q->lazy(1_000);
    }

    /* ── Styles ───────────────────────────────────────────────────────────── */

    private function applyStyles(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $ws): void
    {
        $numFmt  = '#,##0';
        $numCols = ['F', 'G', 'H', 'I'];

        // Number format on data rows
        foreach ($this->dataRows as $r) {
            foreach ($numCols as $col) {
                $ws->getStyle($col . $r)->getNumberFormat()->setFormatCode($numFmt);
            }
        }

        // Per-row styling
        foreach ($this->meta as $r => $type) {
            match ($type) {
                self::T_DOC_HEADER => $this->sDocHeader($ws, $r),
                self::T_PERIOD     => $this->sPeriod($ws, $r),
                self::T_COL_HEADER => $this->sColHeader($ws, $r),
                self::T_DATA       => $this->sData($ws, $r),
                self::T_TOTAL      => $this->sTotal($ws, $r),
                default            => null,
            };
        }

        // Status cell coloring on data rows
        foreach ($this->statusRows as $r => $status) {
            $colors = self::STATUS_COLORS[$status] ?? ['F9FAFB', '374151'];
            $ws->getStyle('J' . $r)->applyFromArray([
                'font' => ['bold' => true, 'size' => 9, 'color' => ['rgb' => $colors[1]]],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $colors[0]]],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ]);
        }

        // Number format on total row
        $totalRow = array_key_last(array_filter($this->meta, fn($t) => $t === self::T_TOTAL));
        if ($totalRow) {
            foreach ($numCols as $col) {
                $ws->getStyle($col . $totalRow)->getNumberFormat()->setFormatCode($numFmt);
            }
        }

        // Freeze pane under headers
        $ws->freezePane('A5');

        // Print settings
        $ws->getPageSetup()
            ->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE)
            ->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_A4)
            ->setFitToPage(true)
            ->setFitToWidth(1)
            ->setFitToHeight(0);

        $ws->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd(1, 4);

        $ws->getHeaderFooter()
            ->setOddHeader('&L&BListe des Devis&R&P / &N');
        $ws->getHeaderFooter()
            ->setOddFooter('&LÉdité le ' . now()->format('d/m/Y') . '&R&F');

        $ws->setPrintGridlines(false);

        // Company header text wrapping
        $this->applyCompanyHeaderWrap($ws, 1, 2);
    }

    /* ── Style helpers ────────────────────────────────────────────────────── */

    private function sDocHeader($ws, int $r): void
    {
        $ws->mergeCells('A' . $r . ':E' . $r);
        $ws->mergeCells('F' . $r . ':I' . $r);

        $ws->getStyle('A' . $r . ':K' . $r)->applyFromArray([
            'font'      => ['bold' => true, 'size' => 12, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E3A5F']],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $ws->getStyle('A' . $r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $ws->getStyle('F' . $r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $ws->getStyle('J' . $r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $ws->getStyle('K' . $r)->applyFromArray([
            'font'      => ['bold' => true, 'size' => 10, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E3A5F']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $ws->getRowDimension($r)->setRowHeight(24);
    }

    private function sPeriod($ws, int $r): void
    {
        $ws->mergeCells('A' . $r . ':I' . $r);
        $ws->getStyle('A' . $r . ':K' . $r)->applyFromArray([
            'font'      => ['italic' => true, 'size' => 9, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2D5986']],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $ws->getStyle('A' . $r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $ws->getStyle('J' . $r)->applyFromArray([
            'font'      => ['italic' => true, 'size' => 9, 'color' => ['rgb' => 'D1D5DB']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2D5986']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT],
        ]);
        $ws->getStyle('K' . $r)->applyFromArray([
            'font' => ['italic' => true, 'size' => 9, 'color' => ['rgb' => 'D1D5DB']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2D5986']],
        ]);
        $ws->getRowDimension($r)->setRowHeight(16);
    }

    private function sColHeader($ws, int $r): void
    {
        $ws->getStyle('A' . $r . ':K' . $r)->applyFromArray([
            'font'      => ['bold' => true, 'size' => 9, 'color' => ['rgb' => '1E3A5F']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E0E7FF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'borders'   => [
                'bottom' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '6366F1']],
                'top'    => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '6366F1']],
            ],
        ]);
        $ws->getStyle('A' . $r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $ws->getStyle('B' . $r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $ws->getStyle('C' . $r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $ws->getStyle('K' . $r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $ws->getRowDimension($r)->setRowHeight(18);
    }

    private function sData($ws, int $r): void
    {
        $ws->getStyle('A' . $r . ':K' . $r)->applyFromArray([
            'font'      => ['size' => 9],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
            'borders'   => [
                'bottom' => ['borderStyle' => Border::BORDER_HAIR, 'color' => ['rgb' => 'E5E7EB']],
            ],
        ]);
        foreach (['F', 'G', 'H', 'I'] as $col) {
            $ws->getStyle($col . $r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        }
        $ws->getStyle('J' . $r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $ws->getRowDimension($r)->setRowHeight(13);
    }

    private function sTotal($ws, int $r): void
    {
        $ws->mergeCells('A' . $r . ':E' . $r);
        $ws->getStyle('A' . $r . ':K' . $r)->applyFromArray([
            'font'      => ['bold' => true, 'size' => 11, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E3A5F']],
            'borders'   => [
                'top' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => '6366F1']],
            ],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $ws->getStyle('A' . $r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        foreach (['F', 'G', 'H', 'I'] as $col) {
            $ws->getStyle($col . $r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        }
        $ws->getRowDimension($r)->setRowHeight(22);
    }
}
