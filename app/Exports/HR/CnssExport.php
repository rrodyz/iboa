<?php

namespace App\Exports\HR;

use App\Models\PayrollRun;
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

/**
 * [RH-PRO] Bordereau CNSS — export Excel.
 * Cotisations salariales + patronales, prêt pour transmission à la CNSS.
 */
class CnssExport implements FromArray, WithTitle, WithColumnWidths, WithEvents
{
    use RegistersEventListeners;

    private array $rows    = [];
    private PayrollSetting $payroll;

    public function __construct(private PayrollRun $run)
    {
        $this->payroll = PayrollSetting::forCompany($run->company_id ?? $run->company->id);
        $this->build();
    }

    public function array(): array { return $this->rows; }

    public function title(): string
    {
        return "CNSS {$this->run->period_month}-{$this->run->period_year}";
    }

    public function columnWidths(): array
    {
        return ['A' => 14, 'B' => 30, 'C' => 20, 'D' => 18, 'E' => 18, 'F' => 18, 'G' => 18];
    }

    private function build(): void
    {
        $run     = $this->run->load(['items' => fn($q) => $q->orderBy('employee_name'), 'company']);
        $company = $run->company;

        $months  = ['Janvier','Février','Mars','Avril','Mai','Juin',
                    'Juillet','Août','Septembre','Octobre','Novembre','Décembre'];
        $period  = ($months[$run->period_month - 1] ?? $run->period_month) . ' ' . $run->period_year;

        $this->rows[] = [$company->name ?? 'Entreprise'];
        $this->rows[] = ["BORDEREAU CNSS — {$period}"];
        $this->rows[] = ["N° Employeur CNSS : " . ($company->cnss_number ?? '—')];
        $this->rows[] = [];
        $this->rows[] = [
            'Matricule', 'Nom & Prénom', 'N° CNSS Salarié',
            'Salaire Brut', 'Base CNSS',
            'CNSS Salarié (' . $this->payroll->cnss_employee_rate . '%)',
            'CNSS Patronal (' . $this->payroll->cnss_employer_rate . '%)',
        ];

        foreach ($run->items as $item) {
            $this->rows[] = [
                $item->employee_matricule,
                $item->employee_name,
                $item->employee->cnss_number ?? '',
                $item->salaire_brut,
                $item->cnss_base,
                $item->cnss_employee,
                $item->cnss_employer,
            ];
        }

        $this->rows[] = [
            '', 'TOTAL', '',
            $run->total_brut,
            $run->items->sum('cnss_base'),
            $run->total_cnss_employee,
            $run->total_cnss_employer,
        ];
    }

    public static function afterSheet(AfterSheet $event): void
    {
        $sheet   = $event->sheet->getDelegate();
        $last    = count($event->getConcernable()->rows);
        $dataStart = 6;

        // Headers
        $sheet->mergeCells('A1:G1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(12);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->mergeCells('A2:G2');
        $sheet->getStyle('A2')->getFont()->setBold(true);
        $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Column header row (row 5)
        $sheet->getStyle('A5:G5')->applyFromArray([
            'font'  => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
            'fill'  => ['fillType' => Fill::FILL_SOLID, 'color' => ['argb' => 'FF1E3A5F']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        // Numeric columns D-G
        foreach (['D','E','F','G'] as $col) {
            if ($dataStart <= $last) {
                $sheet->getStyle("{$col}{$dataStart}:{$col}{$last}")
                      ->getNumberFormat()->setFormatCode('#,##0');
            }
        }

        // Total row
        $sheet->getStyle("A{$last}:G{$last}")->getFont()->setBold(true);
        $sheet->getStyle("A{$last}:G{$last}")
              ->getFill()->setFillType(Fill::FILL_SOLID)
              ->getStartColor()->setARGB('FFEFF6FF');
        $sheet->getStyle("A{$last}:G{$last}")
              ->getBorders()->getTop()->setBorderStyle(Border::BORDER_MEDIUM);

        // Table borders
        if ($dataStart <= $last) {
            $sheet->getStyle("A5:G{$last}")->applyFromArray([
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFD1D5DB']]],
            ]);
        }
        $sheet->freezePane("A{$dataStart}");
    }
}
