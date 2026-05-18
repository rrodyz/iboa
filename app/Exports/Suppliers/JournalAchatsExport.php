<?php

namespace App\Exports\Suppliers;

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

class JournalAchatsExport implements FromArray, WithTitle, WithColumnWidths, WithEvents
{
    use \App\Exports\Concerns\HasCompanyHeader;
    use RegistersEventListeners;

    private const T_DOC_HEADER = 'doc_header';
    private const T_PERIOD     = 'period';
    private const T_BLANK      = 'blank';
    private const T_COL_HEADER = 'col_header';
    private const T_DATA       = 'data';
    private const T_TOTAL      = 'total';

    private array $rows = [];
    private array $meta = [];
    private int   $rowIdx = 0;
    private array $dataRows = [];

    public function __construct(private ?string $dateFrom, private ?string $dateTo, private ?string $search) { $this->build(); }

    public function title(): string { return 'Journal des achats'; }
    public function array(): array  { return $this->rows; }
    public function columnWidths(): array { return ['A' => 12, 'B' => 14, 'C' => 8, 'D' => 16, 'E' => 46, 'F' => 18, 'G' => 18]; }
    public function registerEvents(): array { return [AfterSheet::class => fn(AfterSheet $e) => $this->applyStyles($e->sheet->getDelegate())]; }

    private function build(): void
    {
        $company = Company::first();
        $this->push([$this->companyNameCell($company), null, null, 'JOURNAL DES ACHATS', null, null, 'Période : ' . $this->fmt($this->dateFrom) . ' → ' . $this->fmt($this->dateTo)], self::T_DOC_HEADER);
        $periodText = 'Écritures validées — journal Achat';
        $this->push([$this->companyLegalLine($company, $periodText), null, null, null, null, null, 'Édition du ' . now()->format('d/m/Y à H\hi')], self::T_PERIOD);
        $this->push(array_fill(0, 7, null), self::T_BLANK);
        $this->push(['Date', 'N° Écriture', 'Jnl', 'Référence', 'Libellé', 'Débit (FCFA)', 'Crédit (FCFA)'], self::T_COL_HEADER);

        $query = JournalEntry::with(['journalType'])->whereHas('journalType', fn($q) => $q->where('type', 'achat'))->where('status', 'valide');
        if ($this->dateFrom) $query->whereDate('entry_date', '>=', $this->dateFrom);
        if ($this->dateTo)   $query->whereDate('entry_date', '<=', $this->dateTo);
        if ($this->search) {
            $s = "%{$this->search}%";
            $query->where(fn($q) => $q->where('number', 'like', $s)->orWhere('reference', 'like', $s)->orWhere('description', 'like', $s));
        }

        $entries = $query->orderBy('entry_date')->orderBy('id')->lazy(1_000);

        foreach ($entries as $e) {
            $this->push([$e->entry_date?->format('d/m/Y'), $e->number, $e->journalType?->code, $e->reference, $e->description, $e->total_debit ?: null, $e->total_credit ?: null], self::T_DATA);
            $this->dataRows[] = $this->rowIdx;
        }

        $this->push(['TOTAUX', null, null, null, null, $entries->sum('total_debit') ?: null, $entries->sum('total_credit') ?: null], self::T_TOTAL);
    }

    private function fmt(?string $d): string { return $d ? date('d/m/Y', strtotime($d)) : '—'; }
    private function push(array $cells, string $type): void { $this->rows[] = $cells; $this->meta[++$this->rowIdx] = $type; }

    private function applyStyles(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $ws): void
    {
        $numFmt = '#,##0'; $numCols = ['F', 'G'];
        foreach ($this->dataRows as $r) { foreach ($numCols as $c) { $ws->getStyle($c . $r)->getNumberFormat()->setFormatCode($numFmt); } }
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
        $totalRow = array_key_last(array_filter($this->meta, fn($t) => $t === self::T_TOTAL));
        if ($totalRow) { foreach ($numCols as $c) { $ws->getStyle($c . $totalRow)->getNumberFormat()->setFormatCode($numFmt); } }
        $ws->freezePane('A5');
        $ws->getPageSetup()->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE)->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_A4)->setFitToPage(true)->setFitToWidth(1)->setFitToHeight(0);
        $ws->getHeaderFooter()->setOddHeader('&L&BJournal des Achats&R&P / &N');
        $ws->getHeaderFooter()->setOddFooter('&LÉdité le ' . now()->format('d/m/Y') . '&R&F');

        // Company header text wrapping
        $this->applyCompanyHeaderWrap($ws, 1, 2);
    }

    private function sDocHeader($ws, int $r): void
    {
        $ws->mergeCells("A{$r}:C{$r}"); $ws->mergeCells("D{$r}:F{$r}");
        $ws->getStyle("A{$r}:G{$r}")->applyFromArray(['font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '7C2D12']], 'alignment' => ['vertical' => Alignment::VERTICAL_CENTER]]);
        $ws->getStyle("A{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $ws->getStyle("D{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $ws->getStyle("G{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $ws->getRowDimension($r)->setRowHeight(24);
    }

    private function sPeriod($ws, int $r): void
    {
        $ws->mergeCells("A{$r}:F{$r}");
        $ws->getStyle("A{$r}:G{$r}")->applyFromArray(['font' => ['italic' => true, 'size' => 9, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'B45309']], 'alignment' => ['vertical' => Alignment::VERTICAL_CENTER]]);
        $ws->getStyle("G{$r}")->applyFromArray(['font' => ['italic' => true, 'size' => 9, 'color' => ['rgb' => 'FDE68A']], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'B45309']], 'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT]]);
        $ws->getRowDimension($r)->setRowHeight(16);
    }

    private function sColHeader($ws, int $r): void
    {
        $ws->getStyle("A{$r}:G{$r}")->applyFromArray(['font' => ['bold' => true, 'size' => 9, 'color' => ['rgb' => '7C2D12']], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FEF3C7']], 'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER], 'borders' => ['bottom' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'F97316']], 'top' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'F97316']]]]);
        $ws->getStyle("A{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $ws->getStyle("E{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $ws->getRowDimension($r)->setRowHeight(16);
    }

    private function sData($ws, int $r): void
    {
        $ws->getStyle("A{$r}:G{$r}")->applyFromArray(['font' => ['size' => 9], 'alignment' => ['vertical' => Alignment::VERTICAL_CENTER], 'borders' => ['bottom' => ['borderStyle' => Border::BORDER_HAIR, 'color' => ['rgb' => 'E5E7EB']]]]);
        $ws->getStyle('C' . $r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        foreach (['F', 'G'] as $c) { $ws->getStyle($c . $r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT); }
        $ws->getRowDimension($r)->setRowHeight(13);
    }

    private function sTotal($ws, int $r): void
    {
        $ws->mergeCells("A{$r}:E{$r}");
        $ws->getStyle("A{$r}:G{$r}")->applyFromArray(['font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '7C2D12']], 'borders' => ['top' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => 'F97316']]], 'alignment' => ['vertical' => Alignment::VERTICAL_CENTER]]);
        foreach (['F', 'G'] as $c) { $ws->getStyle($c . $r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT); }
        $ws->getRowDimension($r)->setRowHeight(22);
    }
}
