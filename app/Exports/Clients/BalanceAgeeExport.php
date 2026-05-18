<?php

namespace App\Exports\Clients;

use App\Models\Company;
use App\Models\Invoice;
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

class BalanceAgeeExport implements FromArray, WithTitle, WithColumnWidths, WithEvents
{
    use \App\Exports\Concerns\HasCompanyHeader;
    use RegistersEventListeners;

    private const T_DOC_HEADER = 'doc_header';
    private const T_PERIOD     = 'period';
    private const T_BLANK      = 'blank';
    private const T_COL_HEADER = 'col_header';
    private const T_DATA       = 'data';
    private const T_TOTAL      = 'total';

    private array $rows     = [];
    private array $meta     = [];
    private int   $rowIdx   = 0;
    private array $dataRows = [];

    public function __construct(private ?int $clientId = null)
    {
        $this->build();
    }

    public function title(): string { return 'Balance âgée clients'; }
    public function array(): array  { return $this->rows; }

    public function columnWidths(): array
    {
        return ['A' => 10, 'B' => 32, 'C' => 18, 'D' => 16, 'E' => 16, 'F' => 16, 'G' => 16, 'H' => 16];
    }

    public function registerEvents(): array
    {
        return [AfterSheet::class => fn(AfterSheet $e) => $this->applyStyles($e->sheet->getDelegate())];
    }

    private function build(): void
    {
        $company = Company::first();
        $today   = Carbon::today();

        $this->push([$this->companyNameCell($company), null, null, 'BALANCE ÂGÉE CLIENTS', null, null, null,
            'Au ' . $today->format('d/m/Y')], self::T_DOC_HEADER);

        $periodText = 'Créances en cours ventilées par ancienneté';
        $this->push([$this->companyLegalLine($company, $periodText), null, null, null, null, null, null,
            'Édition du ' . now()->format('d/m/Y à H\hi')], self::T_PERIOD);

        $this->push(array_fill(0, 8, null), self::T_BLANK);

        $this->push(['Code', 'Client', 'Total dû (FCFA)', 'Non échu', '1 – 30 j', '31 – 60 j', '61 – 90 j', '+ 90 j'], self::T_COL_HEADER);

        [$rows, $totals] = $this->computeRows($today);

        foreach ($rows as $row) {
            $this->push([
                $row['code'],
                $row['name'],
                $row['total']    ?: null,
                $row['non_echu'] ?: null,
                $row['j1_30']    ?: null,
                $row['j31_60']   ?: null,
                $row['j61_90']   ?: null,
                $row['j90p']     ?: null,
            ], self::T_DATA);
            $this->dataRows[] = $this->rowIdx;
        }

        $this->push(['', 'TOTAL',
            $totals['total']    ?: null,
            $totals['non_echu'] ?: null,
            $totals['j1_30']    ?: null,
            $totals['j31_60']   ?: null,
            $totals['j61_90']   ?: null,
            $totals['j90p']     ?: null,
        ], self::T_TOTAL);
    }

    private function computeRows(Carbon $today): array
    {
        $query = Invoice::with('client')
            ->whereNotIn('status', ['brouillon', 'annulee'])
            ->where('remaining_amount', '>', 0);

        if ($this->clientId) {
            $query->where('client_id', $this->clientId);
        }

        $data = [];
        foreach ($query->get() as $inv) {
            $cid    = $inv->client_id;
            $amount = (int) $inv->remaining_amount;
            $due    = $inv->due_at;
            $days   = $due ? (int) $today->diffInDays($due, false) * -1 : 0;

            if (!isset($data[$cid])) {
                $data[$cid] = ['code' => $inv->client?->code ?? '', 'name' => $inv->client?->name ?? '—', 'total' => 0, 'non_echu' => 0, 'j1_30' => 0, 'j31_60' => 0, 'j61_90' => 0, 'j90p' => 0];
            }
            $data[$cid]['total'] += $amount;
            if (!$due || $days <= 0) { $data[$cid]['non_echu'] += $amount; }
            elseif ($days <= 30)     { $data[$cid]['j1_30']   += $amount; }
            elseif ($days <= 60)     { $data[$cid]['j31_60']  += $amount; }
            elseif ($days <= 90)     { $data[$cid]['j61_90']  += $amount; }
            else                     { $data[$cid]['j90p']    += $amount; }
        }

        $rows = collect(array_values($data))->sortByDesc('total');
        $totals = ['total' => $rows->sum('total'), 'non_echu' => $rows->sum('non_echu'), 'j1_30' => $rows->sum('j1_30'), 'j31_60' => $rows->sum('j31_60'), 'j61_90' => $rows->sum('j61_90'), 'j90p' => $rows->sum('j90p')];

        return [$rows, $totals];
    }

