<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Company;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\JournalType;
use App\Models\VatDeclaration;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class VatDeclarationService
{
    // [FIX-BUG-04] SYSCOHADA TVA account codes — these MUST match the codes
    // used by AccountingService when posting invoices (4431 collectée, 4455
    // déductible). Also list common neighbouring codes so manual entries on
    // 4432/4433/4452/4456/4457/4458 are still picked up by the declaration.
    const TVA_COLLECTEE_ACCOUNTS  = ['4431', '4432', '4433', '4434', '4457']; // TVA facturée sur ventes
    const TVA_DEDUCTIBLE_ACCOUNTS = ['4452', '4453', '4454', '4455', '4456', '4458']; // TVA récupérable sur achats

    public function __construct(private DocumentSequenceService $sequenceService) {}

    // ─────────────────────────────────────────────────────────────────────────
    // Calculate TVA amounts from journal entries for a period
    // ─────────────────────────────────────────────────────────────────────────
    public function calculatePeriod(string $dateFrom, string $dateTo): array
    {
        $company = Company::findOrFail(Auth::user()->company_id);

        // TVA collectée = credit sur comptes 441x/445x
        $tvaCollectee = $this->sumAccountMovements(
            self::TVA_COLLECTEE_ACCOUNTS, $dateFrom, $dateTo, 'credit'
        );

        // TVA déductible = debit sur comptes 4456/4458
        $tvaDeductible = $this->sumAccountMovements(
            self::TVA_DEDUCTIBLE_ACCOUNTS, $dateFrom, $dateTo, 'debit'
        );

        $tvaDue     = max(0, $tvaCollectee - $tvaDeductible);
        $creditTva  = max(0, $tvaDeductible - $tvaCollectee);

        return compact('tvaCollectee', 'tvaDeductible', 'tvaDue', 'creditTva');
    }

    public function create(array $data): VatDeclaration
    {
        return DB::transaction(function () use ($data) {
            $company            = Company::findOrFail(Auth::user()->company_id);

            // [PRIO-4] Anti-doublon : interdit 2 déclarations actives sur la même période
            if (!empty($data['period_start']) && !empty($data['period_end'])) {
                $existing = VatDeclaration::where('company_id', $company->id)
                    ->where('period_start', $data['period_start'])
                    ->where('period_end',   $data['period_end'])
                    ->whereNotIn('status', ['annule'])
                    ->first();
                if ($existing) {
                    throw new \RuntimeException(
                        'Une déclaration TVA active existe déjà pour cette période ('
                        . $existing->number . ', statut: ' . $existing->status . ').'
                    );
                }
            }

            $data['company_id'] = $company->id;
            $data['number']     = $this->sequenceService->nextNumber($company, 'declaration_tva');
            $data['created_by'] = Auth::id();
            $data['status']     = 'brouillon';

            // Auto-calculate if not provided
            if (empty($data['tva_collectee']) && !empty($data['period_start'])) {
                $calc = $this->calculatePeriod($data['period_start'], $data['period_end']);
                $data = array_merge($data, [
                    'tva_collectee'  => $calc['tvaCollectee'],
                    'tva_deductible' => $calc['tvaDeductible'],
                    'tva_due'        => $calc['tvaDue'],
                    'credit_tva'     => $calc['creditTva'],
                ]);
            }

            return VatDeclaration::create($data);
        });
    }

    public function submit(VatDeclaration $decl): VatDeclaration
    {
        if ($decl->status !== 'brouillon') {
            throw new \RuntimeException('Seules les déclarations en brouillon peuvent être soumises.');
        }
        $decl->update(['status' => 'soumis']);
        return $decl->fresh();
    }

    public function markPaid(VatDeclaration $decl, int $amount): VatDeclaration
    {
        if ($decl->status !== 'soumis') {
            throw new \RuntimeException('Seules les déclarations soumises peuvent être marquées comme payées.');
        }

        // [FIX-TVA-01] Validate amount does not exceed TVA due.
        if ($amount > (int) $decl->tva_due) {
            throw new \RuntimeException(
                'Le montant payé (' . number_format($amount, 0, ',', ' ') . ' FCFA) '
                . 'dépasse la TVA due (' . number_format($decl->tva_due, 0, ',', ' ') . ' FCFA).'
            );
        }

        return DB::transaction(function () use ($decl, $amount) {
            $decl->update([
                'status'      => 'paye',
                'amount_paid' => $amount,
            ]);

            // [FIX-TVA-02] Generate the GL journal entry for TVA payment:
            //   DR 4431 TVA collectée    = amount (reduces the TVA liability)
            //   CR 521  Banque           = amount (cash out)
            if ($amount > 0) {
                $this->postVatPayment($decl, $amount);
            }

            return $decl->fresh();
        });
    }

    /**
     * Post the GL entry for a TVA payment:
     *   DR 4431 TVA collectée (reduces liability)
     *   CR 521  Banque (cash out)
     */
    private function postVatPayment(VatDeclaration $decl, int $amount): void
    {
        $company = Company::findOrFail(Auth::user()->company_id);

        // Resolve accounts — use firstOrCreate so missing accounts are auto-bootstrapped
        $classId4 = $this->classId($company, 4);
        $classId5 = $this->classId($company, 5);

        $tvaAccount = Account::firstOrCreate(
            ['company_id' => $company->id, 'code' => '4431'],
            ['account_class_id' => $classId4, 'name' => 'TVA facturée sur ventes', 'type' => 'passif', 'is_detail' => true, 'is_active' => true, 'debit_balance' => 0, 'credit_balance' => 0]
        );

        $bankAccount = Account::firstOrCreate(
            ['company_id' => $company->id, 'code' => '521'],
            ['account_class_id' => $classId5, 'name' => 'Banques, chèques postaux', 'type' => 'actif', 'is_detail' => true, 'is_active' => true, 'debit_balance' => 0, 'credit_balance' => 0]
        );

        $journalType = JournalType::firstOrCreate(
            ['company_id' => $company->id, 'code' => 'OD'],
            ['name' => 'Opérations diverses', 'type' => 'operations_diverses', 'is_active' => true]
        );

        $number = app(DocumentSequenceService::class)->nextNumber($company, 'ecriture_comptable');

        $entry = JournalEntry::create([
            'company_id'      => $company->id,
            'journal_type_id' => $journalType->id,
            'fiscal_year_id'  => $company->current_fiscal_year_id,
            'number'          => $number,
            'entry_date'      => now()->toDateString(),
            'value_date'      => now()->toDateString(),
            'reference'       => $decl->number,
            'description'     => 'Paiement TVA — ' . $decl->period_label,
            'status'          => 'valide',
            'total_debit'     => $amount,
            'total_credit'    => $amount,
            'created_by'      => Auth::id(),
            'validated_by'    => Auth::id(),
            'validated_at'    => now(),
        ]);

        $lines = [
            ['account_id' => $tvaAccount->id, 'label' => 'Paiement TVA '.$decl->period_label, 'debit' => $amount, 'credit' => 0],
            ['account_id' => $bankAccount->id, 'label' => 'Paiement TVA '.$decl->period_label, 'debit' => 0,      'credit' => $amount],
        ];

        foreach ($lines as $i => $line) {
            $entry->lines()->create(['sort_order' => $i] + $line);
            Account::where('id', $line['account_id'])->increment('debit_balance',  $line['debit']);
            Account::where('id', $line['account_id'])->increment('credit_balance', $line['credit']);
        }
    }

    private function classId(Company $company, int $number): int
    {
        return \App\Models\AccountClass::firstOrCreate(
            ['company_id' => $company->id, 'number' => $number],
            ['name' => 'Classe ' . $number]
        )->id;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Detail lines for TVA breakdown (by account code)
    // ─────────────────────────────────────────────────────────────────────────
    public function getDetail(string $dateFrom, string $dateTo): array
    {
        $collectee = $this->getAccountBreakdown(self::TVA_COLLECTEE_ACCOUNTS, $dateFrom, $dateTo, 'credit');
        $deductible = $this->getAccountBreakdown(self::TVA_DEDUCTIBLE_ACCOUNTS, $dateFrom, $dateTo, 'debit');

        return [
            'collectee'  => $collectee,
            'deductible' => $deductible,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Private helpers
    // ─────────────────────────────────────────────────────────────────────────
    private function sumAccountMovements(array $codes, string $from, string $to, string $side): int
    {
        $companyId = Auth::user()->company_id;

        return (int) JournalEntryLine::query()
            ->whereHas('account', function ($q) use ($codes, $companyId) {
                $q->where('company_id', $companyId)
                  ->where(function ($sub) use ($codes) {
                      foreach ($codes as $code) {
                          $sub->orWhere('code', 'like', $code . '%');
                      }
                  });
            })
            ->whereHas('journalEntry', fn ($q) => $q
                ->where('company_id', $companyId)
                ->where('status', 'valide')
                ->whereBetween('entry_date', [$from, $to])
            )
            ->sum($side);
    }

    private function getAccountBreakdown(array $codes, string $from, string $to, string $side): \Illuminate\Support\Collection
    {
        $companyId = Auth::user()->company_id;

        return JournalEntryLine::query()
            ->selectRaw('account_id, SUM(' . $side . ') as total')
            ->with('account:id,code,name')
            ->whereHas('account', function ($q) use ($codes, $companyId) {
                $q->where('company_id', $companyId)
                  ->where(function ($sub) use ($codes) {
                      foreach ($codes as $code) {
                          $sub->orWhere('code', 'like', $code . '%');
                      }
                  });
            })
            ->whereHas('journalEntry', fn ($q) => $q
                ->where('company_id', $companyId)
                ->where('status', 'valide')
                ->whereBetween('entry_date', [$from, $to])
            )
            ->groupBy('account_id')
            ->get();
    }
}
