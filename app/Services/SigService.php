<?php

namespace App\Services;

use App\Models\FiscalYear;
use Illuminate\Support\Facades\DB;

/**
 * [COMPTA-PRO-03] Soldes Intermédiaires de Gestion (SIG) — SYSCOHADA révisé.
 *
 * Calcule la cascade des soldes à partir des classes 6 (charges) et 7 (produits)
 * de l'exercice. Implémentation conforme au plan SYSCOA :
 *
 *   1. Marge commerciale     = 701 (ventes march.) − 601 − 6031 (variation)
 *   2. Marge sur matières    = 702-705 (ventes prod.) − 602-604 − 6032-6033
 *   3. Chiffre d'affaires    = Σ 701 → 708
 *   4. Production exercice   = Ventes produits + 73 (variation stocks) + 72 (immob.)
 *   5. Valeur ajoutée (VA)   = Marge com. + Marge mat. + Production − (61 + 62)
 *   6. EBE                   = VA + 71 (subv. expl.) − 64 (impôts) − 66 (personnel)
 *   7. Résultat exploitation = EBE + reprises − dotations + autres produits/charges
 *   8. Résultat financier    = 77 − 67
 *   9. Résultat HAO          = (84+85+86+88) − (81+82+83+87)
 *  10. Résultat avant impôts = R.expl + R.fin + R.HAO
 *  11. Résultat net          = R.avant impôts − 89 (impôt sur le résultat)
 */
class SigService
{
    /**
     * Calcule l'ensemble des soldes pour un exercice donné.
     *
     * @return array{
     *     ca: int, marge_commerciale: int, marge_matieres: int,
     *     production_exercice: int, valeur_ajoutee: int, ebe: int,
     *     resultat_exploitation: int, resultat_financier: int,
     *     resultat_hao: int, resultat_avant_impot: int, resultat_net: int,
     *     details: array<string, int>
     * }
     */
    public function compute(FiscalYear $fiscalYear): array
    {
        $sums = $this->sumByPrefix($fiscalYear);

        // Helpers pour requêter par préfixe (SYSCOA = code commence par X)
        $by = function (string ...$prefixes) use ($sums) {
            $total = 0;
            foreach ($sums as $code => $amount) {
                foreach ($prefixes as $p) {
                    if (str_starts_with((string) $code, $p)) {
                        $total += $amount;
                        break;
                    }
                }
            }
            return $total;
        };

        // Note: $sums contient des montants signés ; un compte de classe 7 est
        // déjà compté en crédit positif, classe 6 en débit positif.

        // 1. Marge commerciale
        $ventesMarch    = $by('701');
        $achatsMarch    = $by('601');
        $varStockMarch  = $by('6031');   // débit = augmentation conso
        $margeCommerciale = $ventesMarch - $achatsMarch - $varStockMarch;

        // 2. Marge sur matières
        $ventesProd     = $by('702','703','704','705');
        $achatsMatieres = $by('602','604','608');
        $varStockMat    = $by('6032','6033');
        $margeMatieres  = $ventesProd - $achatsMatieres - $varStockMat;

        // 3. Chiffre d'affaires
        $ca = $by('701','702','703','704','705','706','707','708');

        // 4. Production de l'exercice
        $productionImmob = $by('72');
        $varStockProd    = $by('73');
        $production = $ventesProd + $by('706','708') + $varStockProd + $productionImmob;

        // 5. Valeur ajoutée
        $consommationsExternes = $by('61','62') + $by('605');
        $valeurAjoutee = $margeCommerciale + $margeMatieres + $production - $consommationsExternes;

        // 6. EBE
        $subventionsExpl  = $by('71');
        $impotsTaxes      = $by('64');
        $chargesPersonnel = $by('66');
        $ebe = $valeurAjoutee + $subventionsExpl - $impotsTaxes - $chargesPersonnel;

        // 7. Résultat d'exploitation
        $autresProduitsExpl = $by('75');
        $autresChargesExpl  = $by('65');
        $reprisesExpl       = $by('78','79');
        $dotationsExpl      = $by('68','69');
        $resultatExploit = $ebe + $autresProduitsExpl - $autresChargesExpl + $reprisesExpl - $dotationsExpl;

        // 8. Résultat financier
        $produitsFin = $by('77');
        $chargesFin  = $by('67');
        $resultatFin = $produitsFin - $chargesFin;

        // 9. Résultat HAO (Hors Activités Ordinaires)
        $produitsHao = $by('84','85','86','88');
        $chargesHao  = $by('81','82','83','87');
        $resultatHao = $produitsHao - $chargesHao;

        // 10. Résultat avant impôt
        $resultatAvantImpot = $resultatExploit + $resultatFin + $resultatHao;

        // 11. Résultat net
        $impotResultat = $by('89');
        $resultatNet = $resultatAvantImpot - $impotResultat;

        return [
            'ca'                    => $ca,
            'marge_commerciale'     => $margeCommerciale,
            'marge_matieres'        => $margeMatieres,
            'production_exercice'   => $production,
            'consommations_externes'=> $consommationsExternes,
            'valeur_ajoutee'        => $valeurAjoutee,
            'subventions_expl'      => $subventionsExpl,
            'impots_taxes'          => $impotsTaxes,
            'charges_personnel'     => $chargesPersonnel,
            'ebe'                   => $ebe,
            'autres_produits_expl'  => $autresProduitsExpl,
            'autres_charges_expl'   => $autresChargesExpl,
            'reprises_expl'         => $reprisesExpl,
            'dotations_expl'        => $dotationsExpl,
            'resultat_exploitation' => $resultatExploit,
            'produits_financiers'   => $produitsFin,
            'charges_financieres'   => $chargesFin,
            'resultat_financier'    => $resultatFin,
            'produits_hao'          => $produitsHao,
            'charges_hao'           => $chargesHao,
            'resultat_hao'          => $resultatHao,
            'resultat_avant_impot'  => $resultatAvantImpot,
            'impot_sur_resultat'    => $impotResultat,
            'resultat_net'          => $resultatNet,
            // ratios calculés (lecture seule, en pourcentage du CA)
            'ratio_va_ca'           => $ca > 0 ? round($valeurAjoutee / $ca * 100, 1) : 0,
            'ratio_ebe_ca'          => $ca > 0 ? round($ebe / $ca * 100, 1) : 0,
            'ratio_resultat_ca'     => $ca > 0 ? round($resultatNet / $ca * 100, 1) : 0,
        ];
    }

