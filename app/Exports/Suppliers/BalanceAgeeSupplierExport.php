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

class BalanceAgeeSupplierExport implements FromArray, WithTitle, WithColumnWidths, WithEvents
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

    public function __construct(private ?int $supplierId = null) { $this->build(); }

    public function title(): string { return 'Balance âgée fournisseurs'; }
    public function array(): array  { return $this->rows; }
    public function columnWidths(): array { return ['A' => 10, 'B' => 32, 'C' => 18, 'D' => 16, 'E' => 16, 'F' => 16, 'G' => 16, 'H' => 16]; }
    public function registerEvents(): array { return [AfterSheet::class => fn(AfterSheet $e) => $this->applyStyles($e->sheet->getDelegate())]; }

    private function build(): void
    {
        $company = Company::first();
        $today   = Carbon::today();

        $this->push([$this->companyNameCell($company), null, null, 'BALANCE ÂGÉE FOURNISSEURS', null, null, null, 'Au ' . $today->format('d/m/Y')], self::T_DOC_HEADER);
        $periodText = 'Dettes en cours ventilées par ancienneté';
        $this->push([$this->companyLegalLine($company, $periodText), null, null, null, null, null, null, 'Édition du ' . now()->format('d/m/Y à H\hi')], self::T_PERIOD);
        $this->push(array_fill(0, 8, null), self::T_BLANK);
        $this->push(['Code', 'Fournisseur', 'Total dû (FCFA)', 'Non échu', '1 – 30 j', '31 – 60 j', '61 – 90 j', '+ 90 j'], self::T_COL_HEADER);

        $query = SupplierInvoice::with('supplier')->whereNotIn('status', ['brouillon', 'annulee'])->where('remaining_amount', '>', 0);
        if ($this->supplierId) $query->where('supplier_id', $this->supplierId);

        $data = [];
        foreach ($query->get() as $inv) {
            $sid    = $inv->supplier_id;
            $amount = (int) $inv->remaining_amount;
            $due    = $inv->due_at;
            $days   = $due ? (int) $today->diffInDays($due, false) * -1 : 0;
            if (!isset($data[$sid])) {
                $data[$sid] = ['code' => $inv->supplier?->code ?? '', 'name' => $inv->supplier?->name ?? '—', 'total' => 0, 'non_echu' => 0, 'j1_30' => 0, 'j31_60' => 0, 'j61_90' => 0, 'j90p' => 0];
            }
            $data[$sid]['total'] += $amount;
            if (!$due || $days <= 0)  { $data[$sid]['non_echu'] += $amount; }
            elseif ($days <= 30)      { $data[$sid]['j1_30']    += $amount; }
            elseif ($days <= 60)      { $data[$sid]['j31_60']   += $amount; }
            elseif ($days <= 90)      { $data[$sid]['j61_90']   += $amount; }
            else                      { $data[$sid]['j90p']     += $amount; }
        }

        $rows = collect(array_values($data))->sortByDesc('total');
        foreach ($rows as $row) {
            $this->push([$row['code'], $row['name'], $row['total'] ?: null, $row['non_echu'] ?: null, $row['j1_30'] ?: null, $row['j31_60'] ?: null, $row['j61_90'] ?: null, $row['j90p'] ?: null], self::T_DATA);
            $this->dataRows[] = $this->rowIdx;
        }

        $this->push(['', 'TOTAL', $rows->sum('total') ?: null, $rows->sum('non_echu') ?: null, $rows->sum('j1_30') ?: null, $rows->sum('j31_60') ?: null, $rows->sum('j61_90') ?: null, $rows->sum('j90p') ?: null], self::T_TOTAL);
    }

    private function push(array $cells, string $type): void { $this->rows[] = $cells; $this->meta[++$this->rowIdx] = $type; }

    private function applyStyles(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $ws): void
    {
        $numFmt = '#,##0'; $numCols = ['C', 'D', 'E', 'F', 'G', 'H'];
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
        $ws->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd(1, 4);
        $ws->getHeaderFooter()->setOddHeader('&L&BBalance Âgée Fournisseurs&R&P / &N');
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
        $ws->getStyle("A{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $ws->getStyle("H{$r}")->applyFromArray(['font' => ['italic' => true, 'size' => 9, 'color' => ['rgb' => 'FDE68A']], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'B45309']], 'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT]]);
        $ws->getRowDimension($r)->setRowHeight(16);
    }

    private function sColHeader($ws, int $r): void
    {
        $colColors = ['D' => 'FEF3C7', 'E' => 'FEF9C3', 'F' => 'FFEDD5', 'G' => 'FEE2E2', 'H' => 'FECACA'];
        $ws->getStyle("A{$r}:H{$r}")->applyFromArray(['font' => ['bold' => true, 'size' => 9, 'color' => ['rgb' => '7C2D12']], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FEF3C7']], 'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER], 'borders' => ['bottom' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'F97316']], 'top' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'F97316']]]]);
        foreach ($colColors as $col => $bg) { $ws->getStyle($col . $r)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($bg); }
        $ws->getStyle("A{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $ws->getStyle("B{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $ws->getRowDimension($r)->setRowHeight(20);
    }

    private function sData($ws, int $r): void
    {
        $ws->getStyle("A{$r}:H{$r}")->applyFromArray(['font' => ['size' => 9], 'alignment' => ['vertical' => Alignment::VERTICAL_CENTER], 'borders' => ['bottom' => ['borderStyle' => Border::BORDER_HAIR, 'color' => ['rgb' => 'E5E7EB']]]]);
        foreach (['C', 'D', 'E', 'F', 'G', 'H'] as $c) { $ws->getStyle($c . $r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT); }
        $ws->getRowDimension($r)->setRowHeight(13);
    }

    private function sTotal($ws, int $r): void
    {
        $ws->getStyle("A{$r}:H{$r}")->applyFromArray(['font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '7C2D12']], 'borders' => ['top' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => 'F97316']]], 'alignment' => ['vertical' => Alignment::VERTICAL_CENTER]]);
        $ws->getStyle("B{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        foreach (['C', 'D', 'E', 'F', 'G', 'H'] as $c) { $ws->getStyle($c . $r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT); }
        $ws->getRowDimension($r)->setRowHeight(22);
    }
}
