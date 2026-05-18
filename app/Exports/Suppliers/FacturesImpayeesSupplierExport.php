<?php

namespace App\Exports\Suppliers;

use App\Models\Company;
use App\Models\SupplierInvoice;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\RegistersEventListeners;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class FacturesImpayeesSupplierExport implements FromArray, WithTitle, WithColumnWidths, WithEvents
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
    private array $overdueRows = [];

    public function __construct(private ?int $supplierId = null) { $this->build(); }

    public function title(): string { return 'Factures impayées'; }
    public function array(): array  { return $this->rows; }
    public function columnWidths(): array { return ['A' => 14, 'B' => 34, 'C' => 16, 'D' => 14, 'E' => 14, 'F' => 18, 'G' => 18, 'H' => 10]; }
    public function registerEvents(): array { return [AfterSheet::class => fn(AfterSheet $e) => $this->applyStyles($e->sheet->getDelegate())]; }

    private function build(): void
    {
        $company = Company::first();
        $today   = Carbon::today();

        $this->push([$this->companyNameCell($company), null, null, 'FACTURES FOURNISSEURS IMPAYÉES', null, null, null, 'Au ' . $today->format('d/m/Y')], self::T_DOC_HEADER);
        $periodText = 'Factures avec solde restant dû';
        $this->push([$this->companyLegalLine($company, $periodText), null, null, null, null, null, null, 'Édition du ' . now()->format('d/m/Y à H\hi')], self::T_PERIOD);
        $this->push(array_fill(0, 8, null), self::T_BLANK);
        $this->push(['N° Facture', 'Fournisseur', 'Réf. Fourn.', 'Date', 'Échéance', 'Total TTC', 'Restant dû', 'Retard'], self::T_COL_HEADER);

        $query = SupplierInvoice::with('supplier')->where('remaining_amount', '>', 0)->whereNotIn('status', ['brouillon', 'annulee']);
        if ($this->supplierId) $query->where('supplier_id', $this->supplierId);
        $invoices = $query->orderBy('due_at')->lazy(1_000);

        foreach ($invoices as $inv) {
            $days    = $inv->due_at ? $today->diffInDays($inv->due_at, false) * -1 : 0;
            $overdue = $inv->due_at && $inv->due_at < $today;
            $this->push([$inv->number, $inv->supplier?->name ?? '—', $inv->supplier_invoice_number ?? '', $inv->received_at?->format('d/m/Y'), $inv->due_at?->format('d/m/Y'), $inv->total_ttc, $inv->remaining_amount, $overdue ? (int)$days . ' j' : '—'], self::T_DATA);
            $this->dataRows[] = $this->rowIdx;
            if ($overdue) $this->overdueRows[] = $this->rowIdx;
        }

        $this->push(['TOTAL', null, null, null, null, $invoices->sum('total_ttc') ?: null, $invoices->sum('remaining_amount') ?: null, null], self::T_TOTAL);
    }

    private function push(array $cells, string $type): void { $this->rows[] = $cells; $this->meta[++$this->rowIdx] = $type; }

    private function applyStyles(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $ws): void
    {
        $numFmt = '#,##0';
        foreach ($this->dataRows as $r) {
            $ws->getStyle('F' . $r)->getNumberFormat()->setFormatCode($numFmt);
            $ws->getStyle('G' . $r)->getNumberFormat()->setFormatCode($numFmt);
        }
        // Highlight overdue rows in light red
        foreach ($this->overdueRows as $r) {
            $ws->getStyle("A{$r}:H{$r}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FEF2F2');
            $ws->getStyle('H' . $r)->getFont()->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('DC2626'));
        }
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
        if ($totalRow) {
            $ws->getStyle('F' . $totalRow)->getNumberFormat()->setFormatCode($numFmt);
            $ws->getStyle('G' . $totalRow)->getNumberFormat()->setFormatCode($numFmt);
        }
        $ws->freezePane('A5');
        $ws->getPageSetup()->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE)->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_A4)->setFitToPage(true)->setFitToWidth(1)->setFitToHeight(0);
        $ws->getHeaderFooter()->setOddHeader('&L&BFactures Impayées Fournisseurs&R&P / &N');
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

    private function sColHeader($ws, int $r): void
    {
        $ws->getStyle("A{$r}:H{$r}")->applyFromArray(['font' => ['bold' => true, 'size' => 9, 'color' => ['rgb' => '7C2D12']], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FEF3C7']], 'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER], 'borders' => ['bottom' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'F97316']], 'top' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'F97316']]]]);
        $ws->getStyle("A{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $ws->getStyle("B{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $ws->getRowDimension($r)->setRowHeight(18);
    }

    private function sData($ws, int $r): void
    {
        $ws->getStyle("A{$r}:H{$r}")->applyFromArray(['font' => ['size' => 9], 'alignment' => ['vertical' => Alignment::VERTICAL_CENTER], 'borders' => ['bottom' => ['borderStyle' => Border::BORDER_HAIR, 'color' => ['rgb' => 'E5E7EB']]]]);
        foreach (['F', 'G'] as $c) { $ws->getStyle($c . $r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT); }
        $ws->getStyle('H' . $r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $ws->getRowDimension($r)->setRowHeight(13);
    }

    private function sTotal($ws, int $r): void
    {
        $ws->mergeCells("A{$r}:E{$r}");
        $ws->getStyle("A{$r}:H{$r}")->applyFromArray(['font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '7C2D12']], 'borders' => ['top' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => 'F97316']]], 'alignment' => ['vertical' => Alignment::VERTICAL_CENTER]]);
        foreach (['F', 'G'] as $c) { $ws->getStyle($c . $r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT); }
        $ws->getRowDimension($r)->setRowHeight(22);
    }
}
