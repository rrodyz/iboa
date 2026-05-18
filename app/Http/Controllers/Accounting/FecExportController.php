<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

/**
 * [COMPTA-PRO-02] Export FEC (Fichier des Écritures Comptables).
 *
 * Format texte tabulé conforme à l'art. A47-A1 du LPF (France) et aux normes
 * d'audit fiscal SYSCOA-OHADA. Permet à un commissaire aux comptes ou à
 * l'administration fiscale d'auditer toutes les écritures validées d'un exercice.
 *
 * Colonnes (18) :
 *   JournalCode | JournalLib | EcritureNum | EcritureDate | CompteNum | CompteLib
 *   CompAuxNum | CompAuxLib | PieceRef | PieceDate | EcritureLib | Debit | Credit
 *   EcritureLet | DateLet | ValidDate | Montantdevise | Idevise
 *
 * Encodage : ISO-8859-15 (Latin-1) — exigé par l'administration fiscale.
 * Séparateur : tabulation (\t) — Excel ouvre directement.
 */
class FecExportController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:accounting.view');
    }

    /**
     * GET /comptabilite/fec — formulaire de choix d'exercice + bouton export.
     */
    public function index(Request $request): \Illuminate\View\View
    {
        $fiscalYears = FiscalYear::orderByDesc('starts_at')->get();
        return view('comptabilite.fec', compact('fiscalYears'));
    }

    /**
     * GET /comptabilite/fec/export?fiscal_year_id=X — télécharge le fichier.
     */
    public function export(Request $request): Response
    {
        $data = $request->validate([
            'fiscal_year_id' => ['required', 'integer', 'exists:fiscal_years,id'],
        ]);

        $company = Company::firstOrFail();
        $fy      = FiscalYear::findOrFail($data['fiscal_year_id']);

        $entries = JournalEntry::with(['journalType', 'lines.account', 'validatedBy'])
            ->where('company_id', $company->id)
            ->where('fiscal_year_id', $fy->id)
            ->where('status', '!=', 'brouillon')
            ->whereNull('deleted_at')
            ->orderBy('entry_date')
            ->orderBy('number')
            ->get();

        $rows = [];
        // Header
        $rows[] = [
            'JournalCode','JournalLib','EcritureNum','EcritureDate','CompteNum','CompteLib',
            'CompAuxNum','CompAuxLib','PieceRef','PieceDate','EcritureLib','Debit','Credit',
            'EcritureLet','DateLet','ValidDate','Montantdevise','Idevise',
        ];

        foreach ($entries as $entry) {
            foreach ($entry->lines as $line) {
                $rows[] = [
                    $entry->journalType?->code ?? '',
                    $entry->journalType?->name ?? '',
                    $entry->number,
                    $entry->entry_date?->format('Ymd') ?? '',
                    $line->account?->code ?? '',
                    $line->account?->name ?? '',
                    '',                                       // CompAuxNum (tiers — laissé vide ici)
                    '',                                       // CompAuxLib
                    $entry->reference ?? '',
                    $entry->entry_date?->format('Ymd') ?? '',
                    $line->label ?? $entry->description,
                    $this->fmtAmount($line->debit),
                    $this->fmtAmount($line->credit),
                    $line->reconciliation_ref ?? '',          // EcritureLet — lettrage
                    '',                                       // DateLet
                    $entry->validated_at?->format('Ymd') ?? '',
                    '',                                       // Montantdevise
                    '',                                       // Idevise (FCFA implicite)
                ];
            }
        }

        // Build TSV body
        $body = '';
        foreach ($rows as $row) {
            $clean = array_map(fn($c) => str_replace(["\t","\n","\r"], ' ', (string) $c), $row);
            $body .= implode("\t", $clean) . "\r\n";
        }

        // Convertit en ISO-8859-15 (exigence administration fiscale)
        $body = mb_convert_encoding($body, 'ISO-8859-15', 'UTF-8');

        // Nom de fichier : <SIREN/IFU><FECYYYYMMDD>
        $ifu      = preg_replace('/\D/', '', $company->ifu ?? '0000000');
        $filename = sprintf('%sFEC%s.txt', $ifu, $fy->ends_at->format('Ymd'));

        return response($body, 200, [
            'Content-Type'        => 'text/plain; charset=ISO-8859-15',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    private function fmtAmount($v): string
    {
        // Le FEC accepte le point décimal, à 2 décimales. Nos montants sont en entier FCFA.
        return number_format((float) $v, 2, '.', '');
    }
}
