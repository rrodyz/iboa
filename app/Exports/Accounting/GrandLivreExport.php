<?php

namespace App\Exports\Accounting;

use App\Models\Account;
use App\Models\Company;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\RegistersEventListeners;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

/* ═══════════════════════════════════════════════════════════════════════════
   Entry point — delegates to a single-sheet export
═══════════════════════════════════════════════════════════════════════════ */
class GrandLivreExport implements WithMultipleSheets
{
    public function __construct(
        private array   $accountIds,
        private ?string $dateFrom,
        private ?string $dateTo,
        private ?string $search,
    ) {}

    public function sheets(): array
    {
        return [new GrandLivreSingleSheet(
            $this->accountIds,
            $this->dateFrom,
            $this->dateTo,
            $this->search,
        )];
    }
}

/* ═══════════════════════════════════════════════════════════════════════════
   Single-sheet — format calqué sur le modèle PDF (Sage 100 Comptabilité)
═══════════════════════════════════════════════════════════════════════════ */
class GrandLivreSingleSheet implements FromArray, WithTitle, WithColumnWidths, WithEvents
{
    use \App\Exports\Concerns\HasCompanyHeader;
    use RegistersEventListeners;

    // Row-type constants
    private const T_DOC_HEADER   = 'doc_header';
    private const T_PERIOD       = 'period';
    private const T_BLANK        = 'blank';
    private const T_ACCT_TITLE   = 'acct_title';
    private const T_COL_HEADER   = 'col_header';
    private const T_DATA         = 'data';
    private const T_TOTAL        = 'total';
    private const T_GRAND_TOTAL  = 'grand_total';

    private array $rows      = [];
    private array $meta      = [];   // 1-indexed row number → type
    private int   $rowIdx    = 0;

    // Collected for number-formatting pass
    private array $numericRows = []; // row numbers that have F/G amounts (totals + data)
    private array $dataRows    = []; // data row numbers only (for H column formatting)

    public function __construct(
        private array   $accountIds,
        private ?string $dateFrom,
        private ?string $dateTo,
        private ?string $search,
    ) {
        $this->build();
    }

    public function title(): string { return 'Grand livre'; }

    public function array(): array { return $this->rows; }

