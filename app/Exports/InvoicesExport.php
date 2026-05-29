<?php

namespace App\Exports;

use App\Models\Company;
use App\Models\Invoice;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\RegistersEventListeners;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class InvoicesExport implements FromArray, WithTitle, WithColumnWidths, WithEvents
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
        'brouillon'           => 'Brouillon',
        'emise'               => 'Émise',
        'envoyee'             => 'Envoyée',
        'partiellement_payee' => 'Partiellement payée',
        'payee'               => 'Payée',
        'en_retard'           => 'En retard',
        'annulee'             => 'Annulée',
    ];

    private array $rows     = [];
    private array $meta     = [];
    private int   $rowIdx   = 0;
    private array $dataRows = [];

    public function __construct(private array $filters = []) {
        $this->build();
    }

    public function title(): string { return 'Factures'; }

    public function array(): array { return $this->rows; }

    public function columnWidths(): array
    {
        return [
            'A' => 14,   // N° Facture
            'B' => 30,   // Client
            'C' => 14,   // Date émission
            'D' => 14,   // Date échéance
            'E' => 20,   // Statut
            'F' => 18,   // Total HT
            'G' => 16,   // TVA
            'H' => 18,   // Total TTC
            'I' => 18,   // Montant payé
            'J' => 18,   // Reste à payer
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
            ['LISTE DES FACTURES'],
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
            'N° Facture', 'Client', 'Date émission', 'Date échéance', 'Statut',
            'Total HT (FCFA)', 'TVA (FCFA)', 'Total TTC (FCFA)',
            'Montant payé (FCFA)', 'Reste à payer (FCFA)', 'Créé par',
        ], self::T_COL_HEADER);

        // ── Data ─────────────────────────────────────────────────────────────
        $invoices = $this->loadInvoices();

        $totHt    = 0;
        $totTva   = 0;
        $totTtc   = 0;
        $totPaid  = 0;
        $totRest  = 0;

        foreach ($invoices as $invoice) {
            $ht   = (int) $invoice->subtotal_ht;
            $tva  = (int) $invoice->total_tax;
            $ttc  = (int) $invoice->total_ttc;
            $paid = (int) $invoice->paid_amount;
            $rest = (int) $invoice->remaining_amount;

            $this->push([
                $invoice->number,
                $invoice->client?->name ?? '—',
                $invoice->issued_at?->format('d/m/Y'),
                $invoice->due_at?->format('d/m/Y') ?? '—',
                self::STATUS_LABELS[$invoice->status] ?? $invoice->status,
                $ht   ?: null,
                $tva  ?: null,
                $ttc  ?: null,
                $paid ?: null,
                $rest ?: null,
                $invoice->createdBy?->name ?? '—',
            ], self::T_DATA);

            $this->dataRows[] = $this->rowIdx;

            $totHt   += $ht;
            $totTva  += $tva;
            $totTtc  += $ttc;
            $totPaid += $paid;
            $totRest += $rest;
        }

        // ── Totals row ───────────────────────────────────────────────────────
        $this->push([
            'TOTAL', null, null, null, null,
            $totHt   ?: null,
            $totTva  ?: null,
            $totTtc  ?: null,
            $totPaid ?: null,
            $totRest ?: null,
            null,
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
        return $parts ? implode('  •  ', $parts) : 'Toutes les factures';
    }

    private function loadInvoices()
    {
        $q = Invoice::with(['client', 'createdBy'])->orderByDesc('issued_at');

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
        if (!empty($this->filters['overdue'])) {
            $q->overdue();
        }

        return $q->lazy(1_000);
    }

    /* ── Styles ───────────────────────────────────────────────────────────── */

    private function applyStyles(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $ws): void
    {
        $numFmt  = '#,##0';
        $numCols = ['F', 'G', 'H', 'I', 'J'];

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

        // Number format on total row
        $totalRow = array_key_last(array_filter($this->meta, fn($t) => $t === self::T_TOTAL));
        if ($totalRow) {
            foreach ($numCols as $col) {
                $ws->getStyle($col . $totalRow)->getNumberFormat()->setFormatCode($numFmt);
            }
        }

        // Freeze pane
        $ws->freezePane('A5');

        // Print settings
        $ws->getPageSetup()
            ->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE)
            ->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_A4)
            ->setFitToPage(true)
            ->setFitToWidth(1)
            ->setFitToHeight(0);

        $ws->getHeaderFooter()
            ->setOddHeader('&L&BListe des Factures&R&P / &N');
        $ws->getHeaderFooter()
            ->setOddFooter('&LÉdité le ' . now()->format('d/m/Y') . '&R&F');

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
            'font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E3A5F']],
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
        foreach (['F', 'G', 'H', 'I', 'J'] as $col) {
            $ws->getStyle($col . $r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        }
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
        foreach (['F', 'G', 'H', 'I', 'J'] as $col) {
            $ws->getStyle($col . $r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        }
        $ws->getRowDimension($r)->setRowHeight(22);
    }
}
