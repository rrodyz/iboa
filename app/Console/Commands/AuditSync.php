<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * [AUDIT-SYNC] Audit des synchronisations inter-modules.
 *
 * Vérifie que les chaînes de données entre modules sont cohérentes :
 *   - Devis/Commande/BL/Facture/Avoir (ventes)
 *   - Demande/RFQ/PO/Réception/FF/Paiement (achats)
 *   - Stock movements ↔ stocks physiques / parents
 *   - Compta : écriture pour chaque document validé
 *   - Trésorerie : transaction pour chaque paiement validé
 *
 * Usage : php artisan audit:sync [--json] [--fail-on=HIGH|MED|LOW|never]
 */
class AuditSync extends Command
{
    protected $signature = 'audit:sync
                            {--json : Sortie JSON}
                            {--fail-on=HIGH : Exit non-zero si anomalie de cette sévérité (HIGH|MED|LOW|never)}';

    protected $description = 'Audit des synchronisations cross-modules (intégrité des chaînes de documents).';

    private array $findings = [];

    public function handle(): int
    {
        $this->collect();

        $rank = ['HIGH' => 0, 'MED' => 1, 'LOW' => 2];
        usort($this->findings, fn($a, $b) =>
            [$rank[$a['severity']], $a['count'] > 0 ? 0 : 1, $a['area']]
            <=>
            [$rank[$b['severity']], $b['count'] > 0 ? 0 : 1, $b['area']]
        );

        if ($this->option('json')) {
            $this->line(json_encode($this->findings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        } else {
            $this->render();
        }

        $failOn = strtoupper($this->option('fail-on') ?? 'HIGH');
        if ($failOn === 'NEVER') return self::SUCCESS;
        $threshold = $rank[$failOn] ?? 0;
        foreach ($this->findings as $f) {
            if ($f['count'] > 0 && ($rank[$f['severity']] ?? 99) <= $threshold) return self::FAILURE;
        }
        return self::SUCCESS;
    }

    private function add(string $area, string $severity, string $title, $count, $sample = null): void
    {
        $arr = function ($x) {
            if (is_array($x)) return $x;
            if (is_object($x) && method_exists($x, 'all')) return $x->all();
            return (array) $x;
        };
        $this->findings[] = [
            'area'     => $area,
            'severity' => $severity,
            'title'    => $title,
            'count'    => (int) $count,
            'sample'   => $count > 0 && $sample
                ? array_map(fn($s) => is_object($s) ? get_object_vars($s) : (array)$s, array_slice($arr($sample), 0, 3))
                : null,
        ];
    }

    private function collect(): void
    {
        // ════════════ VENTES ════════════

        $x = DB::select("
            SELECT q.id, q.number FROM quotes q
            LEFT JOIN orders o ON o.id = q.converted_to_order_id
            WHERE q.converted_to_order_id IS NOT NULL AND o.id IS NULL AND q.deleted_at IS NULL LIMIT 5");
        $this->add('Ventes', 'HIGH', 'Devis pointe vers Order inexistant', count($x), $x);

        if (Schema::hasColumn('invoices', 'order_id') && Schema::hasColumn('invoices', 'type')) {
            $x = DB::select("
                SELECT o.id, o.number, o.total_ttc, COALESCE(SUM(i.total_ttc),0) invoiced
                FROM orders o
                LEFT JOIN invoices i ON i.order_id = o.id
                    AND i.status NOT IN ('brouillon','annulee')
                    AND i.deleted_at IS NULL
                    AND i.type != 'avoir'
                WHERE o.deleted_at IS NULL AND o.status NOT IN ('brouillon','annulee')
                GROUP BY o.id, o.number, o.total_ttc
                HAVING COALESCE(SUM(i.total_ttc),0) > o.total_ttc + 100 LIMIT 5");
            $this->add('Ventes', 'MED', 'Commande sur-facturée (∑factures > total commande)', count($x), $x);
        }

        // [SYNC-FIX] BL facturé plus d'une fois
        if (Schema::hasColumn('invoices', 'delivery_note_id')) {
            $x = DB::select("
                SELECT delivery_note_id, COUNT(*) n
                FROM invoices
                WHERE delivery_note_id IS NOT NULL AND status != 'annulee' AND deleted_at IS NULL
                GROUP BY delivery_note_id HAVING COUNT(*) > 1 LIMIT 5");
            $this->add('Ventes', 'HIGH', 'BL facturé plusieurs fois', count($x), $x);
        }

        // [WITHHOLDING + CN AWARE] paid_amount cohérent avec allocations + avoirs appliqués
        if (Schema::hasTable('client_payment_allocations') && Schema::hasTable('credit_notes')) {
            $x = DB::select("
                SELECT i.id, i.number, i.paid_amount,
                       (
                         COALESCE((SELECT SUM(a.amount) FROM client_payment_allocations a
                                   JOIN client_payments p ON p.id = a.client_payment_id
                                   WHERE a.invoice_id = i.id AND p.deleted_at IS NULL),0)
                         + COALESCE((SELECT SUM(applied_amount) FROM credit_notes
                                     WHERE invoice_id = i.id AND deleted_at IS NULL AND status IN ('valide','applique')),0)
                       ) AS expected_paid
                FROM invoices i
                WHERE i.deleted_at IS NULL AND i.status != 'brouillon'
                  AND ABS(i.paid_amount - (
                      COALESCE((SELECT SUM(a.amount) FROM client_payment_allocations a JOIN client_payments p ON p.id = a.client_payment_id WHERE a.invoice_id = i.id AND p.deleted_at IS NULL),0)
                      + COALESCE((SELECT SUM(applied_amount) FROM credit_notes WHERE invoice_id = i.id AND deleted_at IS NULL AND status IN ('valide','applique')),0)
                  )) > 1
                LIMIT 5");
            $this->add('Ventes', 'HIGH', 'Facture paid_amount ≠ Σ(paiements + avoirs appliqués)', count($x), $x);
        }

        // ════════════ ACHATS ════════════

        $x = DB::select("
            SELECT poi.id, po.number, poi.quantity, poi.invoiced_quantity,
                   COALESCE((SELECT SUM(received_quantity) FROM reception_items WHERE purchase_order_item_id = poi.id),0) recv
            FROM purchase_order_items poi
            JOIN purchase_orders po ON po.id = poi.purchase_order_id
            WHERE po.deleted_at IS NULL
              AND poi.invoiced_quantity > COALESCE((SELECT SUM(received_quantity) FROM reception_items WHERE purchase_order_item_id = poi.id),0) + 0.01
              AND COALESCE((SELECT SUM(received_quantity) FROM reception_items WHERE purchase_order_item_id = poi.id),0) > 0
            LIMIT 5");
        $this->add('Achats', 'MED', 'PO : qté facturée > qté reçue', count($x), $x);

        $x = DB::select("
            SELECT r.id, r.number, r.purchase_order_id
            FROM receptions r LEFT JOIN purchase_orders po ON po.id = r.purchase_order_id
            WHERE r.deleted_at IS NULL AND r.purchase_order_id IS NOT NULL
              AND (po.id IS NULL OR po.deleted_at IS NOT NULL) LIMIT 5");
        $this->add('Achats', 'HIGH', 'Réception orpheline (PO supprimé ou inexistant)', count($x), $x);

        if (Schema::hasTable('payment_schedules')) {
            $x = DB::select("
                SELECT si.id, si.number, si.total_ttc, COALESCE(SUM(ps.amount),0) schedule_sum
                FROM supplier_invoices si JOIN payment_schedules ps ON ps.supplier_invoice_id = si.id
                WHERE si.deleted_at IS NULL
                GROUP BY si.id, si.number, si.total_ttc
                HAVING ABS(si.total_ttc - COALESCE(SUM(ps.amount),0)) > 1 LIMIT 5");
            $this->add('Achats', 'HIGH', 'Cadencier : Σ échéances ≠ total_ttc facture', count($x), $x);
        }

        if (Schema::hasTable('supplier_payment_allocations')) {
            $x = DB::select("
                SELECT si.id, si.number, si.paid_amount, COALESCE(SUM(a.amount),0) allocated_sum
                FROM supplier_invoices si
                LEFT JOIN supplier_payment_allocations a ON a.supplier_invoice_id = si.id
                LEFT JOIN supplier_payments sp ON sp.id = a.supplier_payment_id AND sp.deleted_at IS NULL
                WHERE si.deleted_at IS NULL AND si.status != 'brouillon'
                GROUP BY si.id, si.number, si.paid_amount
                HAVING ABS(si.paid_amount - COALESCE(SUM(CASE WHEN sp.id IS NOT NULL THEN a.amount ELSE 0 END),0)) > 1 LIMIT 5");
            $this->add('Achats', 'HIGH', 'FF paid_amount ≠ Σ allocations', count($x), $x);
        }

        if (Schema::hasColumn('rfqs', 'purchase_order_id')) {
            $x = DB::select("
                SELECT r.id, r.number FROM rfqs r
                LEFT JOIN purchase_orders po ON po.id = r.purchase_order_id
                WHERE r.deleted_at IS NULL AND r.purchase_order_id IS NOT NULL
                  AND (po.id IS NULL OR po.deleted_at IS NOT NULL) LIMIT 5");
            $this->add('Achats', 'MED', 'RFQ pointe vers PO supprimé', count($x), $x);
        }

        // ════════════ STOCK ════════════

        $rows = DB::select("
            SELECT m.id, m.type, m.reference_type, m.reference_id FROM stock_movements m
            WHERE m.reference_type IN ('App\\\\Models\\\\Reception','App\\\\Models\\\\DeliveryNote','App\\\\Models\\\\StockTransfer')
              AND m.reference_id IS NOT NULL");
        $broken = [];
        foreach ($rows as $m) {
            $table = match ($m->reference_type) {
                'App\\Models\\Reception'      => 'receptions',
                'App\\Models\\DeliveryNote'   => 'delivery_notes',
                'App\\Models\\StockTransfer'  => 'stock_transfers',
                default => null,
            };
            if (!$table) continue;
            $exists = DB::table($table)->where('id', $m->reference_id)->whereNull('deleted_at')->exists();
            if (!$exists) $broken[] = $m;
        }
        $this->add('Stock', 'MED', 'Mouvement avec parent supprimé', count($broken), $broken);

        if (Schema::hasTable('stock_transfers')) {
            $x = DB::select("
                SELECT st.id, st.number FROM stock_transfers st
                JOIN stock_transfer_items sti ON sti.stock_transfer_id = st.id
                WHERE st.status = 'recu' AND st.deleted_at IS NULL
                GROUP BY st.id, st.number
                HAVING SUM(CASE WHEN sti.received_quantity IS NULL THEN 1 ELSE 0 END) > 0 LIMIT 5");
            $this->add('Stock', 'MED', 'Transfert reçu avec lignes sans received_quantity', count($x), $x);
        }

        // ════════════ COMPTA ════════════

        $x = DB::select("
            SELECT i.id, i.number FROM invoices i
            WHERE i.status IN ('emise','partiellement_payee','payee','en_retard') AND i.deleted_at IS NULL
              AND i.type != 'avoir'
              AND NOT EXISTS (SELECT 1 FROM journal_entries je WHERE je.reference = i.number AND je.deleted_at IS NULL AND je.status != 'brouillon')
            LIMIT 5");
        $this->add('Compta', 'HIGH', 'Facture validée sans écriture comptable', count($x), $x);

        $x = DB::select("
            SELECT si.id, si.number FROM supplier_invoices si
            WHERE si.status IN ('validee','partiellement_payee','payee','en_retard') AND si.deleted_at IS NULL
              AND NOT EXISTS (SELECT 1 FROM journal_entries je WHERE je.reference = si.number AND je.deleted_at IS NULL AND je.status != 'brouillon')
            LIMIT 5");
        $this->add('Compta', 'HIGH', 'FF validée sans écriture comptable', count($x), $x);

        $x = DB::select("
            SELECT l.id, l.journal_entry_id, l.account_id FROM journal_entry_lines l
            LEFT JOIN accounts a ON a.id = l.account_id
            WHERE a.id IS NULL OR a.is_active = 0 LIMIT 5");
        $this->add('Compta', 'MED', 'Ligne d\'écriture référence compte inactif/inexistant', count($x), $x);

        // ════════════ TRÉSORERIE ════════════

        if (Schema::hasColumn('cash_transactions', 'reference_type')) {
            $x = DB::select("
                SELECT cp.id, cp.number FROM client_payments cp
                WHERE cp.deleted_at IS NULL AND cp.status IN ('valide','encaisse','effectue')
                  AND NOT EXISTS (SELECT 1 FROM cash_transactions ct WHERE ct.reference_type = 'App\\\\Models\\\\ClientPayment' AND ct.reference_id = cp.id)
                LIMIT 5");
            $this->add('Tréso', 'MED', 'ClientPayment validé sans CashTransaction', count($x), $x);

            $x = DB::select("
                SELECT sp.id, sp.number FROM supplier_payments sp
                WHERE sp.deleted_at IS NULL AND sp.status IN ('valide','effectue')
                  AND NOT EXISTS (SELECT 1 FROM cash_transactions ct WHERE ct.reference_type = 'App\\\\Models\\\\SupplierPayment' AND ct.reference_id = sp.id)
                LIMIT 5");
            $this->add('Tréso', 'MED', 'SupplierPayment validé sans CashTransaction', count($x), $x);
        }
    }

    private function render(): void
    {
        $issues = array_filter($this->findings, fn($f) => $f['count'] > 0);

        $this->newLine();
        $this->line('  <fg=cyan>══ AUDIT SYNCHRONISATION INTER-MODULES ══</>');
        if (empty($issues)) {
            $this->line('  <fg=green;options=bold>✓ Aucune anomalie de synchronisation — '.count($this->findings).' contrôles passés.</>');
            $this->newLine();
            return;
        }
        $this->line(sprintf('  <fg=yellow>%d / %d contrôles ont remonté des anomalies</>', count($issues), count($this->findings)));
        $this->newLine();

        foreach ($this->findings as $f) {
            if ($f['count'] === 0) continue;
            $color = match ($f['severity']) { 'HIGH' => 'red', 'MED' => 'yellow', default => 'gray' };
            $this->line(sprintf('  <fg=%s>[%s]</> <fg=cyan>%s</> %s : <fg=%s;options=bold>%d</>',
                $color, $f['severity'], str_pad($f['area'], 9), $f['title'], $color, $f['count']));
            if ($f['sample']) {
                foreach ($f['sample'] as $s) {
                    $this->line('       <fg=gray>→ '.json_encode($s, JSON_UNESCAPED_UNICODE).'</>');
                }
            }
        }
        $this->newLine();
    }
}
