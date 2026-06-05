<?php

namespace Database\Seeders;

use App\Models\Company;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * ════════════════════════════════════════════════════════════════════════════
 *  SEEDERS DE RÉFÉRENCE — données indispensables au fonctionnement de l'ERP.
 *  À CONSERVER EN PRODUCTION. Tous les seeders appelés ici sont idempotents
 *  (firstOrCreate / updateOrCreate) : ré-exécutables sans créer de doublons.
 * ════════════════════════════════════════════════════════════════════════════
 *
 *  Deux familles :
 *
 *   1. Référence GLOBALE (indépendante de toute société) :
 *        - RolesAndPermissionsSeeder   rôles + permissions Ventes/Achats/Stock…
 *        - RhPermissionsSeeder         permissions RH/Paie
 *        - PaymentTermSeeder           conditions de règlement
 *        - PayrollSettingSeeder        paramètres de paie Burkina Faso
 *        - PayrollParametrageSeeder    barèmes CNSS/IUTS, plan de paie
 *        - PayRubricSeeder             rubriques de paie standard
 *
 *   2. Référence COMPANY-SCOPÉE (nécessite au moins une société) :
 *        - SyscohadaChartOfAccountsSeeder  plan comptable + journaux SYSCOHADA
 *        - TaxAccountLinkingSeeder         liaison taux TVA ↔ comptes, comptes CNSS
 *
 *  Sur une installation NEUVE en production, aucune société n'existe encore :
 *  la partie company-scopée est alors ignorée proprement (les seeders
 *  concernés détectent l'absence de société et se taisent). Le plan comptable
 *  d'une société est en pratique créé à la création de la société. On relance
 *  ce seeder après avoir créé la première société pour câbler le plan SYSCOHADA.
 *
 *  Usage :
 *      php artisan db:seed --class=ReferenceDataSeeder
 * ════════════════════════════════════════════════════════════════════════════
 */
class ReferenceDataSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        // ── 1. Référence globale (toujours) ──────────────────────────────────
        $this->call([
            RolesAndPermissionsSeeder::class,
            RhPermissionsSeeder::class,
            PaymentTermSeeder::class,
            PayrollSettingSeeder::class,
            PayrollParametrageSeeder::class,
            PayRubricSeeder::class,
            PayrollAllowanceTypeSeeder::class,
        ]);

        // ── 2. Référence company-scopée (si une société existe) ──────────────
        if (Company::exists()) {
            $this->call([
                SyscohadaChartOfAccountsSeeder::class,
                TaxAccountLinkingSeeder::class,
                LeaveTypeSeeder::class,
                PayrollProfileAndPeriodSeeder::class,
            ]);
        } else {
            $this->command->warn(
                'ReferenceDataSeeder : aucune société — plan comptable SYSCOHADA '.
                'et liaison TVA ignorés. Relancez ce seeder après création de la '.
                'première société.'
            );
        }
    }
}