    private function push(array $cells, string $type): void
    {
        $this->rows[]             = $cells;
        $this->meta[++$this->rowIdx] = $type;
    }

    private function applyStyles(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $ws): void
    {
        $numFmt  = '#,##0';
        $numCols = ['C', 'D', 'E', 'F', 'G', 'H'];

        foreach ($this->dataRows as $r) {
            foreach ($numCols as $c) {
                $ws->getStyle($c . $r)->getNumberFormat()->setFormatCode($numFmt);
            }
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
            foreach ($numCols as $c) {
                $ws->getStyle($c . $totalRow)->getNumberFormat()->setFormatCode($numFmt);
            }
        }

        $ws->freezePane('A5');
        $ws->getPageSetup()->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE)
            ->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_A4)->setFitToPage(true)->setFitToWidth(1)->setFitToHeight(0);
        $ws->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd(1, 4);
        $ws->getHeaderFooter()->setOddHeader('&L&BBalance Âgée Clients&R&P / &N');
        $ws->getHeaderFooter()->setOddFooter('&LÉdité le ' . now()->format('d/m/Y') . '&R&F');
        $ws->setPrintGridlines(false);

        // Company header text wrapping
        $this->applyCompanyHeaderWrap($ws, 1, 2);
    }

    private function sDocHeader($ws, int $r): void
    {
        $ws->mergeCells("A{$r}:C{$r}"); $ws->mergeCells("D{$r}:G{$r}");
        $ws->getStyle("A{$r}:H{$r}")->applyFromArray(['font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E3A5F']], 'alignment' => ['vertical' => Alignment::VERTICAL_CENTER]]);
        $ws->getStyle("A{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $ws->getStyle("D{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $ws->getStyle("H{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $ws->getRowDimension($r)->setRowHeight(24);
    }

    private function sPeriod($ws, int $r): void
    {
        $ws->mergeCells("A{$r}:G{$r}");
        $ws->getStyle("A{$r}:H{$r}")->applyFromArray(['font' => ['italic' => true, 'size' => 9, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2D5986']], 'alignment' => ['vertical' => Alignment::VERTICAL_CENTER]]);
        $ws->getStyle("A{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $ws->getStyle("H{$r}")->applyFromArray(['font' => ['italic' => true, 'size' => 9, 'color' => ['rgb' => 'D1D5DB']], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2D5986']], 'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT]]);
        $ws->getRowDimension($r)->setRowHeight(16);
    }

    private function sColHeader($ws, int $r): void
    {
        // Buckets headers colored
        $colColors = ['D' => 'DBEAFE', 'E' => 'FEF9C3', 'F' => 'FFEDD5', 'G' => 'FEE2E2', 'H' => 'FECACA'];
        $ws->getStyle("A{$r}:H{$r}")->applyFromArray(['font' => ['bold' => true, 'size' => 9, 'color' => ['rgb' => '1E3A5F']], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E0E7FF']], 'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true], 'borders' => ['bottom' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '6366F1']], 'top' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '6366F1']]]]);
        foreach ($colColors as $col => $bg) {
            $ws->getStyle($col . $r)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($bg);
        }
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
        $ws->getStyle("A{$r}:H{$r}")->applyFromArray(['font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E3A5F']], 'borders' => ['top' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => '6366F1']]], 'alignment' => ['vertical' => Alignment::VERTICAL_CENTER]]);
        $ws->getStyle("B{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        foreach (['C', 'D', 'E', 'F', 'G', 'H'] as $c) { $ws->getStyle($c . $r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT); }
        $ws->getRowDimension($r)->setRowHeight(22);
    }
}
