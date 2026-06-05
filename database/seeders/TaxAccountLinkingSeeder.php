<?php

namespace Database\Seeders;

use App\Models\AccountClass;
use App\Models\Company;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Relie les taux TVA aux comptes comptables SYSCOHADA d'une société.
 * Crée les comptes manquants : 4432, 431, 4311, 4312.
 * Corrige le libellé du compte 641.
 *
 * SEEDER DE RÉFÉRENCE (production-safe, idempotent) — company-scopé.
 * La société et la classe comptable 4 sont résolues dynamiquement
 * (plus aucun id codé en dur), donc le seeder est portable d'un
 * environnement à l'autre. Nécessite qu'une société et son plan
 * comptable SYSCOHADA existent (SyscohadaChartOfAccountsSeeder d'abord).
 */
class TaxAccountLinkingSeeder extends Seeder
{
    private int $companyId;
    private int $classId4;  // account_classes.id pour la classe 4

    public function run(): void
    {
        // ── Résolution dynamique de la société et de la classe 4 ─────────────
        $company = Company::orderBy('id')->first();
        if (! $company) {
            $this->command->warn('TaxAccountLinkingSeeder : aucune société — seeder ignoré.');
            return;
        }
        $this->companyId = $company->id;

        $classId4 = AccountClass::where('company_id', $this->companyId)
            ->where('number', 4)
            ->value('id');
        if (! $classId4) {
            $this->command->warn(
                "TaxAccountLinkingSeeder : classe comptable 4 absente pour la société #{$this->companyId} ".
                '(lancer SyscohadaChartOfAccountsSeeder d\'abord) — seeder ignoré.'
            );
            return;
        }
        $this->classId4 = $classId4;

        DB::beginTransaction();

        try {
            $this->createMissingAccounts();
            $this->linkTaxRates();
            $this->fix641();
            $this->createCnssAccounts();

            DB::commit();
            $this->command->info('✅ TaxAccountLinkingSeeder terminé avec succès.');
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->command->error('❌ Erreur : ' . $e->getMessage());
            throw $e;
        }
    }

    // ─── Comptes TVA ─────────────────────────────────────────────────────────

    private function createMissingAccounts(): void
    {
        // 4432 — TVA récupérable sur achats (déductible)
        $this->ensureAccount('4432', 'TVA récupérable sur achats', 'actif');

        $this->command->info('Comptes TVA vérifiés/créés.');
    }

    // ─── Liaison tax_rates ────────────────────────────────────────────────────

    private function linkTaxRates(): void
    {
        $account4431 = $this->getAccountId('4431');
        $account4432 = $this->getAccountId('4432');
        $account4473 = $this->getAccountId('4473'); // retenues IS — utilisé pour BIC2

        // TVA18 : 18% — collectée → 4431, déductible → 4432
        $updated = DB::table('tax_rates')
            ->where('short_name', 'TVA18')
            ->update([
                'collected_account_id'  => $account4431,
                'deductible_account_id' => $account4432,
                'updated_at'            => now(),
            ]);
        $this->command->info("TVA18 → collected=4431(id={$account4431}), deductible=4432(id={$account4432}) — lignes MAJ: {$updated}");

        // EXO : 0% — aucun compte nécessaire (laisser null)
        $this->command->info('EXO (0%) — aucune liaison de compte requise.');

        // BIC2 : 2% retenue à la source → compte 4473 (État — retenues IS)
        $updated = DB::table('tax_rates')
            ->where('short_name', 'BIC2')
            ->update([
                'collected_account_id'  => $account4473,
                'deductible_account_id' => null,
                'updated_at'            => now(),
            ]);
        $this->command->info("BIC2 → collected=4473(id={$account4473}) — lignes MAJ: {$updated}");
    }

    // ─── Correction compte 641 ────────────────────────────────────────────────

    private function fix641(): void
    {
        $updated = DB::table('accounts')
            ->where('company_id', $this->companyId)
            ->where('code', '641')
            ->where('name', 'Impôts et taxes directs') // sécurité : ne corriger que si mauvais libellé
            ->update([
                'name'       => 'Appointements et salaires',
                'updated_at' => now(),
            ]);

        if ($updated) {
            $this->command->info("✅ Compte 641 corrigé : 'Appointements et salaires'");
        } else {
            $this->command->warn("Compte 641 : déjà correct ou introuvable (aucune ligne modifiée).");
        }
    }

    // ─── Comptes CNSS 431x ───────────────────────────────────────────────────

    private function createCnssAccounts(): void
    {
        // Compte parent 431
        $this->ensureAccount('431', 'Organismes de prévoyance sociale', 'passif', false);

        $parent431 = $this->getAccountId('431');

        // 4311 — CNSS part salariale
        $this->ensureAccount('4311', 'CNSS — cotisations part salariale', 'passif', true, $parent431);

        // 4312 — CNSS part patronale
        $this->ensureAccount('4312', 'CNSS — cotisations part patronale', 'passif', true, $parent431);

        $this->command->info('Comptes CNSS 431/4311/4312 vérifiés/créés.');
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function ensureAccount(
        string $code,
        string $name,
        string $type,
        bool $isDetail = true,
        ?int $parentId = null
    ): void {
        $exists = DB::table('accounts')
            ->where('company_id', $this->companyId)
            ->where('code', $code)
            ->exists();

        if ($exists) {
            $this->command->warn("Compte {$code} déjà présent — ignoré.");
            return;
        }

        DB::table('accounts')->insert([
            'company_id'       => $this->companyId,
            'account_class_id' => $this->classId4,
            'parent_id'        => $parentId,
            'code'             => $code,
            'name'             => $name,
            'type'             => $type,
            'is_detail'        => $isDetail ? 1 : 0,
            'is_active'        => 1,
            'debit_balance'    => 0,
            'credit_balance'   => 0,
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);

        $this->command->info("✅ Compte {$code} '{$name}' créé.");
    }

    private function getAccountId(string $code): int
    {
        $account = DB::table('accounts')
            ->where('company_id', $this->companyId)
            ->where('code', $code)
            ->first();

        if (! $account) {
            throw new \RuntimeException("Compte {$code} introuvable pour company_id={$this->companyId}");
        }

        return $account->id;
    }
}
