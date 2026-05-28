<?php

namespace App\Http\Controllers\Integrations;

use App\Http\Controllers\Controller;
use App\Models\ApiIntegration;
use App\Models\FiscalYear;
use App\Models\JournalType;
use App\Models\VatDeclaration;
use App\Services\Integrations\FiscalBfService;
use App\Services\Integrations\IntegrationManager;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

/**
 * Contrôleur pour l'export fiscal DGI Burkina Faso.
 *
 * Routes :
 *   GET  /integrations/{integration}/fiscal           → dashboard export
 *   POST /integrations/{integration}/fiscal/tva       → export TVA
 *   POST /integrations/{integration}/fiscal/factures  → export factures
 *   POST /integrations/{integration}/fiscal/journal   → export journal
 *   POST /integrations/{integration}/fiscal/declare   → push déclaration DGI
 */
class FiscalExportController extends Controller
{
    // ── Dashboard ─────────────────────────────────────────────────────────────

    public function index(ApiIntegration $integration)
    {
        abort_unless($integration->provider === 'fiscal_bf', 404);
        abort_unless($integration->is_active || Auth::user()?->is_admin, 403);

        $companyId   = Auth::user()->company_id;
        // FiscalYear est globale (pas de company_id) — on trie par date descroissante
        $fiscalYears  = FiscalYear::orderByDesc('starts_at')->get();
        $journalTypes = JournalType::where('company_id', $companyId)->orderBy('code')->get();

        // Dernières déclarations TVA
        $lastDeclarations = VatDeclaration::where('company_id', $companyId)
            ->orderByDesc('period_end')
            ->limit(6)
            ->get();

        // Logs des exports précédents via cette intégration
        $recentLogs = $integration->logs()
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        return view('integrations.fiscal.index', compact(
            'integration',
            'fiscalYears',
            'journalTypes',
            'lastDeclarations',
            'recentLogs',
        ));
    }

    // ── Export TVA ────────────────────────────────────────────────────────────

    public function exportTva(Request $request, ApiIntegration $integration)
    {
        abort_unless($integration->provider === 'fiscal_bf', 404);

        $validated = $request->validate([
            'period_start' => ['required', 'date'],
            'period_end'   => ['required', 'date', 'after_or_equal:period_start'],
            'format'       => ['nullable', 'in:csv,xml,json'],
        ]);

        $service = $this->makeService($integration);
        $result  = $service->exportTva($validated);

        if (! $result['success']) {
            return back()->withErrors(['tva' => $result['error']])->withInput();
        }

        return $this->downloadResponse(
            $result['file_content'],
            $result['filename'],
            $validated['format'] ?? 'csv',
        );
    }

    // ── Export Factures ───────────────────────────────────────────────────────

    public function exportInvoices(Request $request, ApiIntegration $integration)
    {
        abort_unless($integration->provider === 'fiscal_bf', 404);

        $validated = $request->validate([
            'date_from' => ['required', 'date'],
            'date_to'   => ['required', 'date', 'after_or_equal:date_from'],
            'type'      => ['nullable', 'in:vente,achat,all'],
            'format'    => ['nullable', 'in:csv,xml,json'],
        ]);

        $service = $this->makeService($integration);
        $result  = $service->exportInvoices($validated);

        if (! $result['success']) {
            return back()->withErrors(['factures' => $result['error']])->withInput();
        }

        return $this->downloadResponse(
            $result['file_content'],
            $result['filename'],
            $validated['format'] ?? 'csv',
        );
    }

    // ── Export Journal ────────────────────────────────────────────────────────

    public function exportJournal(Request $request, ApiIntegration $integration)
    {
        abort_unless($integration->provider === 'fiscal_bf', 404);

        $validated = $request->validate([
            'date_from'       => ['required', 'date'],
            'date_to'         => ['required', 'date', 'after_or_equal:date_from'],
            'journal_type_id' => ['nullable', 'integer', 'exists:journal_types,id'],
            'format'          => ['nullable', 'in:csv,xml,json'],
        ]);

        $service = $this->makeService($integration);
        $result  = $service->exportJournal($validated);

        if (! $result['success']) {
            return back()->withErrors(['journal' => $result['error']])->withInput();
        }

        return $this->downloadResponse(
            $result['file_content'],
            $result['filename'],
            $validated['format'] ?? 'csv',
        );
    }

    // ── Déclaration TVA (push DGI API) ────────────────────────────────────────

    public function declareTva(Request $request, ApiIntegration $integration)
    {
        abort_unless($integration->provider === 'fiscal_bf', 404);

        $validated = $request->validate([
            'period_start'    => ['required', 'date'],
            'period_end'      => ['required', 'date', 'after_or_equal:period_start'],
            'tva_collectee'   => ['required', 'numeric', 'min:0'],
            'tva_deductible'  => ['required', 'numeric', 'min:0'],
        ]);

        $service = $this->makeService($integration);

        // Exporter d'abord pour avoir toutes les données
        $export = $service->exportTva($validated);
        if (! $export['success']) {
            return back()->withErrors(['declare' => $export['error']]);
        }

        $result = $service->declareTva($export['data']);

        if (! $result['success']) {
            return back()->withErrors(['declare' => $result['error']]);
        }

        return back()->with('success', "Déclaration envoyée. Référence DGI : {$result['reference']}");
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function makeService(ApiIntegration $integration): FiscalBfService
    {
        return new FiscalBfService($integration);
    }

    private function downloadResponse(string $content, string $filename, string $format): Response
    {
        $mimes = [
            'csv'  => 'text/csv; charset=UTF-8',
            'xml'  => 'application/xml; charset=UTF-8',
            'json' => 'application/json; charset=UTF-8',
        ];

        $mime = $mimes[$format] ?? 'application/octet-stream';

        return response($content, 200)
            ->header('Content-Type', $mime)
            ->header('Content-Disposition', "attachment; filename=\"{$filename}\"")
            ->header('Content-Length', strlen($content));
    }
}
