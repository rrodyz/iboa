<?php

namespace App\Exports\Accounting;

use App\Models\Account;
use App\Models\Company;
use App\Models\JournalEntryLine;
use App\Models\PayrollSetting;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\RegistersEventListeners;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class BalanceExport implements FromArray, WithTitle, WithColumnWidths, WithEvents
{
    use \App\Exports\Concerns\HasCompanyHeader;
    use RegistersEventListeners;

    // Row-type constants
    private const T_DOC_HEADER = 'doc_header';
    private const T_PERIOD     = 'period';
    private const T_BLANK      = 'blank';
    private const T_COL_HEADER = 'col_header';
    private const T_DATA       = 'data';
    private const T_TOTAL      = 'total';

    private array $rows     = [];
    private array $meta     = [];   // 1-indexed → type
    private int   $rowIdx   = 0;
    private array $dataRows = [];   // data row numbers (for number format)

    public function __construct(
        private int     $companyId,
        private ?string $dateFrom,
        private ?string $dateTo,
        private ?string $classId,
    ) {
        $this->build();
    }

    public function title(): string { return 'Balance générale'; }

    public function array(): array { return $this->rows; }

    public function columnWidths(): array
    {
        return [
            'A' => 12,   // Code
            'B' => 40,   // Libellé
            'C' => 18,   // Ouv. Débit
            'D' => 18,   // Ouv. Crédit
            'E' => 18,   // Mvts Débit
            'F' => 18,   // Mvts Crédit
            'G' => 18,   // Solde Débiteur
            'H' => 18,   // Solde Créditeur
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => fn(AfterSheet $e) => $this->applyStyles($e->sheet->getDelegate()),
        ];
    }

    /* ── Data builder ─────────────────────────────────────────────────────── */

    private function build(): void
    {
        $company  = Company::find($this->companyId) ?? currentCompany();
        $currency = PayrollSetting::forCompany($company->id)->currency_code ?? 'FCFA';
        $period   = $this->periodLabel();

        // ── Document header ──────────────────────────────────────────────────
        $this->push([
            $this->companyNameCell($company),
            null, null, null,
            'BALANCE GÉNÉRALE DES COMPTES',
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

        // ── Column headers ───────────────────────────────────────────────────
        $this->push([
            'Code', 'Libellé',
            'Ouv. Débit', 'Ouv. Crédit',
            'Mvts Débit', 'Mvts Crédit',
            'Solde Débiteur', 'Solde Créditeur',
        ], self::T_COL_HEADER);

        // ── Data ─────────────────────────────────────────────────────────────
        $accounts = $this->loadAccounts();

        $totals = [
            'open_d' => 0, 'open_c' => 0,
            'mvt_d'  => 0, 'mvt_c'  => 0,
            'sol_d'  => 0, 'sol_c'  => 0,
        ];

        foreach ($accounts as $account) {
            $finalDebit  = $account->open_debit  + $account->period_debit;
            $finalCredit = $account->open_credit + $account->period_credit;
            $balance     = $finalDebit - $finalCredit;
            $solD        = $balance > 0 ? $balance       : 0;
            $solC        = $balance < 0 ? abs($balance)  : 0;

            $this->push([
                $account->code,
                $account->name,
                $account->open_debit   > 0 ? $account->open_debit   : null,
                $account->open_credit  > 0 ? $account->open_credit  : null,
                $account->period_debit > 0 ? $account->period_debit : null,
                $account->period_credit > 0 ? $account->period_credit : null,
                $solD > 0 ? $solD : null,
                $solC > 0 ? $solC : null,
            ], self::T_DATA);

            $this->dataRows[] = $this->rowIdx;

            $totals['open_d'] += $account->open_debit;
            $totals['open_c'] += $account->open_credit;
            $totals['mvt_d']  += $account->period_debit;
            $totals['mvt_c']  += $account->period_credit;
            $totals['sol_d']  += $solD;
            $totals['sol_c']  += $solC;
        }

        // ── Totals row ───────────────────────────────────────────────────────
        $this->push([
            'TOTAL',
            null,
            $totals['open_d'] ?: null,
            $totals['open_c'] ?: null,
            $totals['mvt_d']  ?: null,
            $totals['mvt_c']  ?: null,
            $totals['sol_d']  ?: null,
            $totals['sol_c']  ?: null,
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
        $from = $this->dateFrom ? date('d/m/Y', strtotime($this->dateFrom)) : '—';
        $to   = $this->dateTo   ? date('d/m/Y', strtotime($this->dateTo))   : '—';
        return 'du ' . $from . ' au ' . $to;
    }

    private function loadAccounts()
    {
        $accounts = Account::with('accountClass')
            ->where('company_id', $this->companyId)
            ->where('is_detail', true)
            ->when($this->classId, fn($q) => $q->where('account_class_id', $this->classId))
            ->orderBy('code')
            ->get();

        $accountIds = $accounts->pluck('id');

        // Opening balances (before date_from)
        $openings = [];
        if ($this->dateFrom) {
            $openings = JournalEntryLine::query()
                ->selectRaw('account_id, SUM(debit) as open_debit, SUM(credit) as open_credit')
                ->whereIn('account_id', $accountIds)
                ->whereHas('journalEntry', fn($q) => $q->where('status', 'valide')
                                                        ->whereDate('entry_date', '<', $this->dateFrom))
                ->groupBy('account_id')
                ->get()->keyBy('account_id')
                ->toArray();
        }

        // Period movements
        if ($this->dateFrom || $this->dateTo) {
            $movements = JournalEntryLine::query()
                ->selectRaw('account_id, SUM(debit) as total_debit, SUM(credit) as total_credit')
                ->whereIn('account_id', $accountIds)
                ->whereHas('journalEntry', function ($q) {
                    $q->where('status', 'valide');
                    if ($this->dateFrom) $q->whereDate('entry_date', '>=', $this->dateFrom);
                    if ($this->dateTo)   $q->whereDate('entry_date', '<=', $this->dateTo);
                })
                ->groupBy('account_id')
                ->get()->keyBy('account_id')
                ->toArray();

            $accounts = $accounts->map(function ($account) use ($movements, $openings) {
                $account->open_debit    = (int) ($openings[$account->id]['open_debit']    ?? 0);
                $account->open_credit   = (int) ($openings[$account->id]['open_credit']   ?? 0);
                $account->period_debit  = (int) ($movements[$account->id]['total_debit']  ?? 0);
                $account->period_credit = (int) ($movements[$account->id]['total_credit'] ?? 0);
                return $account;
            });
        } else {
            $accounts = $accounts->map(function ($account) {
                $account->open_debit    = 0;
                $account->open_credit   = 0;
                $account->period_debit  = (int) $account->debit_balance;
                $account->period_credit = (int) $account->credit_balance;
                return $account;
            });
        }

        return $accounts->filter(
            fn($a) => $a->open_debit > 0 || $a->open_credit > 0
                   || $a->period_debit > 0 || $a->period_credit > 0
        );
    }

    /* ── Styles ────────────────────────────────────────────────────────────── */

    private function applyStyles(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $ws): void
    {
        $numFmt = '#,##0';

        // Number format on all numeric columns for data rows
        foreach ($this->dataRows as $r) {
            foreach (['C', 'D', 'E', 'F', 'G', 'H'] as $col) {
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
            foreach (['C', 'D', 'E', 'F', 'G', 'H'] as $col) {
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

        $ws->getHeaderFooter()
            ->setOddHeader('&L&BBalance Générale des Comptes&R&P / &N');
        $ws->getHeaderFooter()
            ->setOddFooter('&LÉdité le ' . now()->format('d/m/Y') . '&R&F');

        $ws->setPrintGridlines(false);

        // Company header text wrapping
        $this->applyCompanyHeaderWrap($ws, 1, 2);
    }

    /* ── Style helpers ────────────────────────────────────────────────────── */

    private function sDocHeader($ws, int $r): void
    {
        $ws->mergeCells('A' . $r . ':D' . $r);
        $ws->mergeCells('E' . $r . ':G' . $r);

        $ws->getStyle('A' . $r . ':H' . $r)->applyFromArray([
            'font'      => ['bold' => true, 'size' => 12, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E3A5F']],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $ws->getStyle('A' . $r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $ws->getStyle('E' . $r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $ws->getStyle('H' . $r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $ws->getRowDimension($r)->setRowHeight(24);
    }

    private function sPeriod($ws, int $r): void
    {
        $ws->mergeCells('A' . $r . ':G' . $r);
        $ws->getStyle('A' . $r . ':H' . $r)->applyFromArray([
            'font'      => ['italic' => true, 'size' => 9, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2D5986']],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $ws->getStyle('A' . $r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $ws->getStyle('H' . $r)->applyFromArray([
            'font'      => ['italic' => true, 'size' => 9, 'color' => ['rgb' => 'D1D5DB']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2D5986']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT],
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
        // Left-align code and label columns
        $ws->getStyle('A' . $r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $ws->getStyle('B' . $r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
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
        // Right-align numeric columns
        foreach (['C', 'D', 'E', 'F', 'G', 'H'] as $col) {
            $ws->getStyle($col . $r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        }
        $ws->getRowDimension($r)->setRowHeight(13);
    }

    private function sTotal($ws, int $r): void
    {
        $ws->getStyle('A' . $r . ':H' . $r)->applyFromArray([
            'font'      => ['bold' => true, 'size' => 11, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E3A5F']],
            'borders'   => [
                'top' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => '6366F1']],
            ],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $ws->getStyle('A' . $r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        foreach (['C', 'D', 'E', 'F', 'G', 'H'] as $col) {
            $ws->getStyle($col . $r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        }
        $ws->getRowDimension($r)->setRowHeight(22);
    }
}
