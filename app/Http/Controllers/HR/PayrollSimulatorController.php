<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\PayrollSetting;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

/**
 * Simulateur de salaire inverse — Burkina Faso.
 *
 * Methode : recherche binaire (<=150 iterations, tolerance <=5 FCFA).
 * Tous les taux sont lus depuis PayrollSetting (no-hardcode).
 */
class PayrollSimulatorController extends Controller
{
    /**
     * Grille salariale indicative BF (niveaux S1 -> D1).
     * net_mid = valeur pre-remplie par defaut quand on clique la categorie.
     */
    public const GRILLE = [
        ['code'=>'S1', 'label'=>'Stagiaire / Apprenti',    'net_min'=>  45_000, 'net_max'=>  80_000, 'net_mid'=>  60_000, 'color'=>'gray',   'icon'=>'🎓', 'desc'=>"Stage, apprentissage, 1er emploi"],
        ['code'=>'E1', 'label'=>"Agent d'execution",       'net_min'=>  80_000, 'net_max'=> 150_000, 'net_mid'=> 110_000, 'color'=>'slate',  'icon'=>'🔧', 'desc'=>"Manoeuvre, aide, agent de service"],
        ['code'=>'E2', 'label'=>'Ouvrier / Employe',       'net_min'=> 150_000, 'net_max'=> 280_000, 'net_mid'=> 200_000, 'color'=>'blue',   'icon'=>'⚙️', 'desc'=>"Ouvrier qualifie, employe de bureau"],
        ['code'=>'T1', 'label'=>'Technicien / Agent adm.', 'net_min'=> 280_000, 'net_max'=> 450_000, 'net_mid'=> 350_000, 'color'=>'indigo', 'icon'=>'📐', 'desc'=>"BEP/BTS, technicien specialise"],
        ['code'=>'T2', 'label'=>"Agent de maitrise",       'net_min'=> 450_000, 'net_max'=> 650_000, 'net_mid'=> 540_000, 'color'=>'violet', 'icon'=>'🏗️', 'desc'=>"Chef d'equipe, superviseur"],
        ['code'=>'C1', 'label'=>'Cadre junior',            'net_min'=> 600_000, 'net_max'=> 900_000, 'net_mid'=> 720_000, 'color'=>'emerald','icon'=>'👔', 'desc'=>"Licence/Master, 0-3 ans experience"],
        ['code'=>'C2', 'label'=>'Cadre confirme',          'net_min'=> 900_000, 'net_max'=>1_400_000, 'net_mid'=>1_100_000,'color'=>'teal',   'icon'=>'💼', 'desc'=>"Master+, 3-8 ans experience"],
        ['code'=>'C3', 'label'=>'Cadre superieur',         'net_min'=>1_400_000,'net_max'=>2_200_000, 'net_mid'=>1_800_000,'color'=>'orange', 'icon'=>'🏅', 'desc'=>"Expert, responsable de departement"],
        ['code'=>'D1', 'label'=>'Directeur / DG',          'net_min'=>2_200_000,'net_max'=>6_000_000, 'net_mid'=>3_000_000,'color'=>'red',    'icon'=>'🏢', 'desc'=>"Directeur, DG, DGA"],
    ];

    // ─────────────────────────────────────────────────────────────────────────

    public function index()
    {
        $payroll = PayrollSetting::forCompany(Company::firstOrFail()->id);
        $grille  = self::GRILLE;

        return view('rh.simulateur.index', compact('payroll', 'grille'));
    }

    /**
     * Calcul Ajax — retourne le resultat JSON.
     */
    public function calculate(Request $request)
    {
        $data = $request->validate([
            'net_souhaite'        => ['required', 'integer', 'min:1'],
            'family_status'       => ['required', 'in:celibataire,marie,veuf'],
            'nb_children'         => ['required', 'integer', 'min:0', 'max:15'],
            'prime_imposable'     => ['nullable', 'integer', 'min:0'],
            'prime_non_imposable' => ['nullable', 'integer', 'min:0'],
            'avances'             => ['nullable', 'integer', 'min:0'],
        ]);

        [$payroll, $result] = $this->runSimulation($data);

        return response()->json($result);
    }

