<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\LeaveType;
use Illuminate\Database\Seeder;

/**
 * SEEDER DE RÉFÉRENCE — Types de congés standard Burkina Faso.
 * Idempotent (firstOrCreate sur company_id + code).
 *
 * Références légales :
 *  - Code du Travail BF (Loi n°028-2008) : Art. 144 → congés annuels
 *  - Art. 150 → congés de maternité (14 semaines)
 *  - Art. 151 → congés exceptionnels / événements familiaux
 *  - Convention collective interprofessionnelle BF
 */
class LeaveTypeSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::orderBy('id')->first();
        if (! $company) {
            $this->command->warn('LeaveTypeSeeder : aucune société — seeder ignoré.');
            return;
        }

        $types = [
            // ── Congé annuel payé (Art. 144 Code Travail BF) ─────────────────
            [
                'code'               => 'CA',
                'name'               => 'Congé annuel',
                'days_per_year'      => 30,   // 2,5 jours/mois × 12 = 30 jours
                'is_paid'            => true,
                'deduct_from_salary' => false,
                'color'              => '#10b981', // vert
            ],
            // ── Congé maladie ordinaire ───────────────────────────────────────
            [
                'code'               => 'CM',
                'name'               => 'Congé maladie',
                'days_per_year'      => 60,
                'is_paid'            => true,
                'deduct_from_salary' => false,
                'color'              => '#f59e0b', // orange
            ],
            // ── Congé de maternité (Art. 150 — 14 semaines) ──────────────────
            [
                'code'               => 'MAT',
                'name'               => 'Congé maternité',
                'days_per_year'      => 98,   // 14 semaines
                'is_paid'            => true,
                'deduct_from_salary' => false,
                'color'              => '#ec4899', // rose
            ],
            // ── Congé de paternité ────────────────────────────────────────────
            [
                'code'               => 'PAT',
                'name'               => 'Congé paternité',
                'days_per_year'      => 3,
                'is_paid'            => true,
                'deduct_from_salary' => false,
                'color'              => '#6366f1', // indigo
            ],
            // ── Congés exceptionnels / événements familiaux (Art. 151) ────────
            [
                'code'               => 'CEF',
                'name'               => 'Congé événement familial',
                'days_per_year'      => 10,   // mariage, décès, etc.
                'is_paid'            => true,
                'deduct_from_salary' => false,
                'color'              => '#8b5cf6', // violet
            ],
            // ── Absence injustifiée ───────────────────────────────────────────
            [
                'code'               => 'ABS',
                'name'               => 'Absence injustifiée',
                'days_per_year'      => 0,
                'is_paid'            => false,
                'deduct_from_salary' => true,
                'color'              => '#ef4444', // rouge
            ],
            // ── Sans solde ────────────────────────────────────────────────────
            [
                'code'               => 'CSS',
                'name'               => 'Congé sans solde',
                'days_per_year'      => 0,
                'is_paid'            => false,
                'deduct_from_salary' => true,
                'color'              => '#6b7280', // gris
            ],
            // ── Formation ─────────────────────────────────────────────────────
            [
                'code'               => 'CF',
                'name'               => 'Congé de formation',
                'days_per_year'      => 15,
                'is_paid'            => true,
                'deduct_from_salary' => false,
                'color'              => '#0ea5e9', // bleu
            ],
        ];

        $created = 0;
        foreach ($types as $t) {
            $new = ! LeaveType::where('company_id', $company->id)->where('code', $t['code'])->exists();
            LeaveType::firstOrCreate(
                ['company_id' => $company->id, 'code' => $t['code']],
                array_merge($t, ['company_id' => $company->id, 'is_active' => true])
            );
            if ($new) $created++;
        }

        $this->command->info("✅ LeaveTypeSeeder : {$created} type(s) créé(s) pour {$company->name}.");
    }
}
