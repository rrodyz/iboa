<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\Supplier;
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

        $company = currentCompany();
        $fy      = FiscalYear::findOrFail($data['fiscal_year_id']);
        abort_if($fy->company_id !== null && $fy->company_id !== $company->id, 403);

        $entries = JournalEntry::with(['journalType', 'lines.account', 'validatedBy'])
            ->where('company_id', $company->id)
            ->where('fiscal_year_id', $fy->id)
            ->where('status', '!=', 'brouillon')
            ->whereNull('deleted_at')
            ->orderBy('entry_date')
            ->orderBy('number')
            ->get();

        // [BUG-3] Construire une map référence → tiers (clients 411, fournisseurs 401)
        // pour renseigner CompAuxNum et CompAuxLib dans le FEC.
        // On collecte toutes les références d'écritures puis on fait 2 requêtes groupées.
        $allRefs = $entries->pluck('reference')->filter()->unique()->values()->toArray();

        // Map number → [code, name] pour clients et fournisseurs
        $clientMap   = Client::whereIn('code', $allRefs)
            ->orWhereIn(DB::raw('code'), function ($q) use ($allRefs, $company) {
                // Cherche aussi par numéro de facture client
                $q->select('number')
                  ->from('invoices')
                  ->where('company_id', $company->id)
                  ->whereIn('number', $allRefs);
            })
            ->pluck('code', 'code')
            ->toArray();

        // Rebuild: ref d'écriture → [client_code, client_name]
        $invoiceToClient = DB::table('invoices as i')
            ->join('clients as c', 'c.id', '=', 'i.client_id')
            ->where('i.company_id', $company->id)
            ->whereIn('i.number', $allRefs)
            ->select('i.number as ref', 'c.code as tiers_code', 'c.name as tiers_lib')
            ->get()
            ->keyBy('ref');

        $supplierInvToSupplier = DB::table('supplier_invoices as si')
            ->join('suppliers as s', 's.id', '=', 'si.supplier_id')
            ->where('si.company_id', $company->id)
            ->whereIn('si.number', $allRefs)
            ->select('si.number as ref', 's.code as tiers_code', 's.name as tiers_lib')
            ->get()
            ->keyBy('ref');

        $rows = [];
        // Header
        $rows[] = [
            'JournalCode','JournalLib','EcritureNum','EcritureDate','CompteNum','CompteLib',
            'CompAuxNum','CompAuxLib','PieceRef','PieceDate','EcritureLib','Debit','Credit',
            'EcritureLet','DateLet','ValidDate','Montantdevise','Idevise',
        ];

        foreach ($entries as $entry) {
            $ref = $entry->reference ?? '';

            foreach ($entry->lines as $line) {
                $accountCode = $line->account?->code ?? '';

                // [BUG-3] Renseigner CompAuxNum / CompAuxLib pour les comptes de tiers
                $compAuxNum = '';
                $compAuxLib = '';

                if (str_starts_with($accountCode, '411') && $ref) {
                    // Compte client : chercher dans les factures clients
                    $tiers      = $invoiceToClient->get($ref);
                    $compAuxNum = $tiers?->tiers_code ?? '';
                    $compAuxLib = $tiers?->tiers_lib  ?? '';
                } elseif (str_starts_with($accountCode, '401') && $ref) {
                    // Compte fournisseur : chercher dans les factures fournisseurs
                    $tiers      = $supplierInvToSupplier->get($ref);
                    $compAuxNum = $tiers?->tiers_code ?? '';
                    $compAuxLib = $tiers?->tiers_lib  ?? '';
                }

                $rows[] = [
                    $entry->journalType?->code ?? '',
                    $entry->journalType?->name ?? '',
                    $entry->number,
                    $entry->entry_date?->format('Ymd') ?? '',
                    $accountCode,
                    $line->account?->name ?? '',
                    $compAuxNum,
                    $compAuxLib,
                    $ref,
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
