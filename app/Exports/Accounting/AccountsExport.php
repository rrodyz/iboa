<?php

namespace App\Exports\Accounting;

use App\Models\Account;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class AccountsExport implements FromQuery, WithHeadings, WithMapping, WithTitle, ShouldAutoSize, WithStyles
{
    public function __construct(
        private int  $companyId,
        private ?int $classId = null,
    ) {}

    public function title(): string
    {
        return 'Plan comptable';
    }

    public function query()
    {
        return Account::with(['accountClass', 'parent'])
            ->where('company_id', $this->companyId)
            ->when($this->classId, fn ($q) => $q->where('account_class_id', $this->classId))
            ->orderBy('code');
    }

    public function headings(): array
    {
        // These exact headers are expected by AccountsImport — do not rename them.
        return ['code', 'libelle', 'classe', 'type', 'parent', 'saisissable', 'actif'];
    }

    public function map($account): array
    {
        return [
            $account->code,
            $account->name,
            $account->accountClass?->number,
            $account->type,
            $account->parent?->code,
            $account->is_detail ? 'Oui' : 'Non',
            $account->is_active ? 'Oui' : 'Non',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '7C3AED']],
            ],
        ];
    }
}
