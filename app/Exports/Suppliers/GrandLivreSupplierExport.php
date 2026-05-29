<?php

namespace App\Exports\Suppliers;

use App\Models\Company;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\RegistersEventListeners;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class GrandLivreSupplierExport implements FromArray, WithTitle, WithColumnWidths, WithEvents
{
    use \App\Exports\Concerns\HasCompanyHeader;
    use RegistersEventListeners;

    private const T_DOC_HEADER = 'doc_header';
    private const T_PERIOD     = 'period';
    private const T_BLANK      = 'blank';
    private const T_ACC_HEADER = 'acc_header';
    private const T_COL_HEADER = 'col_header';
    private const T_DATA       = 'data';
    private const T_ACC_TOTAL  = 'acc_total';

    private array $rows = [];
    private array $meta = [];
    private int   $rowIdx = 0;
    private array $dataRows = [];

    public function __construct(private ?string $dateFrom, private ?string $dateTo, private ?string $search) { $this->build(); }

    public function title(): string { return 'Grand livre fournisseurs'; }
    public function array(): array  { return $this->rows; }
    public function columnWidths(): array { return ['A' => 13, 'B' => 18, 'C' => 8, 'D' => 18, 'E' => 44, 'F' => 18, 'G' => 18, 'H' => 18]; }
    public function registerEvents(): array { return [AfterSheet::class => fn(AfterSheet $e) => $this->applyStyles($e->sheet->getDelegate())]; }

    private function build(): void
    {
        $company = currentCompany();
        $this->push([$this->companyNameCell($company), null, null, 'GRAND LIVRE FOURNISSEURS (comptes 401)', null, null, null, 'Période : ' . $this->fmt($this->dateFrom) . ' → ' . $this->fmt($this->dateTo)], self::T_DOC_HEADER);
        $periodText = 'Écritures validées — comptes fournisseurs';
        $this->push([$this->companyLegalLine($company, $periodText), null, null, null, null, null, null, 'Édition du ' . now()->format('d/m/Y à H\hi')], self::T_PERIOD);
        $this->push(array_fill(0, 8, null), self::T_BLANK);

        $accounts = $this->loadAccounts();
        foreach ($accounts as $account) {
            $this->push([$account['code'] . ' — ' . $account['name'], null, null, 'Solde ouv. : ' . number_format($account['solde_ouv'], 0, ',', ' '), null, null, null, 'Solde fin : ' . number_format($account['solde_fin'], 0, ',', ' ')], self::T_ACC_HEADER);
            $this->push(['Date', 'N° Écriture', 'Jnl', 'Référence', 'Libellé', 'Débit (FCFA)', 'Crédit (FCFA)', 'Solde (FCFA)'], self::T_COL_HEADER);
            foreach ($account['lines'] as $item) {
                $l = $item['line'];
                $this->push([$l->journalEntry?->entry_date?->format('d/m/Y') ?? '—', $l->journalEntry?->number ?? '—', $l->journalEntry?->journalType?->code ?? '—', $l->journalEntry?->reference ?? '', $l->label ?: ($l->journalEntry?->description ?? ''), (int)$l->debit > 0 ? (int)$l->debit : null, (int)$l->credit > 0 ? (int)$l->credit : null, $item['solde']], self::T_DATA);
                $this->dataRows[] = $this->rowIdx;
            }
            $this->push(['Total ' . $account['code'], null, null, null, null, $account['total_d'] ?: null, $account['total_c'] ?: null, $account['solde_fin']], self::T_ACC_TOTAL);
            $this->push(array_fill(0, 8, null), self::T_BLANK);
        }
    }

    private function loadAccounts(): array
    {
        $query = JournalEntryLine::with(['account', 'journalEntry.journalType'])->whereHas('account', fn($q) => $q->where('code', 'like', '401%'))->whereHas('journalEntry', fn($q) => $q->where('status', 'valide'));
        if ($this->dateFrom) $query->whereHas('journalEntry', fn($q) => $q->whereDate('entry_date', '>=', $this->dateFrom));
        if ($this->dateTo)   $query->whereHas('journalEntry', fn($q) => $q->whereDate('entry_date', '<=', $this->dateTo));
        if ($this->search) {
            $s = "%{$this->search}%";
            $query->where(fn($q) => $q->whereHas('account', fn($aq) => $aq->where('code', 'like', $s)->orWhere('name', 'like', $s))->orWhere('label', 'like', $s)->orWhereHas('journalEntry', fn($eq) => $eq->where('number', 'like', $s)->orWhere('reference', 'like', $s)));
        }
        $lines   = $query->orderBy(JournalEntry::select('entry_date')->whereColumn('id', 'journal_entry_lines.journal_entry_id'))->orderBy('journal_entry_id')->get();
        $grouped = $lines->groupBy(fn($l) => $l->account?->code);
        $accounts = [];
        foreach ($grouped as $code => $accountLines) {
            $soldeOuv = 0;
            if ($this->dateFrom) {
                $open = JournalEntryLine::whereHas('account', fn($q) => $q->where('code', $code))->whereHas('journalEntry', fn($q) => $q->where('status', 'valide')->whereDate('entry_date', '<', $this->dateFrom))->get();
                $soldeOuv = $open->sum('credit') - $open->sum('debit');
            }
            $solde = $soldeOuv;
            $linesWithSolde = $accountLines->map(function ($l) use (&$solde) {
                $solde += (int)$l->credit - (int)$l->debit;
                return ['line' => $l, 'solde' => $solde];
            });
            $accounts[] = ['code' => $code, 'name' => $accountLines->first()?->account?->name ?? '—', 'solde_ouv' => $soldeOuv, 'lines' => $linesWithSolde, 'total_d' => $accountLines->sum('debit'), 'total_c' => $accountLines->sum('credit'), 'solde_fin' => $solde];
        }
        usort($accounts, fn($a, $b) => strcmp($a['code'], $b['code']));
        return $accounts;
    }

    private function fmt(?string $d): string { return $d ? date('d/m/Y', strtotime($d)) : '—'; }
    private function push(array $cells, string $type): void { $this->rows[] = $cells; $this->meta[++$this->rowIdx] = $type; }

    private function applyStyles(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $ws): void
    {
        $numFmt = '#,##0'; $numCols = ['F', 'G', 'H'];
        foreach ($this->dataRows as $r) { foreach ($numCols as $c) { $ws->getStyle($c . $r)->getNumberFormat()->setFormatCode($numFmt); } }
        foreach ($this->meta as $r => $type) {
            match ($type) {
                self::T_DOC_HEADER => $this->sDocHeader($ws, $r),
                self::T_PERIOD     => $this->sPeriod($ws, $r),
                self::T_ACC_HEADER => $this->sAccHeader($ws, $r),
                self::T_COL_HEADER => $this->sColHeader($ws, $r),
                self::T_DATA       => $this->sData($ws, $r),
                self::T_ACC_TOTAL  => $this->sAccTotal($ws, $r),
                default            => null,
            };
        }
        foreach (array_keys(array_filter($this->meta, fn($t) => $t === self::T_ACC_TOTAL)) as $r) {
            foreach ($numCols as $c) { $ws->getStyle($c . $r)->getNumberFormat()->setFormatCode($numFmt); }
        }
        $ws->getPageSetup()->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE)->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_A4)->setFitToPage(true)->setFitToWidth(1)->setFitToHeight(0);
        $ws->getHeaderFooter()->setOddHeader('&L&BGrand Livre Fournisseurs&R&P / &N');
        $ws->getHeaderFooter()->setOddFooter('&LÉdité le ' . now()->format('d/m/Y') . '&R&F');

        // Company header text wrapping
        $this->applyCompanyHeaderWrap($ws, 1, 2);
    }

    private function sDocHeader($ws, int $r): void
    {
        $ws->mergeCells("A{$r}:C{$r}"); $ws->mergeCells("D{$r}:G{$r}");
        $ws->getStyle("A{$r}:H{$r}")->applyFromArray(['font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '7C2D12']], 'alignment' => ['vertical' => Alignment::VERTICAL_CENTER]]);
        $ws->getStyle("A{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $ws->getStyle("D{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $ws->getStyle("H{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $ws->getRowDimension($r)->setRowHeight(24);
    }

    private function sPeriod($ws, int $r): void
    {
        $ws->mergeCells("A{$r}:G{$r}");
        $ws->getStyle("A{$r}:H{$r}")->applyFromArray(['font' => ['italic' => true, 'size' => 9, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'B45309']], 'alignment' => ['vertical' => Alignment::VERTICAL_CENTER]]);
        $ws->getStyle("H{$r}")->applyFromArray(['font' => ['italic' => true, 'size' => 9, 'color' => ['rgb' => 'FDE68A']], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'B45309']], 'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT]]);
        $ws->getRowDimension($r)->setRowHeight(16);
    }

    private function sAccHeader($ws, int $r): void
    {
        $ws->mergeCells("A{$r}:C{$r}"); $ws->mergeCells("D{$r}:G{$r}");
        $ws->getStyle("A{$r}:H{$r}")->applyFromArray(['font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '92400E']], 'alignment' => ['vertical' => Alignment::VERTICAL_CENTER]]);
        $ws->getStyle("A{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $ws->getStyle("D{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $ws->getStyle("H{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $ws->getRowDimension($r)->setRowHeight(18);
    }

    private function sColHeader($ws, int $r): void
    {
        $ws->getStyle("A{$r}:H{$r}")->applyFromArray(['font' => ['bold' => true, 'size' => 8, 'color' => ['rgb' => '7C2D12']], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FEF3C7']], 'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER], 'borders' => ['bottom' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'F97316']]]]);
        $ws->getStyle("A{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $ws->getStyle("E{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $ws->getRowDimension($r)->setRowHeight(15);
    }

    private function sData($ws, int $r): void
    {
        $ws->getStyle("A{$r}:H{$r}")->applyFromArray(['font' => ['size' => 9], 'alignment' => ['vertical' => Alignment::VERTICAL_CENTER], 'borders' => ['bottom' => ['borderStyle' => Border::BORDER_HAIR, 'color' => ['rgb' => 'E5E7EB']]]]);
        $ws->getStyle('C' . $r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        foreach (['F', 'G', 'H'] as $c) { $ws->getStyle($c . $r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT); }
        $ws->getRowDimension($r)->setRowHeight(13);
    }

    private function sAccTotal($ws, int $r): void
    {
        $ws->mergeCells("A{$r}:E{$r}");
        $ws->getStyle("A{$r}:H{$r}")->applyFromArray(['font' => ['bold' => true, 'size' => 9, 'color' => ['rgb' => '7C2D12']], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FEF3C7']], 'borders' => ['top' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'F97316']]], 'alignment' => ['vertical' => Alignment::VERTICAL_CENTER]]);
        $ws->getStyle("A{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        foreach (['F', 'G', 'H'] as $c) { $ws->getStyle($c . $r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT); }
        $ws->getRowDimension($r)->setRowHeight(16);
    }
}
