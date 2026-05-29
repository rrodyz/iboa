<?php

namespace App\Services\Integrations;

use App\Models\Company;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\VatDeclaration;
use App\Services\Integrations\Contracts\FiscalGatewayInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Intégration fiscale — DGI Burkina Faso / e-SINTAX
 *
 * Portail DGI BF : https://impots.gov.bf
 * Télédéclaration : https://etax.impots.gov.bf
 *
 * État de l'API DGI BF (2026) :
 *  - L'API REST officielle est en cours de déploiement progressif.
 *  - Ce service supporte deux modes :
 *    • Mode API  : push direct vers l'endpoint DGI quand disponible.
 *    • Mode Export (défaut) : génération de fichiers CSV/XML conformes
 *      pour dépôt manuel sur le portail e-SINTAX.
 *
 * Formats acceptés par DGI BF :
 *  - Déclaration TVA  : XML SINTAX v2 ou CSV DGI
 *  - Factures         : CSV (séparateur point-virgule), encodage UTF-8 BOM
 *  - Journaux         : CSV SYSCOHADA ou FEC (Fichier des Écritures Comptables)
 *
 * Taux TVA Burkina Faso (code général des impôts) :
 *  - TVA standard : 18 %
 *  - Exonéré / 0 % : produits de première nécessité, exportations
 */
class FiscalBfService extends BaseApiService implements FiscalGatewayInterface
{
    // ── DGI BF endpoints (sandbox / production) ───────────────────────────────

    public const SANDBOX_BASE_URL    = 'https://etax-sandbox.impots.gov.bf/api/v1';
    public const PRODUCTION_BASE_URL = 'https://etax.impots.gov.bf/api/v1';

    /** Taux TVA légal Burkina Faso. */
    public const TVA_RATE_STANDARD = 18.0;

    /** Séparateur CSV conforme DGI BF (point-virgule + UTF-8 BOM). */
    private const CSV_SEP = ';';
    private const CSV_BOM = "\xEF\xBB\xBF";

    // ── Interface : TVA ───────────────────────────────────────────────────────

    /**
     * {@inheritDoc}
     *
     * Génère (ou envoie) la déclaration TVA pour la période.
     * Source : VatDeclaration + calcul direct depuis JournalEntryLine.
     */
    public function exportTva(array $params): array
    {
        try {
            $company = $this->resolveCompany();
            $format  = $params['format'] ?? 'csv';

            // Cherche une déclaration existante pour la période
            $declaration = VatDeclaration::where('company_id', $company->id)
                ->where('period_start', $params['period_start'])
                ->where('period_end',   $params['period_end'])
                ->whereNotIn('status', ['annule'])
                ->latest()
                ->first();

            // Calcul direct sur les écritures si pas de déclaration validée
            $tvaCollectee  = $declaration?->tva_collectee  ?? $this->computeTvaCollectee($company->id, $params);
            $tvaDeductible = $declaration?->tva_deductible ?? $this->computeTvaDeductible($company->id, $params);
            $tvaDue        = max(0, $tvaCollectee - $tvaDeductible);
            $creditTva     = max(0, $tvaDeductible - $tvaCollectee);

            $data = [
                'ifu'            => $company->ifu ?? '',
                'nif'            => $company->nif ?? '',
                'rccm'           => $company->rccm ?? '',
                'raison_sociale' => $company->name,
                'periode_debut'  => $params['period_start'],
                'periode_fin'    => $params['period_end'],
                'tva_collectee'  => $tvaCollectee,
                'tva_deductible' => $tvaDeductible,
                'tva_due'        => $tvaDue,
                'credit_tva'     => $creditTva,
                'declaration_ref'=> $declaration?->number ?? null,
                'statut'         => $declaration?->status ?? 'calcule',
            ];

            $fileContent = match ($format) {
                'xml'  => $this->buildTvaXml($data),
                'json' => json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
                default => $this->buildTvaCsv($data),
            };

            return [
                'success'      => true,
                'data'         => $data,
                'file_content' => $fileContent,
                'filename'     => "declaration_tva_{$params['period_start']}_{$params['period_end']}.{$format}",
                'error'        => null,
            ];

        } catch (\Throwable $e) {
            Log::error('[FiscalBf] exportTva error', ['error' => $e->getMessage()]);
            return ['success' => false, 'data' => null, 'file_content' => null, 'error' => $e->getMessage()];
        }
    }

