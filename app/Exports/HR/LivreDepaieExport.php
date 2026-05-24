<?php

namespace App\Exports\HR;

use App\Models\PayrollRun;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\RegistersEventListeners;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

/**
 * [RH-PRO] Livre de paie mensuel — export Excel.
 * Toutes les colonnes du bulletin : brut, HS, primes, CNSS, IUTS, net, coût.
 */
class LivreDepaieExport implements FromArray, WithTitle, WithColumnWidths, WithEvents
{
    use RegistersEventListeners;

    private array  $rows   = [];
    private array  $meta   = [];
    private int    $dataStartRow = 5;

    public function __construct(private PayrollRun $run)
    {
        $this->build();
    }

    // ─── Interface implementations ───────────────────────────────────────────

    public function array(): array { return $this->rows; }

    public function title(): string
    {
        return "Livre Paie {$this->run->period_month}-{$this->run->period_year}";
    }

    public function columnWidths(): array
    {
        return [
            'A' => 12, 'B' => 28, 'C' => 16, 'D' => 16,
            'E' => 12, 'F' => 12, 'G' => 14, 'H' => 14,
            'I' => 12, 'J' => 12, 'K' => 14, 'L' => 14,
            'M' => 14, 'N' => 14, 'O' => 14,
        ];
    }

    // ─── Build rows ──────────────────────────────────────────────────────────

    private function build(): void
    {
        $run     = $this->run->load(['items' => fn($q) => $q->orderBy('employee_name'), 'company']);
        $company = $run->company;

        $months = ['Janvier','Février','Mars','Avril','Mai','Juin',
                   'Juillet','Août','Septembre','Octobre','Novembre','Décembre'];
        $period = ($months[$run->period_month - 1] ?? $run->period_month) . ' ' . $run->period_year;

        // Row 1: Company name
        $this->rows[] = [$company->name ?? 'Entreprise'];
        $this->meta[] = ['type' => 'company'];

        // Row 2: Title
        $this->rows[] = ["LIVRE DE PAIE — {$period}"];
        $this->meta[] = ['type' => 'title'];

        // Row 3: Status
        $this->rows[] = ["Statut : " . strtoupper($run->status) . "  |  Employés : {$run->employee_count}"];
        $this->meta[] = ['type' => 'info'];

        // Row 4: Column headers
        $this->rows[] = [
            'Matricule', 'Nom & Prénom', 'Département', 'Poste',
            'Salaire Base', 'Brut', 'HS Total',
            'CNSS Salarié', 'CNSS Patronal', 'IUTS',
            'Net à Payer', 'Coût Employeur',
            'Cumul Brut YTD', 'Cumul IUTS YTD', 'Cumul Net YTD',
        ];
        $this->meta[] = ['type' => 'header'];
        $this->dataStartRow = 5;

        // Data rows
        foreach ($run->items as $item) {
            $hsTotal = ($item->hs_25_amount ?? 0) + ($item->hs_50_amount ?? 0) + ($item->hs_nuit_amount ?? 0);
            $this->rows[] = [
                $item->employee_matricule,
                $item->employee_name,
                $item->department_name ?? '',
                $item->job_title ?? '',
                $item->base_salary,
                $item->salaire_brut,
                $hsTotal,
                $item->cnss_employee,
                $item->cnss_employer,
                $item->iuts_amount,
                $item->salaire_net,
                $item->cout_employeur,
                $item->cumul_brut_ytd ?? $item->salaire_brut,
                $item->cumul_iuts_ytd ?? $item->iuts_amount,
                $item->cumul_net_ytd  ?? $item->salaire_net,
            ];
            $this->meta[] = ['type' => 'data'];
        }

        // Totals
        $hsTotal = $run->items->sum(fn($i) => ($i->hs_25_amount ?? 0) + ($i->hs_50_amount ?? 0) + ($i->hs_nuit_amount ?? 0));
        $this->rows[] = [
            '', 'TOTAUX', '', '',
            $run->items->sum('base_salary'),
            $run->total_brut,
            $hsTotal,
            $run->total_cnss_employee,
            $run->total_cnss_employer,
            $run->total_iuts,
            $run->total_net,
            $run->total_brut + $run->total_cnss_employer,
            '', '', '',
        ];
        $this->meta[] = ['type' => 'total'];
    }

    // ─── Styling ─────────────────────────────────────────────────────────────

    public static function afterSheet(AfterSheet $event): void
    {
        $sheet = $event->sheet->getDelegate();
        $export = $event->getConcernable();
        $lastRow = count($export->rows) + 1;
        $lastDataRow = $lastRow - 1;
        $headerRow = 4;
        $dataStart = $export->dataStartRow;

        // Row 1 — Company name
        $sheet->mergeCells("A1:O1");
        $sheet->getStyle('A1')->applyFromArray([
            'font'      => ['bold' => true, 'size' => 13, 'color' => ['argb' => 'FF1E3A5F']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        // Row 2 — Title
        $sheet->mergeCells("A2:O2");
        $sheet->getStyle('A2')->applyFromArray([
            'font'      => ['bold' => true, 'size' => 11],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'color' => ['argb' => 'FFE8F0FE']],
        ]);

        // Row 3 — Info
        $sheet->mergeCells("A3:O3");

        // Row 4 — Header
        $sheet->getStyle("A{$headerRow}:O{$headerRow}")->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'color' => ['argb' => 'FF1E3A5F']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'wrapText' => true],
            'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFFFFFFF']]],
        ]);
        $sheet->getRowDimension($headerRow)->setRowHeight(30);

        // Data rows — number format for numeric columns
        $numCols = ['E','F','G','H','I','J','K','L','M','N','O'];
        if ($dataStart <= $lastDataRow) {
            foreach ($numCols as $col) {
                $sheet->getStyle("{$col}{$dataStart}:{$col}{$lastDataRow}")
                      ->getNumberFormat()->setFormatCode('#,##0');
            }
            // Zebra striping
            for ($r = $dataStart; $r <= $lastDataRow; $r++) {
                if ($r % 2 === 0) {
                    $sheet->getStyle("A{$r}:O{$r}")
                          ->getFill()->setFillType(Fill::FILL_SOLID)
                          ->getStartColor()->setARGB('FFF8FAFF');
                }
            }
        }

        // Total row
        $sheet->getStyle("A{$lastRow}:O{$lastRow}")->applyFromArray([
            'font' => ['bold' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['argb' => 'FFEFF6FF']],
            'borders' => ['top' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['argb' => 'FF1E3A5F']]],
        ]);
        foreach ($numCols as $col) {
            $sheet->getStyle("{$col}{$lastRow}")->getNumberFormat()->setFormatCode('#,##0');
        }

        // All-data borders
        if ($dataStart <= $lastRow) {
            $sheet->getStyle("A{$headerRow}:O{$lastRow}")->applyFromArray([
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFD1D5DB']]],
            ]);
        }

        // Freeze header
        $sheet->freezePane("A{$dataStart}");
    }

    /** Expose rows count for the AfterSheet listener */
    public function getRowsCount(): int { return count($this->rows); }
}
