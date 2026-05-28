<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\IutsBracket;
use App\Models\PayrollConstant;
use App\Models\PayrollPlan;
use App\Models\SocialContribution;
use Illuminate\Database\Seeder;

/**
 * Données par défaut — Burkina Faso 2024/2026
 * Idempotent : utilise firstOrCreate, sans risque d'écraser l'existant.
 */
class PayrollParametrageSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::first();
        if (! $company) {
            $this->command->warn('Aucune entreprise trouvée — seeder ignoré.');
            return;
        }

        $cid = $company->id;

        // ── 1. Plan de paie par défaut ─────────────────────────────────────────
        PayrollPlan::firstOrCreate(
            ['company_id' => $cid, 'code' => 'PL-BF-STD'],
            [
                'libelle'      => 'Plan de paie Burkina Faso — Standard',
                'pays'         => 'Burkina Faso',
                'country_code' => 'BF',
                'devise'       => 'FCFA',
                'is_active'    => true,
                'is_default'   => true,
                'notes'        => 'Plan standard — droit burkinabè du travail 2024',
            ]
        );

        // ── 2. Constantes de paie ──────────────────────────────────────────────
        $constants = [
            // SMIG
            ['code' => 'SMIG',           'libelle' => 'Salaire minimum interprofessionnel garanti',
             'value_type' => 'montant',  'value_raw' => '45000',  'unit' => 'FCFA', 'groupe' => 'smig'],

            // CNSS
            ['code' => 'CNSS_SAL_TAUX', 'libelle' => 'Taux CNSS part salarié',
             'value_type' => 'taux',     'value_raw' => '5.5',    'unit' => '%',    'groupe' => 'cnss'],
            ['code' => 'CNSS_PAT_TAUX', 'libelle' => 'Taux CNSS part patronale',
             'value_type' => 'taux',     'value_raw' => '16.0',   'unit' => '%',    'groupe' => 'cnss'],
            ['code' => 'CNSS_PLAFOND',  'libelle' => 'Plafond mensuel CNSS',
             'value_type' => 'montant',  'value_raw' => '650000', 'unit' => 'FCFA', 'groupe' => 'cnss'],
            ['code' => 'CNSS_AT_TAUX',  'libelle' => 'Taux accident du travail (patronal)',
             'value_type' => 'taux',     'value_raw' => '3.5',    'unit' => '%',    'groupe' => 'cnss'],

            // Heures & jours
            ['code' => 'NB_JOURS_MOIS', 'libelle' => 'Nombre de jours ouvrables par mois',
             'value_type' => 'nombre',   'value_raw' => '26',     'unit' => 'jours','groupe' => 'heures'],
            ['code' => 'NB_HEURES_JOUR','libelle' => 'Nombre d\'heures de travail par jour',
             'value_type' => 'nombre',   'value_raw' => '8',      'unit' => 'h',    'groupe' => 'heures'],
            ['code' => 'NB_HEURES_MOIS','libelle' => 'Nombre d\'heures mensuelles légales',
             'value_type' => 'nombre',   'value_raw' => '173.33', 'unit' => 'h',    'groupe' => 'heures'],

            // Congés
            ['code' => 'CONGES_JOURS',  'libelle' => 'Jours de congés annuels acquis',
             'value_type' => 'nombre',   'value_raw' => '30',     'unit' => 'jours','groupe' => 'conges'],

            // Heures supplémentaires
            ['code' => 'HS_TAUX_25',    'libelle' => 'Majoration heures sup. 25%',
             'value_type' => 'taux',     'value_raw' => '25',     'unit' => '%',    'groupe' => 'heures'],
            ['code' => 'HS_TAUX_50',    'libelle' => 'Majoration heures sup. 50%',
             'value_type' => 'taux',     'value_raw' => '50',     'unit' => '%',    'groupe' => 'heures'],
            ['code' => 'HS_TAUX_NUIT',  'libelle' => 'Majoration heures de nuit 75%',
             'value_type' => 'taux',     'value_raw' => '75',     'unit' => '%',    'groupe' => 'heures'],

            // Effort de paix (BF)
            ['code' => 'EFFORT_PAIX',   'libelle' => 'Contribution effort de paix',
             'value_type' => 'taux',     'value_raw' => '1',      'unit' => '%',    'groupe' => 'fiscal'],

            // Quotient familial
            ['code' => 'PARTS_CELIBA',  'libelle' => 'Parts fiscales — célibataire',
             'value_type' => 'nombre',   'value_raw' => '1.0',    'unit' => 'parts','groupe' => 'iuts'],
            ['code' => 'PARTS_MARIE',   'libelle' => 'Parts fiscales — marié(e)',
             'value_type' => 'nombre',   'value_raw' => '2.0',    'unit' => 'parts','groupe' => 'iuts'],
            ['code' => 'PARTS_VEUF',    'libelle' => 'Parts fiscales — veuf/veuve',
             'value_type' => 'nombre',   'value_raw' => '1.5',    'unit' => 'parts','groupe' => 'iuts'],
            ['code' => 'PARTS_ENFANT',  'libelle' => 'Parts supplémentaires par enfant',
             'value_type' => 'nombre',   'value_raw' => '0.5',    'unit' => 'parts','groupe' => 'iuts'],
            ['code' => 'PARTS_MAX',     'libelle' => 'Nombre de parts maximum',
             'value_type' => 'nombre',   'value_raw' => '5',      'unit' => 'parts','groupe' => 'iuts'],
        ];

        foreach ($constants as $c) {
            PayrollConstant::firstOrCreate(
                ['company_id' => $cid, 'code' => $c['code']],
                array_merge($c, ['is_active' => true, 'valid_from' => '2024-01-01'])
            );
        }

        // ── 3. Barème IUTS — Burkina Faso 2024 ────────────────────────────────
        $brackets = [
            ['tranche_min' =>         0, 'tranche_max' =>    25_000, 'taux' => 0,  'ordre' => 1],
            ['tranche_min' =>    25_001, 'tranche_max' =>    40_000, 'taux' => 12, 'ordre' => 2],
            ['tranche_min' =>    40_001, 'tranche_max' =>    60_000, 'taux' => 17, 'ordre' => 3],
            ['tranche_min' =>    60_001, 'tranche_max' =>    80_000, 'taux' => 22, 'ordre' => 4],
            ['tranche_min' =>    80_001, 'tranche_max' =>   120_000, 'taux' => 27, 'ordre' => 5],
            ['tranche_min' =>   120_001, 'tranche_max' => 9_999_999, 'taux' => 33, 'ordre' => 6],
        ];

        foreach ($brackets as $b) {
            IutsBracket::firstOrCreate(
                ['company_id' => $cid, 'tranche_min' => $b['tranche_min'], 'impot' => 'iuts'],
                array_merge($b, [
                    'pays'         => 'Burkina Faso',
                    'country_code' => 'BF',
                    'impot'        => 'iuts',
                    'montant_fixe' => 0,
                    'abattement'   => 0,
                    'is_active'    => true,
                    'valid_from'   => '2024-01-01',
                ])
            );
        }

        // ── 4. Cotisations sociales ────────────────────────────────────────────
        $contributions = [
            [
                'code'            => 'CNSS_SAL',
                'libelle'         => 'CNSS — Part salariale',
                'organisme'       => 'cnss',
                'taux_salarie'    => 5.5,
                'taux_employeur'  => 0,
                'base_cotisable'  => 'plafonne',
                'plafond'         => 650000,
                'account_salarie' => '431100',
            ],
            [
                'code'              => 'CNSS_PAT',
                'libelle'           => 'CNSS — Part patronale',
                'organisme'         => 'cnss',
                'taux_salarie'      => 0,
                'taux_employeur'    => 16.0,
                'base_cotisable'    => 'plafonne',
                'plafond'           => 650000,
                'account_employeur' => '431200',
            ],
            [
                'code'              => 'CNSS_AT',
                'libelle'           => 'Accident du travail — Part patronale',
                'organisme'         => 'cnss',
                'taux_salarie'      => 0,
                'taux_employeur'    => 3.5,
                'base_cotisable'    => 'plafonne',
                'plafond'           => 650000,
                'account_employeur' => '431300',
            ],
        ];

        foreach ($contributions as $c) {
            SocialContribution::firstOrCreate(
                ['company_id' => $cid, 'code' => $c['code']],
                array_merge($c, ['is_active' => true, 'valid_from' => '2024-01-01'])
            );
        }

        $this->command->info('✅ Paramétrage paie BF seedé avec succès.');
    }
}