    /**
     * Renvoie un tableau [code => montant_signé] pour les comptes classes 6 et 7
     * de l'exercice. Pour la classe 6 (charges) on retourne (débit − crédit),
     * pour la classe 7 (produits) on retourne (crédit − débit). Les autres classes
     * concernées par les HAO (8x, 9x) suivent la convention "produit" / "charge".
     */
    private function sumByPrefix(FiscalYear $fy): array
    {
        $rows = DB::table('journal_entry_lines as l')
            ->join('journal_entries as e', 'e.id', '=', 'l.journal_entry_id')
            ->join('accounts as a', 'a.id', '=', 'l.account_id')
            ->where('e.fiscal_year_id', $fy->id)
            ->where('e.status', '!=', 'brouillon')
            ->whereNull('e.deleted_at')
            ->where(function ($q) {
                $q->where('a.code','like','6%')
                  ->orWhere('a.code','like','7%')
                  ->orWhere('a.code','like','8%')
                  ->orWhere('a.code','like','9%');
            })
            ->select('a.code', DB::raw('SUM(l.debit) AS sd'), DB::raw('SUM(l.credit) AS sc'))
            ->groupBy('a.code')
            ->get();

        $sums = [];
        foreach ($rows as $row) {
            $code = (string) $row->code;
            $sd   = (int) $row->sd;
            $sc   = (int) $row->sc;

            // Convention : classe 6 / 8x charges / 87 = débit positif ; classe 7 / 8x produits / 89 = crédit positif.
            // On stocke toujours le "montant représentatif" :
            //  - pour un compte de charge → débit − crédit (≥0 normalement)
            //  - pour un compte de produit → crédit − débit (≥0 normalement)
            //  - pour le 89 (impôt sur le résultat, charge) → débit − crédit
            $isProduct = str_starts_with($code, '7')
                || str_starts_with($code, '84')
                || str_starts_with($code, '85')
                || str_starts_with($code, '86')
                || str_starts_with($code, '88');

            $sums[$code] = $isProduct ? ($sc - $sd) : ($sd - $sc);
        }
        return $sums;
    }
}
