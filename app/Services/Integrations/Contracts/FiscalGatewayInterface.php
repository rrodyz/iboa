<?php

namespace App\Services\Integrations\Contracts;

/**
 * Contract for fiscal platform integrations.
 *
 * Implementations must support:
 *  - Export TVA (Déclaration TVA mensuelle/trimestrielle DGI-BF)
 *  - Export factures (liste de factures conforme DGI)
 *  - Export journaux SYSCOHADA
 *  - Ping / connectivity test
 *
 * When the DGI REST API is unavailable, implementations fall back
 * to generating export files (CSV / XML) for manual upload to e-SINTAX.
 */
interface FiscalGatewayInterface
{
    /**
     * Export a TVA declaration payload.
     *
     * @param  array  $params  ['period_start', 'period_end', 'format' => 'xml|csv|json']
     * @return array  ['success', 'data' => [...], 'file_content' => string|null, 'error']
     */
    public function exportTva(array $params): array;

    /**
     * Export invoices list in DGI-compliant format.
     *
     * @param  array  $params  ['date_from', 'date_to', 'type' => 'vente|achat|all', 'format']
     * @return array  ['success', 'count', 'file_content', 'error']
     */
    public function exportInvoices(array $params): array;

    /**
     * Export accounting journal entries in SYSCOHADA format.
     *
     * @param  array  $params  ['date_from', 'date_to', 'journal_type_id', 'format']
     * @return array  ['success', 'count', 'file_content', 'error']
     */
    public function exportJournal(array $params): array;

    /**
     * Push a TVA declaration to the platform (when API is available).
     * Returns ['success', 'reference', 'error'].
     */
    public function declareTva(array $declaration): array;

    /**
     * Verify connectivity to the fiscal platform.
     * Returns ['success', 'latency_ms', 'message'].
     */
    public function ping(): array;
}
