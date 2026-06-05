<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\PayrollPeriod;
use App\Models\PayrollPlan;
use App\Models\PayrollProfile;
use App\Models\PayRubric;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

/**
 * SEEDER DE RÉFÉRENCE — Profil de paie par défaut + période courante.
 *
 *  1. Lie toutes les rubriques sans plan_id au plan par défaut
 *  2. Crée un profil de paie "Standard BF" (si absent)
 *  3. Crée la période de paie du mois courant (si absente)
 */
class PayrollProfileAndPeriodSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::orderBy('id')->first();
        if (! $company) {
            $this->command->warn('Aucune société — seeder ignoré.');
            return;
        }

        // ── 1. Rattacher les rubriques orphelines au plan par défaut ──────────
        $plan = PayrollPlan::where('company_id', $company->id)
            ->where('is_default', true)
            ->first();

        if (! $plan) {
            $this->command->warn('Aucun plan de paie par défaut — créez-en un dans RH → Plans de paie.');
        } else {
            $linked = PayRubric::where('company_id', $company->id)
                ->whereNull('plan_id')
                ->update(['plan_id' => $plan->id]);
            if ($linked > 0) {
                $this->command->info("✅ {$linked} rubrique(s) liée(s) au plan « {$plan->libelle} ».");
            } else {
                $this->command->warn('Rubriques : déjà toutes liées à un plan.');
            }
        }

        // ── 2. Profil par défaut ──────────────────────────────────────────────
        $profileExists = PayrollProfile::where('company_id', $company->id)->exists();
        if (! $profileExists && $plan) {
            PayrollProfile::create([
                'company_id'  => $company->id,
                'plan_id'     => $plan->id,
                'code'        => 'PROF-STD',
                'libelle'     => 'Profil standard',
                'description' => 'Profil de paie par défaut — Burkina Faso. Applicable à tous les salariés sans profil spécifique.',
                'categorie'   => 'autre',
                'valid_from'  => '2024-01-01',
                'is_active'   => true,
                'is_default'  => true,
                'created_by'  => 1,
                'updated_by'  => 1,
            ]);
            $this->command->info('✅ Profil de paie "Profil standard" créé.');
        } else {
            $this->command->warn('Profil de paie : déjà présent ou plan absent.');
        }

        // ── 3. Période du mois courant ────────────────────────────────────────
        $now   = Carbon::now();
        $start = $now->copy()->startOfMonth();
        $end   = $now->copy()->endOfMonth();
        $code  = $now->format('Ym');   // ex: 202606 — max 7 chars

        $periodExists = PayrollPeriod::where('company_id', $company->id)
            ->where('code', $code)
            ->exists();

        if (! $periodExists) {
            PayrollPeriod::create([
                'company_id'  => $company->id,
                'code'        => $code,
                'libelle'     => 'Paie ' . ucfirst($now->translatedFormat('F Y')),
                'period_start'=> $start->toDateString(),
                'period_end'  => $end->toDateString(),
                'status'      => 'open',
                'created_by'  => 1,
                'updated_by'  => 1,
            ]);
            $this->command->info("✅ Période « {$code} » ({$start->toDateString()} → {$end->toDateString()}) créée.");
        } else {
            $this->command->warn("Période {$code} : déjà présente.");
        }
    }
}
