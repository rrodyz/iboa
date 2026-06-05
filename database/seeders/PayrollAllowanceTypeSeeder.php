<?php

namespace Database\Seeders;

use App\Models\PayrollAllowanceType;
use Illuminate\Database\Seeder;

/**
 * SEEDER DE RÉFÉRENCE — Types de primes & indemnités standard (Burkina Faso).
 * Idempotent (firstOrCreate sur le code).
 *
 * Champs :
 *   is_taxable        → soumis à l'IUTS
 *   is_social_charged → soumis à la CNSS (assiette cotisations)
 */
class PayrollAllowanceTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            // ── Indemnités non imposables, non soumises CNSS ─────────────────
            [
                'code'              => 'TRANSPORT',
                'name'              => 'Indemnité de transport',
                'is_taxable'        => false,
                'is_social_charged' => false,
                'description'       => 'Remboursement frais de déplacement domicile-travail.',
            ],
            [
                'code'              => 'PANIER',
                'name'              => 'Indemnité de panier / repas',
                'is_taxable'        => false,
                'is_social_charged' => false,
                'description'       => 'Indemnité journalière de repas pour le personnel en déplacement.',
            ],
            [
                'code'              => 'SALISSURE',
                'name'              => 'Prime de salissure',
                'is_taxable'        => false,
                'is_social_charged' => false,
                'description'       => 'Compensation pour travaux salissants (exonérée dans la limite légale).',
            ],
            [
                'code'              => 'OUTILLAGE',
                'name'              => 'Indemnité d\'outillage',
                'is_taxable'        => false,
                'is_social_charged' => false,
                'description'       => 'Remboursement de l\'usure des outils personnels.',
            ],
            // ── Indemnités imposables, non soumises CNSS ──────────────────────
            [
                'code'              => 'LOGEMENT',
                'name'              => 'Indemnité de logement',
                'is_taxable'        => true,
                'is_social_charged' => false,
                'description'       => 'Avantage en nature ou allocation logement. Soumis IUTS, exonéré CNSS (dans la limite du seuil).',
            ],
            [
                'code'              => 'REPRESENTATION',
                'name'              => 'Frais de représentation',
                'is_taxable'        => true,
                'is_social_charged' => false,
                'description'       => 'Frais de réception et de représentation engagés pour le compte de l\'entreprise.',
            ],
            [
                'code'              => 'FONCTION',
                'name'              => 'Indemnité de fonction',
                'is_taxable'        => true,
                'is_social_charged' => true,
                'description'       => 'Complément lié à l\'exercice d\'une fonction particulière (direction, encadrement…).',
            ],
            [
                'code'              => 'SPECIFIQUE',
                'name'              => 'Indemnité spécifique',
                'is_taxable'        => true,
                'is_social_charged' => false,
                'description'       => 'Indemnité à caractère particulier selon le poste ou l\'accord d\'entreprise.',
            ],
            // ── Primes imposables, soumises CNSS ─────────────────────────────
            [
                'code'              => 'RESPONSABILITE',
                'name'              => 'Prime de responsabilité',
                'is_taxable'        => true,
                'is_social_charged' => true,
                'description'       => 'Prime liée à l\'exercice d\'une fonction de responsabilité.',
            ],
            [
                'code'              => 'RENDEMENT',
                'name'              => 'Prime de rendement',
                'is_taxable'        => true,
                'is_social_charged' => true,
                'description'       => 'Prime liée à l\'atteinte d\'objectifs de production ou de performance.',
            ],
            [
                'code'              => 'TECHNICITE',
                'name'              => 'Prime de technicité',
                'is_taxable'        => true,
                'is_social_charged' => true,
                'description'       => 'Complément salarial lié à une qualification technique particulière.',
            ],
            [
                'code'              => 'GARDE',
                'name'              => 'Indemnité d\'astreinte / garde',
                'is_taxable'        => true,
                'is_social_charged' => true,
                'description'       => 'Compensation pour les périodes d\'astreinte ou de garde.',
            ],
            [
                'code'              => 'INSALUBRITE',
                'name'              => 'Prime d\'insalubrité',
                'is_taxable'        => true,
                'is_social_charged' => true,
                'description'       => 'Prime versée pour travaux en milieu insalubre ou dangereux.',
            ],
            [
                'code'              => 'FORMATION',
                'name'              => 'Prime de formation',
                'is_taxable'        => true,
                'is_social_charged' => true,
                'description'       => 'Encouragement à la montée en compétences.',
            ],
            [
                'code'              => 'FIN_ANNEE',
                'name'              => 'Prime de fin d\'année (13ᵉ mois)',
                'is_taxable'        => true,
                'is_social_charged' => true,
                'description'       => 'Gratification annuelle équivalente à un mois de salaire.',
            ],
            // ── Ancienneté — calculée automatiquement par le service de paie ─
            [
                'code'              => 'ANCIENNETE',
                'name'              => 'Prime d\'ancienneté',
                'is_taxable'        => true,
                'is_social_charged' => false,
                'description'       => 'Calculée automatiquement : anc_rate_per_year % × années de service, plafonnée à anc_rate_max_pct %. Montant saisi ignoré.',
            ],
        ];

        $created = 0;
        foreach ($types as $t) {
            $new = ! PayrollAllowanceType::where('code', $t['code'])->exists();
            PayrollAllowanceType::firstOrCreate(
                ['code' => $t['code']],
                array_merge($t, ['is_active' => true])
            );
            if ($new) $created++;
        }

        $this->command->info("✅ PayrollAllowanceTypeSeeder : {$created} type(s) créé(s), ".(count($types) - $created)." déjà présent(s).");
    }
}