    // ── Interface : Factures ──────────────────────────────────────────────────

    /**
     * {@inheritDoc}
     *
     * Export CSV/XML des factures clients (ventes) et/ou fournisseurs (achats)
     * conforme aux exigences DGI Burkina Faso.
     *
     * Colonnes obligatoires DGI BF :
     * N°, Date, NIF/IFU client, Raison sociale, HT, TVA, TTC, Statut
     */
    public function exportInvoices(array $params): array
    {
        try {
            $company = $this->resolveCompany();
            $format  = $params['format'] ?? 'csv';
            $type    = $params['type']   ?? 'vente';

            $rows = $this->fetchInvoiceRows($company->id, $params, $type);

            $fileContent = match ($format) {
                'xml'  => $this->buildInvoicesXml($rows, $company, $params),
                'json' => json_encode(['company' => $company->name, 'invoices' => $rows], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
                default => $this->buildInvoicesCsv($rows, $type),
            };

            return [
                'success'      => true,
                'count'        => count($rows),
                'file_content' => $fileContent,
                'filename'     => "export_factures_{$type}_{$params['date_from']}_{$params['date_to']}.{$format}",
                'error'        => null,
            ];

        } catch (\Throwable $e) {
            Log::error('[FiscalBf] exportInvoices error', ['error' => $e->getMessage()]);
            return ['success' => false, 'count' => 0, 'file_content' => null, 'error' => $e->getMessage()];
        }
    }

    // ── Interface : Journaux ──────────────────────────────────────────────────

    /**
     * {@inheritDoc}
     *
     * Export des écritures comptables au format SYSCOHADA / FEC (Fichier des
     * Écritures Comptables) — standard accepté par les commissaires aux comptes
     * et la DGI pour le contrôle fiscal.
     *
     * Format FEC (pipe-délimité, conforme DGE/DGI) :
     * JournalCode|JournalLib|EcritureNum|EcritureDate|CompteNum|CompteLib|
     * PieceRef|PieceDate|EcritureLib|Debit|Credit|EcritureLet|DateLet|
     * ValidDate|MontantDevise|Idevise
     */
    public function exportJournal(array $params): array
    {
        try {
            $company = $this->resolveCompany();
            $format  = $params['format'] ?? 'csv';

            $entries = JournalEntry::with(['lines.account', 'journalType'])
                ->where('company_id', $company->id)
                ->whereBetween('entry_date', [$params['date_from'], $params['date_to']])
                ->when(!empty($params['journal_type_id']), fn ($q) => $q->where('journal_type_id', $params['journal_type_id']))
                ->where('status', 'posted')
                ->orderBy('entry_date')
                ->orderBy('number')
                ->get();

            $rows  = $this->flattenJournalLines($entries);
            $count = $entries->count();

            $fileContent = match ($format) {
                'xml'  => $this->buildJournalXml($rows, $company, $params),
                'json' => json_encode(['company' => $company->name, 'entries' => $rows], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
                default => $this->buildJournalFec($rows),
            };

            return [
                'success'      => true,
                'count'        => $count,
                'lines_count'  => count($rows),
                'file_content' => $fileContent,
                'filename'     => "journal_fec_{$params['date_from']}_{$params['date_to']}.{$format}",
                'error'        => null,
            ];

        } catch (\Throwable $e) {
            Log::error('[FiscalBf] exportJournal error', ['error' => $e->getMessage()]);
            return ['success' => false, 'count' => 0, 'file_content' => null, 'error' => $e->getMessage()];
        }
    }

    // ── Interface : Déclaration push ──────────────────────────────────────────

    /**
     * {@inheritDoc}
     *
     * Envoie la déclaration TVA vers l'API DGI BF quand disponible.
     * Retourne une erreur explicite si l'API n'est pas configurée.
     */
    public function declareTva(array $declaration): array
    {
        // Vérification : l'API DGI BF est-elle activée dans la config ?
        $apiEnabled = (bool) ($this->integration->extra_config['api_push_enabled'] ?? false);

        if (! $apiEnabled) {
            return [
                'success'   => false,
                'reference' => null,
                'error'     => "Mode export uniquement. Activez 'api_push_enabled' dans la config pour pousser directement vers DGI.",
            ];
        }

        $token = $this->getApiToken();
        if (! $token) {
            return [
                'success'   => false,
                'reference' => null,
                'error'     => "Impossible d'obtenir le token DGI BF. Vérifiez client_id / client_secret.",
            ];
        }

        $result = $this->call('POST', '/declarations/tva', $declaration, [
            'Authorization' => "Bearer {$token}",
        ], retry: true);

        if ($result['success']) {
            return [
                'success'   => true,
                'reference' => $result['data']['reference'] ?? null,
                'error'     => null,
            ];
        }

        return [
            'success'   => false,
            'reference' => null,
            'error'     => $result['error'] ?? 'Erreur lors de l\'envoi DGI',
        ];
    }

    // ── Interface : Ping ──────────────────────────────────────────────────────

    /**
     * {@inheritDoc}
     */
    public function ping(): array
    {
        $start = microtime(true);

        // Tentative de ping vers le portail DGI BF
        $result = $this->call('GET', '/ping', [], [], retry: false);

        $latency = round((microtime(true) - $start) * 1000);

        if ($result['success']) {
            return [
                'success'    => true,
                'latency_ms' => $latency,
                'message'    => 'Portail DGI BF accessible.',
            ];
        }

        // Le portail est peut-être accessible mais l'endpoint /ping n'existe pas —
        // on considère que la connexion réseau fonctionne si on obtient un 4xx
        if (in_array($result['status'] ?? 0, [401, 403, 404, 405])) {
            return [
                'success'    => true,
                'latency_ms' => $latency,
                'message'    => "Portail DGI BF joignable (HTTP {$result['status']}). API prête.",
            ];
        }

        return [
            'success'    => false,
            'latency_ms' => $latency,
            'message'    => $result['error'] ?? 'Portail DGI BF non joignable.',
        ];
    }

    // ── Calculs TVA internes ──────────────────────────────────────────────────

    private function computeTvaCollectee(int $companyId, array $params): int
    {
        // Comptes TVA collectée SYSCOHADA BF : 4431, 4432, 4433, 4434, 4457
        return (int) JournalEntryLine::whereHas('journalEntry', fn ($q) =>
                $q->where('company_id', $companyId)
                  ->whereBetween('entry_date', [$params['period_start'], $params['period_end']])
                  ->where('status', 'posted')
            )
            ->whereHas('account', fn ($q) =>
                $q->whereIn('code', ['4431','4432','4433','4434','4457'])
            )
            ->sum('credit');
    }

    private function computeTvaDeductible(int $companyId, array $params): int
    {
        // Comptes TVA déductible SYSCOHADA BF : 4452, 4453, 4454, 4455, 4456, 4458
        return (int) JournalEntryLine::whereHas('journalEntry', fn ($q) =>
                $q->where('company_id', $companyId)
                  ->whereBetween('entry_date', [$params['period_start'], $params['period_end']])
                  ->where('status', 'posted')
            )
            ->whereHas('account', fn ($q) =>
                $q->whereIn('code', ['4452','4453','4454','4455','4456','4458'])
            )
            ->sum('debit');
    }

    // ── Builders CSV / XML ────────────────────────────────────────────────────

    private function buildTvaCsv(array $data): string
    {
        $sep = self::CSV_SEP;
        $lines = [
            self::CSV_BOM . implode($sep, [
                'IFU', 'NIF', 'RCCM', 'RAISON_SOCIALE',
                'PERIODE_DEBUT', 'PERIODE_FIN',
                'TVA_COLLECTEE', 'TVA_DEDUCTIBLE', 'TVA_DUE', 'CREDIT_TVA',
                'REFERENCE_DECLARATION', 'STATUT',
            ]),
            implode($sep, [
                $data['ifu'],
                $data['nif'],
                $data['rccm'],
                '"' . str_replace('"', '""', $data['raison_sociale']) . '"',
                $data['periode_debut'],
                $data['periode_fin'],
                number_format($data['tva_collectee'] / 100, 2, '.', ''),
                number_format($data['tva_deductible'] / 100, 2, '.', ''),
                number_format($data['tva_due'] / 100, 2, '.', ''),
                number_format($data['credit_tva'] / 100, 2, '.', ''),
                $data['declaration_ref'] ?? '',
                $data['statut'],
            ]),
        ];
        return implode("\r\n", $lines);
    }

    private function buildTvaXml(array $d): string
    {
        $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><DeclarationTVA/>');
        $xml->addAttribute('version', '2.0');
        $xml->addAttribute('pays', 'BF');
        $xml->addAttribute('plateforme', 'SINTAX');

        $emetteur = $xml->addChild('Emetteur');
        $emetteur->addChild('IFU',           htmlspecialchars($d['ifu']));
        $emetteur->addChild('NIF',           htmlspecialchars($d['nif']));
        $emetteur->addChild('RCCM',          htmlspecialchars($d['rccm']));
        $emetteur->addChild('RaisonSociale', htmlspecialchars($d['raison_sociale']));

        $periode = $xml->addChild('Periode');
        $periode->addChild('Debut', $d['periode_debut']);
        $periode->addChild('Fin',   $d['periode_fin']);

        $montants = $xml->addChild('Montants');
        $montants->addChild('TVACollectee',  number_format($d['tva_collectee'] / 100, 2, '.', ''));
        $montants->addChild('TVADeductible', number_format($d['tva_deductible'] / 100, 2, '.', ''));
        $montants->addChild('TVADue',        number_format($d['tva_due'] / 100, 2, '.', ''));
        $montants->addChild('CreditTVA',     number_format($d['credit_tva'] / 100, 2, '.', ''));
        $montants->addChild('Devise',        'XOF');

        if ($d['declaration_ref']) {
            $xml->addChild('Reference', htmlspecialchars($d['declaration_ref']));
        }
        $xml->addChild('Statut', $d['statut']);
        $xml->addChild('GenereAt', now()->toIso8601String());

        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;
        $dom->loadXML($xml->asXML());
        return $dom->saveXML();
    }

    // ─────────────────────────────────────────────────────────────────────────

    private function fetchInvoiceRows(int $companyId, array $params, string $type): array
    {
        $rows = [];

        if (in_array($type, ['vente', 'all'])) {
            $invoices = Invoice::with(['client', 'items'])
                ->where('company_id', $companyId)
                ->whereBetween('issued_at', [$params['date_from'], $params['date_to']])
                ->whereIn('status', ['valide', 'envoyee', 'payee', 'partiellement_payee'])
                ->orderBy('issued_at')
                ->orderBy('number')
                ->get();

            foreach ($invoices as $inv) {
                $rows[] = [
                    'type'           => 'VENTE',
                    'numero'         => $inv->number,
                    'date'           => $inv->issued_at?->format('Y-m-d'),
                    'echeance'       => $inv->due_at?->format('Y-m-d'),
                    'nif_ifu_client' => $inv->client?->nif ?? $inv->client?->ifu ?? '',
                    'client'         => $inv->client?->name ?? '',
                    'montant_ht'     => number_format(($inv->subtotal_ht ?? 0) / 100, 2, '.', ''),
                    'tva'            => number_format(($inv->total_tax ?? 0) / 100, 2, '.', ''),
                    'montant_ttc'    => number_format(($inv->total_ttc ?? 0) / 100, 2, '.', ''),
                    'statut'         => $inv->status,
                    'devise'         => $inv->currency_code ?? 'XOF',
                ];
            }
        }

        if (in_array($type, ['achat', 'all'])) {
            $supplierInvoices = \App\Models\SupplierInvoice::with(['supplier'])
                ->where('company_id', $companyId)
                ->whereBetween('invoice_date', [$params['date_from'], $params['date_to']])
                ->whereNotIn('status', ['brouillon', 'annule'])
                ->orderBy('invoice_date')
                ->get();

            foreach ($supplierInvoices as $inv) {
                $rows[] = [
                    'type'           => 'ACHAT',
                    'numero'         => $inv->reference ?? $inv->id,
                    'date'           => $inv->invoice_date?->format('Y-m-d'),
                    'echeance'       => $inv->due_date?->format('Y-m-d'),
                    'nif_ifu_client' => $inv->supplier?->nif ?? $inv->supplier?->ifu ?? '',
                    'client'         => $inv->supplier?->name ?? '',
                    'montant_ht'     => number_format(($inv->subtotal_ht ?? 0) / 100, 2, '.', ''),
                    'tva'            => number_format(($inv->total_tax ?? 0) / 100, 2, '.', ''),
                    'montant_ttc'    => number_format(($inv->total_ttc ?? 0) / 100, 2, '.', ''),
                    'statut'         => $inv->status,
                    'devise'         => 'XOF',
                ];
            }
        }

        return $rows;
    }

    private function buildInvoicesCsv(array $rows, string $type): string
    {
        $sep = self::CSV_SEP;
        $header = [
            'TYPE', 'NUMERO_FACTURE', 'DATE_EMISSION', 'DATE_ECHEANCE',
            'NIF_IFU_TIERS', 'RAISON_SOCIALE_TIERS',
            'MONTANT_HT', 'TVA', 'MONTANT_TTC', 'STATUT', 'DEVISE',
        ];
        $lines = [self::CSV_BOM . implode($sep, $header)];

        foreach ($rows as $r) {
            $lines[] = implode($sep, [
                $r['type'],
                $r['numero'],
                $r['date'],
                $r['echeance'] ?? '',
                $r['nif_ifu_client'],
                '"' . str_replace('"', '""', $r['client']) . '"',
                $r['montant_ht'],
                $r['tva'],
                $r['montant_ttc'],
                $r['statut'],
                $r['devise'],
            ]);
        }

        return implode("\r\n", $lines);
    }

    private function buildInvoicesXml(array $rows, Company $company, array $params): string
    {
        $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><ExportFactures/>');
        $xml->addAttribute('version', '1.0');
        $xml->addAttribute('pays', 'BF');

        $meta = $xml->addChild('Emetteur');
        $meta->addChild('IFU',  htmlspecialchars($company->ifu ?? ''));
        $meta->addChild('Nom',  htmlspecialchars($company->name));
        $meta->addChild('Du',   $params['date_from']);
        $meta->addChild('Au',   $params['date_to']);
        $meta->addChild('Nb',   count($rows));

        $liste = $xml->addChild('Factures');
        foreach ($rows as $r) {
            $f = $liste->addChild('Facture');
            $f->addChild('Type',         $r['type']);
            $f->addChild('Numero',       htmlspecialchars((string) $r['numero']));
            $f->addChild('Date',         $r['date']);
            $f->addChild('Echeance',     $r['echeance'] ?? '');
            $f->addChild('NIF_IFU',      htmlspecialchars($r['nif_ifu_client']));
            $f->addChild('Tiers',        htmlspecialchars($r['client']));
            $f->addChild('MontantHT',    $r['montant_ht']);
            $f->addChild('TVA',          $r['tva']);
            $f->addChild('MontantTTC',   $r['montant_ttc']);
            $f->addChild('Statut',       htmlspecialchars($r['statut']));
            $f->addChild('Devise',       $r['devise']);
        }

        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;
        $dom->loadXML($xml->asXML());
        return $dom->saveXML();
    }

    // ── Journaux / FEC ────────────────────────────────────────────────────────

    private function flattenJournalLines($entries): array
    {
        $rows = [];
        foreach ($entries as $entry) {
            foreach ($entry->lines as $line) {
                $rows[] = [
                    'journal_code'  => $entry->journalType?->code ?? '',
                    'journal_lib'   => $entry->journalType?->name ?? '',
                    'ecriture_num'  => $entry->number,
                    'ecriture_date' => $entry->entry_date?->format('Ymd'),
                    'compte_num'    => $line->account?->code ?? '',
                    'compte_lib'    => $line->account?->name ?? '',
                    'piece_ref'     => $entry->reference ?? '',
                    'piece_date'    => $entry->entry_date?->format('Ymd'),
                    'ecriture_lib'  => $line->label ?: $entry->description,
                    'debit'         => number_format(($line->debit ?? 0) / 100, 2, '.', ''),
                    'credit'        => number_format(($line->credit ?? 0) / 100, 2, '.', ''),
                    'lettering'     => $line->reconciliation_ref ?? '',
                    'date_let'      => $line->lettered_at?->format('Ymd') ?? '',
                    'valid_date'    => $entry->validated_at?->format('Ymd') ?? '',
                    'montant_devise'=> '',
                    'idevise'       => '',
                ];
            }
        }
        return $rows;
    }

    /**
     * Format FEC (Fichier des Écritures Comptables) — pipe-délimité.
     * Standard DGE/DGI France, adapté pour usage SYSCOHADA BF.
     */
    private function buildJournalFec(array $rows): string
    {
        $sep = '|';
        $header = implode($sep, [
            'JournalCode', 'JournalLib', 'EcritureNum', 'EcritureDate',
            'CompteNum', 'CompteLib', 'PieceRef', 'PieceDate',
            'EcritureLib', 'Debit', 'Credit', 'EcritureLet', 'DateLet',
            'ValidDate', 'MontantDevise', 'Idevise',
        ]);
        $lines = [self::CSV_BOM . $header];

        foreach ($rows as $r) {
            $lines[] = implode($sep, [
                $r['journal_code'],
                $r['journal_lib'],
                $r['ecriture_num'],
                $r['ecriture_date'],
                $r['compte_num'],
                $r['compte_lib'],
                $r['piece_ref'],
                $r['piece_date'],
                str_replace(['|', "\n", "\r"], [' ', ' ', ''], $r['ecriture_lib']),
                $r['debit'],
                $r['credit'],
                $r['lettering'],
                $r['date_let'],
                $r['valid_date'],
                $r['montant_devise'],
                $r['idevise'],
            ]);
        }

        return implode("\r\n", $lines);
    }

    private function buildJournalXml(array $rows, Company $company, array $params): string
    {
        $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><ExportJournal/>');
        $xml->addAttribute('format', 'SYSCOHADA');
        $xml->addAttribute('pays', 'BF');

        $meta = $xml->addChild('Societe');
        $meta->addChild('IFU', htmlspecialchars($company->ifu ?? ''));
        $meta->addChild('Nom', htmlspecialchars($company->name));
        $meta->addChild('Du',  $params['date_from']);
        $meta->addChild('Au',  $params['date_to']);

        $ecritures = $xml->addChild('Ecritures');
        foreach ($rows as $r) {
            $e = $ecritures->addChild('Ecriture');
            foreach ($r as $key => $val) {
                $e->addChild(ucfirst(str_replace('_', '', $key)), htmlspecialchars((string) $val));
            }
        }

        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;
        $dom->loadXML($xml->asXML());
        return $dom->saveXML();
    }

    // ── Auth DGI BF (OAuth2) ──────────────────────────────────────────────────

    private function getApiToken(): ?string
    {
        $clientId     = $this->integration->client_id ?? '';
        $clientSecret = $this->integration->client_secret ?? '';

        if (! $clientId || ! $clientSecret) {
            return null;
        }

        $tokenUrl = $this->integration->extra_config['token_endpoint']
            ?? ($this->integration->effectiveBaseUrl() . '/oauth/token');

        return $this->fetchOAuthToken($tokenUrl, $clientId, $clientSecret);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function resolveCompany(): Company
    {
        $user = Auth::user();
        if (! $user) {
            // Fallback pour les appels en CLI / artisan
            return currentCompany() ?? throw new \RuntimeException('Aucune société configurée.');
        }
        return Company::findOrFail($user->company_id);
    }
}
