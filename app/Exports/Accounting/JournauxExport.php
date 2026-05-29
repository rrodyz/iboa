<?php

namespace App\Exports\Accounting;

use App\Models\Company;
use App\Models\JournalEntry;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\RegistersEventListeners;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class JournauxExport implements FromArray, WithTitle, WithColumnWidths, WithEvents
{
    use \App\Exports\Concerns\HasCompanyHeader;
    use RegistersEventListeners;

    private const T_DOC_HEADER = 'doc_header';
    private const T_PERIOD     = 'period';
    private const T_BLANK      = 'blank';
    private const T_COL_HEADER = 'col_header';
    private const T_DATA       = 'data';
    private const T_TOTAL      = 'total';

    // Status → [background RGB, font RGB]
    private const STATUS_COLORS = [
        'brouillon' => ['F3F4F6', '374151'],
        'valide'    => ['DCFCE7', '15803D'],
        'cloture'   => ['EDE9FE', '6D28D9'],
    ];

    private const STATUS_LABELS = [
        'brouillon' => 'Brouillon',
        'valide'    => 'Validé',
        'cloture'   => 'Clôturé',
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

    public function title(): string { return 'Journal comptable'; }

    public function array(): array { return $this->rows; }

    public function columnWidths(): array
    {
        return [
            'A' => 16,   // Numéro
            'B' => 10,   // Journal
            'C' => 13,   // Date
            'D' => 20,   // Référence
            'E' => 48,   // Libellé
            'F' => 14,   // Statut
            'G' => 18,   // Total Débit
            'H' => 18,   // Total Crédit
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
        $cols    = 8;

        // ── Document header ──────────────────────────────────────────────────
        $this->push([
            $this->companyNameCell($company),
            null, null,
            'JOURNAL COMPTABLE',
            null, null,
            'Période : ' . $this->periodLabel(),
            null,
        ], self::T_DOC_HEADER);

        $this->push([
            $this->companyLegalLine($company, $this->subTitle()),
            null, null, null, null, null,
            'Édition du ' . now()->format('d/m/Y à H\hi'),
            null,
        ], self::T_PERIOD);

        $this->push(array_fill(0, $cols, null), self::T_BLANK);

        // ── Column headers ───────────────────────────────────────────────────
        $this->push([
            'Numéro', 'Journal', 'Date', 'Référence', 'Libellé',
            'Statut', 'Total Débit (FCFA)', 'Total Crédit (FCFA)',
        ], self::T_COL_HEADER);

        // ── Data ─────────────────────────────────────────────────────────────
        $entries = $this->loadEntries();

        $totDebit  = 0;
        $totCredit = 0;

        foreach ($entries as $entry) {
            $debit  = (int) $entry->total_debit;
            $credit = (int) $entry->total_credit;

            $this->push([
                $entry->number,
                $entry->journalType?->code ?? '—',
                $entry->entry_date?->format('d/m/Y') ?? '—',
                $entry->reference ?? '',
                $entry->description ?? '',
                self::STATUS_LABELS[$entry->status] ?? $entry->status,
                $debit  ?: null,
                $credit ?: null,
            ], self::T_DATA);

            $this->dataRows[]                = $this->rowIdx;
            $this->statusRows[$this->rowIdx] = $entry->status;

            $totDebit  += $debit;
            $totCredit += $credit;
        }

        // ── Totals row ───────────────────────────────────────────────────────
        $this->push([
            'TOTAL', null, null, null, null, null,
            $totDebit  ?: null,
            $totCredit ?: null,
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
        if (!empty($this->filters['journal_type_id'])) {
            $parts[] = 'Journal : ' . $this->filters['journal_type_id'];
        }
        if (!empty($this->filters['search'])) {
            $parts[] = 'Recherche : "' . $this->filters['search'] . '"';
        }
        return $parts ? implode('  •  ', $parts) : 'Toutes les écritures';
    }

    private function loadEntries()
    {
        return JournalEntry::with(['journalType'])
            ->when($this->filters['search'] ?? null, fn($q, $s) =>
                $q->where(fn($sq) =>
                    $sq->where('number',      'like', "%{$s}%")
                       ->orWhere('description', 'like', "%{$s}%")
                       ->orWhere('reference',   'like', "%{$s}%")
                )
            )
            ->when($this->filters['journal_type_id'] ?? null, fn($q, $id) => $q->where('journal_type_id', $id))
            ->when($this->filters['status']           ?? null, fn($q, $s)  => $q->where('status', $s))
            ->when($this->filters['date_from']        ?? null, fn($q, $d)  => $q->whereDate('entry_date', '>=', $d))
            ->when($this->filters['date_to']          ?? null, fn($q, $d)  => $q->whereDate('entry_date', '<=', $d))
            ->orderByDesc('entry_date')
            ->orderByDesc('id')
            ->lazy(1_000);
    }

    /* ── Styles ───────────────────────────────────────────────────────────── */

    private function applyStyles(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $ws): void
    {
        $numFmt  = '#,##0';
        $numCols = ['G', 'H'];

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
            $ws->getStyle('F' . $r)->applyFromArray([
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

        // Freeze pane under column headers
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
            ->setOddHeader('&L&BJournal Comptable&R&P / &N');
        $ws->getHeaderFooter()
            ->setOddFooter('&LÉdité le ' . now()->format('d/m/Y') . '&R&F');

        $ws->setPrintGridlines(false);

        // Company header text wrapping
        $this->applyCompanyHeaderWrap($ws, 1, 2);
    }

    /* ── Style helpers ────────────────────────────────────────────────────── */

    private function sDocHeader($ws, int $r): void
    {
        $ws->mergeCells('A' . $r . ':C' . $r);
        $ws->mergeCells('D' . $r . ':F' . $r);

        $ws->getStyle('A' . $r . ':H' . $r)->applyFromArray([
            'font'      => ['bold' => true, 'size' => 12, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E3A5F']],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $ws->getStyle('A' . $r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $ws->getStyle('D' . $r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $ws->getStyle('G' . $r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $ws->getRowDimension($r)->setRowHeight(24);
    }

    private function sPeriod($ws, int $r): void
    {
        $ws->mergeCells('A' . $r . ':F' . $r);
        $ws->getStyle('A' . $r . ':H' . $r)->applyFromArray([
            'font'      => ['italic' => true, 'size' => 9, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2D5986']],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $ws->getStyle('A' . $r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $ws->getStyle('G' . $r)->applyFromArray([
            'font'      => ['italic' => true, 'size' => 9, 'color' => ['rgb' => 'D1D5DB']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2D5986']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT],
        ]);
        $ws->getStyle('H' . $r)->applyFromArray([
            'font' => ['italic' => true, 'size' => 9, 'color' => ['rgb' => 'D1D5DB']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2D5986']],
        ]);
        $ws->getRowDimension($r)->setRowHeight(16);
    }

    private function sColHeader($ws, int $r): void
    {
        $ws->getStyle('A' . $r . ':H' . $r)->applyFromArray([
            'font'      => ['bold' => true, 'size' => 9, 'color' => ['rgb' => '1E3A5F']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E0E7FF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'borders'   => [
                'bottom' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '6366F1']],
                'top'    => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '6366F1']],
            ],
        ]);
        $ws->getStyle('A' . $r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $ws->getStyle('D' . $r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $ws->getStyle('E' . $r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $ws->getRowDimension($r)->setRowHeight(18);
    }

    private function sData($ws, int $r): void
    {
        $ws->getStyle('A' . $r . ':H' . $r)->applyFromArray([
            'font'      => ['size' => 9],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
            'borders'   => [
                'bottom' => ['borderStyle' => Border::BORDER_HAIR, 'color' => ['rgb' => 'E5E7EB']],
            ],
        ]);
        foreach (['G', 'H'] as $col) {
            $ws->getStyle($col . $r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        }
        $ws->getStyle('B' . $r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $ws->getRowDimension($r)->setRowHeight(14);
    }

    private function sTotal($ws, int $r): void
    {
        $ws->mergeCells('A' . $r . ':F' . $r);
        $ws->getStyle('A' . $r . ':H' . $r)->applyFromArray([
            'font'      => ['bold' => true, 'size' => 11, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E3A5F']],
            'borders'   => [
                'top' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => '6366F1']],
            ],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $ws->getStyle('A' . $r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        foreach (['G', 'H'] as $col) {
            $ws->getStyle($col . $r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        }
        $ws->getRowDimension($r)->setRowHeight(22);
    }
}