    public function columnWidths(): array
    {
        return [
            'A' => 14,   // Date
            'B' => 8,    // C.j
            'C' => 20,   // N° Pièce
            'D' => 45,   // Libellé
            'E' => 8,    // Let
            'F' => 18,   // Mouvement Débit
            'G' => 18,   // Mouvement Crédit
            'H' => 20,   // Solde progressif
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $this->applyStyles($event->sheet->getDelegate());
            },
        ];
    }

    /* ── Row builders ──────────────────────────────────────────────────────── */

    private function build(): void
    {
        $company  = Company::firstOrFail();
        $currency = 'FCFA';
        $accounts = Account::whereIn('id', $this->accountIds)
            ->orderBy('code')
            ->get(['id', 'code', 'name']);

        $period = $this->periodLabel();

        // ── Document header (2 rows) ─────────────────────────────────────────
        $this->push([
            $this->companyNameCell($company),
            null, null, null,
            'GRAND LIVRE DES COMPTES',
            null, null,
            'Période : ' . $period,
        ], self::T_DOC_HEADER);

        $periodText = 'Tenue de compte : ' . $currency;
        $this->push([
            $this->companyLegalLine($company, $periodText),
            null, null, null, null, null, null,
            'Édition du ' . now()->format('d/m/Y à H\hi'),
        ], self::T_PERIOD);

        $this->push(array_fill(0, 8, null), self::T_BLANK);

        // ── Accounts ─────────────────────────────────────────────────────────
        $grandD = 0;
        $grandC = 0;

        foreach ($accounts as $account) {
            $lines = $this->loadLines($account->id);
            if ($lines->isEmpty()) {
                continue;
            }

            // Title
            $this->push([
                $account->code . '   ' . $account->name,
                null, null, null, null, null, null, null,
            ], self::T_ACCT_TITLE);

            // Column headers
            $this->push([
                'Date', 'C.j', 'N° Pièce', 'Libellé écriture', 'Let',
                'Mouvement Débit', 'Mouvement Crédit', 'Solde progressif',
            ], self::T_COL_HEADER);

            // Transactions
            $running = 0;
            $totD    = 0;
            $totC    = 0;

            foreach ($lines as $line) {
                $d        = (int) round((float) $line->debit);
                $c        = (int) round((float) $line->credit);
                $running += $d - $c;
                $totD    += $d;
                $totC    += $c;

                $this->push([
                    $line->journalEntry?->entry_date?->format('d/m/Y'),
                    $line->journalEntry?->journalType?->code,
                    $line->journalEntry?->number,
                    $line->label ?: $line->journalEntry?->description,
                    $line->reconciliation_ref,
                    $d > 0 ? $d : null,
                    $c > 0 ? $c : null,
                    $running !== 0 ? $running : null,
                ], self::T_DATA);

                $this->numericRows[] = $this->rowIdx;
                $this->dataRows[]    = $this->rowIdx;
            }

            // Account total
            $bal   = $totD - $totC;
            $label = $bal >= 0 ? 'D' : 'C';
            $from  = $this->dateFrom ? date('d/m/Y', strtotime($this->dateFrom)) : '—';
            $to    = $this->dateTo   ? date('d/m/Y', strtotime($this->dateTo))   : '—';

            $this->push([
                'Total compte ' . $account->code . '  du ' . $from . ' au ' . $to,
                null, null, null, null,
                $totD > 0 ? $totD : null,
                $totC > 0 ? $totC : null,
                number_format(abs($bal), 0, ',', ' ') . ' ' . $label,
            ], self::T_TOTAL);

            $this->numericRows[] = $this->rowIdx;

            $this->push(array_fill(0, 8, null), self::T_BLANK);

            $grandD += $totD;
            $grandC += $totC;
        }

        // ── Grand total ──────────────────────────────────────────────────────
        $gBal   = $grandD - $grandC;
        $gLabel = $gBal >= 0 ? 'D' : 'C';

        $this->push([
            'TOTAL GÉNÉRAL',
            null, null, null, null,
            $grandD > 0 ? $grandD : null,
            $grandC > 0 ? $grandC : null,
            number_format(abs($gBal), 0, ',', ' ') . ' ' . $gLabel,
        ], self::T_GRAND_TOTAL);

        $this->numericRows[] = $this->rowIdx;
    }

    private function push(array $cells, string $type): void
    {
        $this->rows[] = $cells;
        $this->rowIdx++;
        $this->meta[$this->rowIdx] = $type;
    }

    private function periodLabel(): string
    {
        $from = $this->dateFrom ? date('d/m/Y', strtotime($this->dateFrom)) : '—';
        $to   = $this->dateTo   ? date('d/m/Y', strtotime($this->dateTo))   : '—';
        return 'du ' . $from . ' au ' . $to;
    }

    private function loadLines(int $accountId)
    {
        return JournalEntryLine::with(['journalEntry.journalType'])
            ->where('account_id', $accountId)
            ->when($this->dateFrom, fn($q) => $q->whereHas('journalEntry', fn($je) => $je->whereDate('entry_date', '>=', $this->dateFrom)))
            ->when($this->dateTo,   fn($q) => $q->whereHas('journalEntry', fn($je) => $je->whereDate('entry_date', '<=', $this->dateTo)))
            ->when($this->search,   fn($q) => $q->where(fn($sq) =>
                $sq->where('label', 'like', '%'.$this->search.'%')
                   ->orWhereHas('journalEntry', fn($je) =>
                       $je->where('number', 'like', '%'.$this->search.'%')
                          ->orWhere('reference', 'like', '%'.$this->search.'%')
                   )
            ))
            ->whereHas('journalEntry', fn($q) => $q->where('status', 'valide'))
            ->orderBy(
                JournalEntry::select('entry_date')
                    ->whereColumn('journal_entries.id', 'journal_entry_lines.journal_entry_id')
                    ->limit(1)
            )
            ->get();
    }

    /* ── Styles ────────────────────────────────────────────────────────────── */

    private function applyStyles(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $ws): void
    {
        // Number format for amount columns (F/G for all rows with amounts)
        $numFmt = '#,##0';
        foreach ($this->numericRows as $r) {
            foreach (['F', 'G'] as $col) {
                $ws->getStyle($col . $r)->getNumberFormat()->setFormatCode($numFmt);
            }
        }
        // H column for data rows only (solde progressif = numeric running balance)
        foreach ($this->dataRows as $r) {
            $ws->getStyle('H' . $r)->getNumberFormat()->setFormatCode($numFmt);
        }

        // Per-row styling
        foreach ($this->meta as $r => $type) {
            $rng = 'A' . $r . ':H' . $r;
            match ($type) {
                self::T_DOC_HEADER  => $this->sDocHeader($ws, $r, $rng),
                self::T_PERIOD      => $this->sPeriod($ws, $r, $rng),
                self::T_ACCT_TITLE  => $this->sAcctTitle($ws, $r, $rng),
                self::T_COL_HEADER  => $this->sColHeader($ws, $r, $rng),
                self::T_DATA        => $this->sData($ws, $r, $rng),
                self::T_TOTAL       => $this->sTotal($ws, $r, $rng),
                self::T_GRAND_TOTAL => $this->sGrandTotal($ws, $r, $rng),
                default             => null,
            };
        }

        // Freeze pane under header
        $ws->freezePane('A4');

        // Print settings
        $ws->getPageSetup()
            ->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE)
            ->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_A4)
            ->setFitToPage(true)
            ->setFitToWidth(1)
            ->setFitToHeight(0);

        $ws->getHeaderFooter()
            ->setOddHeader('&L&B' . 'Grand Livre des Comptes' . '&R&P / &N');
        $ws->getHeaderFooter()
            ->setOddFooter('&LÉdité le ' . now()->format('d/m/Y') . '&R&F');

        $ws->setPrintGridlines(false);

        // Company header text wrapping
        $this->applyCompanyHeaderWrap($ws, 1, 2);
    }

    // ── Style helpers ──────────────────────────────────────────────────────────

    private function sDocHeader($ws, int $r, string $rng): void
    {
        $ws->mergeCells('A' . $r . ':D' . $r);
        $ws->mergeCells('E' . $r . ':G' . $r);

        $ws->getStyle($rng)->applyFromArray([
            'font'      => ['bold' => true, 'size' => 12, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E3A5F']],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $ws->getStyle('A' . $r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $ws->getStyle('E' . $r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $ws->getStyle('H' . $r)->applyFromArray([
            'font'      => ['bold' => true, 'size' => 10, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E3A5F']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $ws->getRowDimension($r)->setRowHeight(24);
    }

    private function sPeriod($ws, int $r, string $rng): void
    {
        $ws->mergeCells('A' . $r . ':G' . $r);
        $ws->getStyle($rng)->applyFromArray([
            'font'      => ['italic' => true, 'size' => 9, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2D5986']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $ws->getStyle('H' . $r)->applyFromArray([
            'font'      => ['italic' => true, 'size' => 9, 'color' => ['rgb' => 'D1D5DB']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2D5986']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT],
        ]);
        $ws->getRowDimension($r)->setRowHeight(16);
    }

    private function sAcctTitle($ws, int $r, string $rng): void
    {
        $ws->mergeCells('A' . $r . ':H' . $r);
        $ws->getStyle($rng)->applyFromArray([
            'font'      => ['bold' => true, 'size' => 10, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4338CA']],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_LEFT,
                'vertical'   => Alignment::VERTICAL_CENTER,
                'indent'     => 1,
            ],
        ]);
        $ws->getRowDimension($r)->setRowHeight(20);
    }

    private function sColHeader($ws, int $r, string $rng): void
    {
        $ws->getStyle($rng)->applyFromArray([
            'font'      => ['bold' => true, 'size' => 8, 'color' => ['rgb' => '1E3A5F']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E0E7FF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders'   => [
                'bottom' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '6366F1']],
                'top'    => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '6366F1']],
            ],
        ]);
        foreach (['F', 'G', 'H'] as $col) {
            $ws->getStyle($col . $r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        }
        $ws->getRowDimension($r)->setRowHeight(15);
    }

    private function sData($ws, int $r, string $rng): void
    {
        $ws->getStyle($rng)->applyFromArray([
            'font'      => ['size' => 9],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
            'borders'   => [
                'bottom' => ['borderStyle' => Border::BORDER_HAIR, 'color' => ['rgb' => 'E5E7EB']],
            ],
        ]);
        foreach (['F', 'G', 'H'] as $col) {
            $ws->getStyle($col . $r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        }
        // Highlight solde column slightly
        $ws->getStyle('H' . $r)->getFont()->setBold(false);
        $ws->getRowDimension($r)->setRowHeight(13);
    }

    private function sTotal($ws, int $r, string $rng): void
    {
        $ws->mergeCells('A' . $r . ':E' . $r);
        $ws->getStyle($rng)->applyFromArray([
            'font'      => ['bold' => true, 'size' => 9, 'italic' => true, 'color' => ['rgb' => '1E3A5F']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'EEF2FF']],
            'borders'   => [
                'top'    => ['borderStyle' => Border::BORDER_THIN,   'color' => ['rgb' => '6366F1']],
                'bottom' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => '4338CA']],
            ],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        foreach (['F', 'G', 'H'] as $col) {
            $ws->getStyle($col . $r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        }
        $ws->getRowDimension($r)->setRowHeight(15);
    }

    private function sGrandTotal($ws, int $r, string $rng): void
    {
        $ws->mergeCells('A' . $r . ':E' . $r);
        $ws->getStyle($rng)->applyFromArray([
            'font'      => ['bold' => true, 'size' => 11, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E3A5F']],
            'borders'   => [
                'top' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => '6366F1']],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_LEFT,
                'vertical'   => Alignment::VERTICAL_CENTER,
                'indent'     => 1,
            ],
        ]);
        foreach (['F', 'G', 'H'] as $col) {
            $ws->getStyle($col . $r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        }
        $ws->getRowDimension($r)->setRowHeight(22);
    }
}
