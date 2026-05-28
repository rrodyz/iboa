<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\PayrollSetting;
use Illuminate\Http\Request;

/**
 * Simulateur de salaire inversé — Burkina Faso.
 *
 * Objectif : à partir d'un net souhaité, calculer le brut,
 * les cotisations (CNSS, IUTS) et le coût employeur, en
 * tenant compte de la situation familiale et des primes.
 *
 * Méthode : recherche binaire (≤ 100 itérations, tolérance ≤ 5 FCFA).
 */
class PayrollSimulatorController extends Controller
{
    public function index()
    {
        $payroll = PayrollSetting::forCompany(Company::firstOrFail()->id);

        return view('rh.simulateur.index', compact('payroll'));
    }

    /**
     * Calcul Ajax — reçoit les paramètres et retourne le résultat JSON.
     */
    public function calculate(Request $request)
    {
        $data = $request->validate([
            'net_souhaite'         => ['required', 'integer', 'min:1'],
            'family_status'        => ['required', 'in:celibataire,marie,veuf'],
            'nb_children'          => ['required', 'integer', 'min:0', 'max:15'],
            'prime_imposable'      => ['nullable', 'integer', 'min:0'],
            'prime_non_imposable'  => ['nullable', 'integer', 'min:0'],
            'avances'              => ['nullable', 'integer', 'min:0'],
        ]);

        $company = Company::firstOrFail();
        $payroll = PayrollSetting::forCompany($company->id);
        $payroll->assertComplete();

        $netSouhaite        = (int) $data['net_souhaite'];
        $primeImposable     = (int) ($data['prime_imposable']     ?? 0);
        $primeNonImposable  = (int) ($data['prime_non_imposable'] ?? 0);
        $avances            = (int) ($data['avances']             ?? 0);
        $familyStatus       = $data['family_status'];
        $nbChildren         = (int) $data['nb_children'];

        // Nombre de parts fiscales
        $nbParts = $payroll->computeNbParts($familyStatus, $nbChildren);

        // Paramètres CNSS
        $cnssEmpRate  = (float) $payroll->cnss_employee_rate;
        $cnssPatRate  = (float) $payroll->cnss_employer_rate;
        $cnssPlafond  = (int)   $payroll->cnss_ceiling;
        $abattRate    = (float) $payroll->iuts_abattement_rate;

        // Net cible ajusté (sans les éléments non-brut)
        // Le net = brut_base + prime_imposable - CNSS_emp - IUTS + prime_non_imposable - avances
        // On cherche brut_base tel que net = cible.
        $netCible = $netSouhaite - $primeNonImposable + $avances;
        // net_cible = brut_base + prime_imposable - CNSS_emp(brut_base + prime_imposable) - IUTS(...)
        // On pose: brut_total = brut_base + prime_imposable

        // --- Recherche binaire sur brut_total ---
        $lo = max(0, $netCible);
        $hi = $netCible * 5 + 1_000_000; // borne haute généreuse

        $tolerance  = 5; // FCFA
        $maxIter    = 150;
        $brut       = 0;
        $calcNet    = 0;
        $exact      = false;

        for ($i = 0; $i < $maxIter; $i++) {
            $mid = (int) round(($lo + $hi) / 2);
            ['net' => $n] = $this->compute($mid, $nbParts, $cnssEmpRate, $cnssPatRate, $cnssPlafond, $abattRate, $payroll);
            // net ici = brut - CNSS_emp - IUTS (primes non-imposables et avances hors boucle)
            $diff = ($n + $primeNonImposable - $avances) - $netSouhaite;

            if (abs($diff) <= $tolerance) {
                $brut    = $mid;
                $calcNet = $n + $primeNonImposable - $avances;
                $exact   = (abs($diff) === 0);
                break;
            }
            if ($diff < 0) {
                $lo = $mid + 1;
            } else {
                $hi = $mid - 1;
            }
            $brut    = $mid;
            $calcNet = $n + $primeNonImposable - $avances;
        }

        // Calcul final détaillé avec le brut trouvé
        $detail = $this->compute($brut, $nbParts, $cnssEmpRate, $cnssPatRate, $cnssPlafond, $abattRate, $payroll);

        // Le brut_total comprend prime_imposable : on en déduit le salaire de base estimé
        $salaireBase  = max(0, $brut - $primeImposable);

        $result = [
            'salaire_base'     => $salaireBase,
            'prime_imposable'  => $primeImposable,
            'salaire_brut'     => $brut,
            'cnss_employee'    => $detail['cnss_emp'],
            'cnss_employer'    => $detail['cnss_pat'],
            'iuts'             => $detail['iuts'],
            'prime_non_imposable' => $primeNonImposable,
            'avances'          => $avances,
            'net_calcule'      => $calcNet,
            'net_souhaite'     => $netSouhaite,
            'ecart'            => abs($calcNet - $netSouhaite),
            'exact'            => $exact || abs($calcNet - $netSouhaite) <= $tolerance,
            'cout_employeur'   => $brut + $detail['cnss_pat'],
            'nb_parts'         => $nbParts,
            // Détail ligne par ligne
            'detail'           => [
                ['label' => 'Salaire de base estimé',    'montant' => $salaireBase,        'signe' => '+', 'color' => 'gray'],
                ['label' => 'Prime(s) imposable(s)',     'montant' => $primeImposable,     'signe' => '+', 'color' => 'indigo'],
                ['label' => 'Salaire brut',               'montant' => $brut,               'signe' => '=', 'color' => 'blue', 'bold' => true],
                ['label' => 'CNSS salarié (' . $cnssEmpRate . '%)', 'montant' => $detail['cnss_emp'], 'signe' => '-', 'color' => 'red'],
                ['label' => 'IUTS (' . $nbParts . ' parts)', 'montant' => $detail['iuts'],      'signe' => '-', 'color' => 'purple'],
                ['label' => 'Prime(s) non imposable(s)', 'montant' => $primeNonImposable,  'signe' => '+', 'color' => 'emerald'],
                ['label' => 'Avances / Retenues',         'montant' => $avances,            'signe' => '-', 'color' => 'orange'],
                ['label' => 'Net à payer',                'montant' => $calcNet,            'signe' => '=', 'color' => 'green', 'bold' => true],
                ['label' => 'CNSS patronal (' . $cnssPatRate . '%)', 'montant' => $detail['cnss_pat'], 'signe' => '+', 'color' => 'amber', 'section' => 'employeur'],
                ['label' => 'Coût total employeur',      'montant' => $brut + $detail['cnss_pat'], 'signe' => '=', 'color' => 'rose', 'bold' => true, 'section' => 'employeur'],
            ],
        ];

        return response()->json($result);
    }

    /**
     * Calcule CNSS + IUTS + net pour un brut total donné.
     */
    private function compute(
        int $brut,
        float $nbParts,
        float $cnssEmpRate,
        float $cnssPatRate,
        int $cnssPlafond,
        float $abattRate,
        PayrollSetting $payroll
    ): array {
        $cnssBase = min($brut, $cnssPlafond);
        $cnssEmp  = (int) round($cnssBase * $cnssEmpRate / 100);
        $cnssPat  = (int) round($cnssBase * $cnssPatRate / 100);

        $imposableBrut = max(0, $brut - $cnssEmp);
        $imposable     = (int) round($imposableBrut * (1 - $abattRate / 100));
        $iuts          = $payroll->computeIuts($imposable, $nbParts);

        $net = $brut - $cnssEmp - $iuts;

        return [
            'cnss_emp' => $cnssEmp,
            'cnss_pat' => $cnssPat,
            'iuts'     => $iuts,
            'net'      => $net,
        ];
    }
}