    /**
     * Export PDF — recalcule cote serveur et retourne un PDF.
     */
    public function exportPdf(Request $request)
    {
        $data = $request->validate([
            'net_souhaite'        => ['required', 'integer', 'min:1'],
            'family_status'       => ['required', 'in:celibataire,marie,veuf'],
            'nb_children'         => ['required', 'integer', 'min:0', 'max:15'],
            'prime_imposable'     => ['nullable', 'integer', 'min:0'],
            'prime_non_imposable' => ['nullable', 'integer', 'min:0'],
            'avances'             => ['nullable', 'integer', 'min:0'],
            'categorie_label'     => ['nullable', 'string', 'max:100'],
        ]);

        [$payroll, $result] = $this->runSimulation($data);

        $company  = Company::firstOrFail();
        $settings = $company->documentSetting;

        $familyLabels = [
            'celibataire' => 'Celibataire',
            'marie'       => 'Marie(e)',
            'veuf'        => 'Veuf / Veuve',
        ];

        $params = [
            'Situation familiale' => $familyLabels[$data['family_status']] ?? $data['family_status'],
            'Nombre de charges'   => $data['nb_children'] . ' enfant(s)',
            'Primes imposables'   => number_format($data['prime_imposable'] ?? 0, 0, ',', ' ') . ' F',
            'Primes non imposables' => number_format($data['prime_non_imposable'] ?? 0, 0, ',', ' ') . ' F',
            'Avances / Retenues'  => number_format($data['avances'] ?? 0, 0, ',', ' ') . ' F',
        ];

        if (!empty($data['categorie_label'])) {
            $params = array_merge(['Categorie' => $data['categorie_label']], $params);
        }

        $pdf = Pdf::loadView('rh.simulateur.pdf', compact('result', 'payroll', 'company', 'settings', 'params'))
            ->setPaper('a4', 'portrait');

        $filename = 'Simulation_Salaire_' . now()->format('Ymd_Hi') . '.pdf';
        return $pdf->download($filename);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Logique interne
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Execute la simulation et retourne [$payroll, $result].
     */
    private function runSimulation(array $data): array
    {
        $company = Company::firstOrFail();
        $payroll = PayrollSetting::forCompany($company->id);
        $payroll->assertComplete();

        $netSouhaite       = (int) $data['net_souhaite'];
        $primeImposable    = (int) ($data['prime_imposable']     ?? 0);
        $primeNonImposable = (int) ($data['prime_non_imposable'] ?? 0);
        $avances           = (int) ($data['avances']             ?? 0);
        $familyStatus      = $data['family_status'];
        $nbChildren        = (int) $data['nb_children'];

        $nbParts     = $payroll->computeNbParts($familyStatus, $nbChildren);
        $cnssEmpRate = (float) $payroll->cnss_employee_rate;
        $cnssPatRate = (float) $payroll->cnss_employer_rate;
        $cnssPlafond = (int)   $payroll->cnss_ceiling;
        $abattRate   = (float) $payroll->iuts_abattement_rate;

        // Recherche binaire sur brut_total (= base + prime_imposable)
        $lo    = max(0, $netSouhaite - $primeNonImposable + $avances);
        $hi    = $lo * 5 + 2_000_000;
        $brut  = $lo;
        $calcNet = 0;

        for ($i = 0; $i < 150; $i++) {
            $mid = (int) round(($lo + $hi) / 2);
            $c   = $this->compute($mid, $nbParts, $cnssEmpRate, $cnssPatRate, $cnssPlafond, $abattRate, $payroll);
            $netMid = $c['net'] + $primeNonImposable - $avances;
            $diff   = $netMid - $netSouhaite;

            if (abs($diff) <= 5) { $brut = $mid; $calcNet = $netMid; break; }
            if ($diff < 0) { $lo = $mid + 1; } else { $hi = $mid - 1; }
            $brut    = $mid;
            $calcNet = $netMid;
        }

        $detail  = $this->compute($brut, $nbParts, $cnssEmpRate, $cnssPatRate, $cnssPlafond, $abattRate, $payroll);
        $salaireBase = max(0, $brut - $primeImposable);
        $ecart   = abs($calcNet - $netSouhaite);

        $result = [
            'salaire_base'        => $salaireBase,
            'prime_imposable'     => $primeImposable,
            'salaire_brut'        => $brut,
            'cnss_employee'       => $detail['cnss_emp'],
            'cnss_employer'       => $detail['cnss_pat'],
            'iuts'                => $detail['iuts'],
            'prime_non_imposable' => $primeNonImposable,
            'avances'             => $avances,
            'net_calcule'         => $calcNet,
            'net_souhaite'        => $netSouhaite,
            'ecart'               => $ecart,
            'exact'               => $ecart <= 5,
            'cout_employeur'      => $brut + $detail['cnss_pat'],
            'nb_parts'            => $nbParts,
            'cnss_employee_rate'  => $cnssEmpRate,
            'cnss_employer_rate'  => $cnssPatRate,
            'abattement_rate'     => $abattRate,
            'detail' => [
                ['label'=>'Salaire de base estime',    'montant'=>$salaireBase,               'signe'=>'+','color'=>'gray'],
                ['label'=>'Prime(s) imposable(s)',     'montant'=>$primeImposable,            'signe'=>'+','color'=>'indigo'],
                ['label'=>'Salaire brut',              'montant'=>$brut,                      'signe'=>'=','color'=>'blue','bold'=>true],
                ['label'=>'CNSS salarie ('.$cnssEmpRate.'%)', 'montant'=>$detail['cnss_emp'],'signe'=>'-','color'=>'red'],
                ['label'=>'IUTS ('.$nbParts.' parts)', 'montant'=>$detail['iuts'],           'signe'=>'-','color'=>'purple'],
                ['label'=>'Prime(s) non imposable(s)', 'montant'=>$primeNonImposable,         'signe'=>'+','color'=>'emerald'],
                ['label'=>'Avances / Retenues',        'montant'=>$avances,                   'signe'=>'-','color'=>'orange'],
                ['label'=>'Net a payer',               'montant'=>$calcNet,                   'signe'=>'=','color'=>'green','bold'=>true],
                ['label'=>'CNSS patronal ('.$cnssPatRate.'%)', 'montant'=>$detail['cnss_pat'],'signe'=>'+','color'=>'amber','section'=>'employeur'],
                ['label'=>'Cout total employeur',      'montant'=>$brut+$detail['cnss_pat'],  'signe'=>'=','color'=>'rose','bold'=>true,'section'=>'employeur'],
            ],
        ];

        return [$payroll, $result];
    }

    private function compute(int $brut, float $nbParts, float $cnssEmpRate, float $cnssPatRate, int $cnssPlafond, float $abattRate, PayrollSetting $payroll): array
    {
        $cnssBase = min($brut, $cnssPlafond);
        $cnssEmp  = (int) round($cnssBase * $cnssEmpRate / 100);
        $cnssPat  = (int) round($cnssBase * $cnssPatRate / 100);
        $imposable = (int) round(max(0, $brut - $cnssEmp) * (1 - $abattRate / 100));
        $iuts      = $payroll->computeIuts($imposable, $nbParts);
        return ['cnss_emp'=>$cnssEmp, 'cnss_pat'=>$cnssPat, 'iuts'=>$iuts, 'net'=>$brut - $cnssEmp - $iuts];
    }
}
