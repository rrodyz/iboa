<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * [VENTES-PRO] Indicateurs avancés du module Ventes — équivalent du dashboard Odoo/Sage.
 *
 *   - KPIs synthétiques : CA mois, encours clients, DSO, taux conversion devis
 *   - Top clients / top articles vendus
 *   - Échéances clients à venir / en retard
 *   - Pipeline devis (par statut)
 *   - Évolution mensuelle 12 mois (CA HT + #factures)
 */
class SalesInsightsService
{
    /**
     * KPIs synthétiques pour le tableau de bord.
     */
    public function dashboardKpis(): array
    {
        $today = now();
        $startOfMonth = $today->copy()->startOfMonth();
        $endOfMonth   = $today->copy()->endOfMonth();
        $startPrevMonth = $today->copy()->subMonth()->startOfMonth();
        $endPrevMonth   = $today->copy()->subMonth()->endOfMonth();

        // CA HT du mois (factures non-brouillon, non-annulées, non-avoir)
        $caMonth = (int) DB::table('invoices')
            ->whereNull('deleted_at')
            ->whereNotIn('status', ['brouillon', 'annulee'])
            ->where('type', '!=', 'avoir')
            ->whereBetween('issued_at', [$startOfMonth, $endOfMonth])
            ->sum('subtotal_ht');

        $caPrevMonth = (int) DB::table('invoices')
            ->whereNull('deleted_at')
            ->whereNotIn('status', ['brouillon', 'annulee'])
            ->where('type', '!=', 'avoir')
            ->whereBetween('issued_at', [$startPrevMonth, $endPrevMonth])
            ->sum('subtotal_ht');

        $variation = $caPrevMonth > 0 ? round(($caMonth - $caPrevMonth) / $caPrevMonth * 100, 1) : null;

        // Encours clients (total factures non payées)
        $outstanding = (int) DB::table('invoices')
            ->whereNull('deleted_at')
            ->whereIn('status', ['emise', 'envoyee', 'partiellement_payee', 'en_retard'])
            ->sum('remaining_amount');

        // Retards (due_at < aujourd'hui)
        $overdue = (int) DB::table('invoices')
            ->whereNull('deleted_at')
            ->whereIn('status', ['emise', 'envoyee', 'partiellement_payee', 'en_retard'])
            ->whereDate('due_at', '<', $today->toDateString())
            ->where('remaining_amount', '>', 0)
            ->sum('remaining_amount');

        $overdueCount = (int) DB::table('invoices')
            ->whereNull('deleted_at')
            ->whereIn('status', ['emise', 'envoyee', 'partiellement_payee', 'en_retard'])
            ->whereDate('due_at', '<', $today->toDateString())
            ->where('remaining_amount', '>', 0)
            ->count();

        // DSO (Days Sales Outstanding) — délai moyen de paiement
        // approximation : (encours / CA 90 derniers jours) × 90
        $ca90 = (int) DB::table('invoices')
            ->whereNull('deleted_at')
            ->whereNotIn('status', ['brouillon', 'annulee'])
            ->where('type', '!=', 'avoir')
            ->where('issued_at', '>=', $today->copy()->subDays(90))
            ->sum('subtotal_ht');
        $dso = $ca90 > 0 ? round($outstanding / $ca90 * 90, 0) : 0;

        // Devis : taux de conversion (acceptés ou convertis / total émis hors brouillon/annulé)
        $quotesSent = DB::table('quotes')
            ->whereNull('deleted_at')
            ->whereNotIn('status', ['brouillon', 'annule'])
            ->count();
        $quotesAccepted = DB::table('quotes')
            ->whereNull('deleted_at')
            ->whereIn('status', ['accepte', 'converti'])
            ->count();
        $conversionRate = $quotesSent > 0 ? round($quotesAccepted / $quotesSent * 100, 1) : 0;

        // Commandes en cours (non livrées, non annulées)
        $ordersInProgress = DB::table('orders')
            ->whereNull('deleted_at')
            ->whereIn('status', ['confirmee', 'partiellement_livre'])
            ->count();

        // Factures brouillon à valider
        $draftInvoices = DB::table('invoices')
            ->whereNull('deleted_at')
            ->where('status', 'brouillon')
            ->count();

        return [
            'ca_month'           => $caMonth,
            'ca_prev_month'      => $caPrevMonth,
            'ca_variation_pct'   => $variation,
            'outstanding'        => $outstanding,
            'overdue_amount'     => $overdue,
            'overdue_count'      => $overdueCount,
            'dso_days'           => $dso,
            'quotes_sent'        => $quotesSent,
            'quotes_accepted'    => $quotesAccepted,
            'conversion_rate'    => $conversionRate,
            'orders_in_progress' => $ordersInProgress,
            'draft_invoices'     => $draftInvoices,
        ];
    }

    /**
     * Échéances clients à venir (30 jours) + en retard.
     */
    public function upcomingDueInvoices(int $days = 30): Collection
    {
        $today = now()->toDateString();
        $limit = now()->addDays($days)->toDateString();

        return DB::table('invoices as i')
            ->join('clients as c', 'c.id', '=', 'i.client_id')
            ->whereNull('i.deleted_at')
            ->whereIn('i.status', ['emise', 'envoyee', 'partiellement_payee', 'en_retard'])
            ->where('i.remaining_amount', '>', 0)
            ->where(function ($q) use ($limit) {
                $q->whereNull('i.due_at')->orWhereDate('i.due_at', '<=', $limit);
            })
            ->select('i.id', 'i.number', 'i.status', 'i.issued_at', 'i.due_at',
                'i.total_ttc', 'i.paid_amount', 'i.remaining_amount',
                'c.name as client_name',
                DB::raw("CASE WHEN i.due_at < '{$today}' THEN 1 ELSE 0 END as is_overdue"),
                DB::raw("DATEDIFF(i.due_at, NOW()) as days_to_due"))
            ->orderBy('i.due_at')
            ->limit(20)
            ->get();
    }

    /**
     * Top clients par CA sur les 12 derniers mois.
     */
    public function topClients(int $limit = 5): Collection
    {
        $from = now()->subMonths(12)->startOfMonth();
        return DB::table('invoices as i')
            ->join('clients as c', 'c.id', '=', 'i.client_id')
            ->whereNull('i.deleted_at')
            ->whereNotIn('i.status', ['brouillon', 'annulee'])
            ->where('i.type', '!=', 'avoir')
            ->where('i.issued_at', '>=', $from)
            ->select('c.id', 'c.name',
                DB::raw('COUNT(DISTINCT i.id) as invoices_count'),
                DB::raw('SUM(i.subtotal_ht) as total_ht'),
                DB::raw('SUM(i.remaining_amount) as outstanding'))
            ->groupBy('c.id', 'c.name')
            ->orderByDesc('total_ht')
            ->limit($limit)
            ->get();
    }

    /**
     * Top articles vendus (quantité + CA) sur les 12 derniers mois.
     */
    public function topProducts(int $limit = 5): Collection
    {
        $from = now()->subMonths(12)->startOfMonth();
        return DB::table('invoice_items as it')
            ->join('invoices as i', 'i.id', '=', 'it.invoice_id')
            ->join('products as p', 'p.id', '=', 'it.product_id')
            ->whereNull('i.deleted_at')
            ->whereNotIn('i.status', ['brouillon', 'annulee'])
            ->where('i.type', '!=', 'avoir')
            ->where('i.issued_at', '>=', $from)
            ->select('p.id', 'p.reference', 'p.name',
                DB::raw('SUM(it.quantity) as qty_sold'),
                DB::raw('SUM(it.line_total_ht) as total_ht'))
            ->groupBy('p.id', 'p.reference', 'p.name')
            ->orderByDesc('total_ht')
            ->limit($limit)
            ->get();
    }

    /**
     * CA HT par mois sur les 12 derniers mois (pour graphique évolution).
     */
    public function monthlyEvolution(int $months = 12): Collection
    {
        $from = now()->subMonths($months - 1)->startOfMonth();
        return DB::table('invoices')
            ->whereNull('deleted_at')
            ->whereNotIn('status', ['brouillon', 'annulee'])
            ->where('type', '!=', 'avoir')
            ->where('issued_at', '>=', $from)
            ->select(
                DB::raw('DATE_FORMAT(issued_at, "%Y-%m") as month'),
                DB::raw('SUM(subtotal_ht) as total_ht'),
                DB::raw('COUNT(*) as invoices_count'))
            ->groupBy('month')
            ->orderBy('month')
            ->get();
    }

    /**
     * Pipeline devis par statut (équivalent funnel Odoo).
     */
    public function quotesPipeline(): array
    {
        $rows = DB::table('quotes')
            ->whereNull('deleted_at')
            ->select('status', DB::raw('COUNT(*) as count'), DB::raw('SUM(total_ttc) as total'))
            ->groupBy('status')
            ->get();

        $labels = [
            'brouillon' => 'Brouillon',
            'envoye'    => 'Envoyé',
            'accepte'   => 'Accepté',
            'converti'  => 'Converti en cmd',
            'refuse'    => 'Refusé',
            'expire'    => 'Expiré',
            'annule'    => 'Annulé',
        ];

        $result = [];
        foreach ($labels as $k => $label) {
            $row = $rows->firstWhere('status', $k);
            $result[$k] = [
                'label' => $label,
                'count' => $row?->count ?? 0,
                'total' => (int) ($row?->total ?? 0),
            ];
        }
        return $result;
    }
}
