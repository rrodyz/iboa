<?php

namespace App\Imports;

use App\Models\Account;
use App\Models\AccountClass;
use App\Models\Company;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class AccountsImport implements ToCollection, WithHeadingRow
{
    private int $companyId;

    public int   $imported   = 0;
    public int   $skipped    = 0;
    public array $errorLines = [];

    public function __construct(int $companyId)
    {
        $this->companyId = $companyId;
    }

    public function collection(Collection $rows): void
    {
        // Pre-load account classes indexed by their number (string key)
        $classMap = AccountClass::where('company_id', $this->companyId)
            ->get(['id', 'number'])
            ->keyBy(fn ($c) => (string) $c->number);

        $validTypes = ['actif', 'passif', 'charge', 'produit', 'bilan', 'resultat'];

        // ── Pass 1 : create / update all accounts (parent_id = null for now) ──
        foreach ($rows as $i => $row) {
            $rowNum  = $i + 2; // row 1 is the heading
            $code    = trim((string) ($row['code']    ?? ''));
            $libelle = trim((string) ($row['libelle'] ?? ''));
            $classe  = trim((string) ($row['classe']  ?? ''));
            $type    = strtolower(trim((string) ($row['type'] ?? '')));

            if ($code === '' || $libelle === '') {
                $this->skipped++;
                continue;
            }

            if (! in_array($type, $validTypes, true)) {
                $type = 'bilan';
            }

            $classId = isset($classMap[$classe]) ? $classMap[$classe]->id : null;

            $saisissable = strtolower(trim((string) ($row['saisissable'] ?? 'non')));
            $isDetail    = in_array($saisissable, ['oui', 'yes', '1', 'true'], true);

            $actif    = strtolower(trim((string) ($row['actif'] ?? 'oui')));
            $isActive = ! in_array($actif, ['non', 'no', '0', 'false'], true);

            try {
                Account::updateOrCreate(
                    ['company_id' => $this->companyId, 'code' => $code],
                    [
                        'name'             => $libelle,
                        'account_class_id' => $classId,
                        'type'             => $type,
                        'is_detail'        => $isDetail,
                        'is_active'        => $isActive,
                        'parent_id'        => null,
                    ]
                );
                $this->imported++;
            } catch (\Throwable $e) {
                $this->errorLines[] = "Ligne {$rowNum} ({$code}) : " . $e->getMessage();
                $this->skipped++;
            }
        }

        // ── Pass 2 : wire parent relationships ────────────────────────────────
        $accountMap = Account::where('company_id', $this->companyId)
            ->get(['id', 'code'])
            ->keyBy('code');

        foreach ($rows as $row) {
            $code       = trim((string) ($row['code']   ?? ''));
            $parentCode = trim((string) ($row['parent'] ?? ''));

            if ($code === '' || $parentCode === '') {
                continue;
            }

            $account = $accountMap->get($code);
            $parent  = $accountMap->get($parentCode);

            if ($account && $parent && $account->parent_id !== $parent->id) {
                $account->update(['parent_id' => $parent->id]);
            }
        }
    }
}
