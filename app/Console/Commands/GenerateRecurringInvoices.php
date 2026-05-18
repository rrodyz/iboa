<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Services\InvoiceService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * [MED-2] Génère les factures récurrentes échues.
 *
 * Cible : factures `is_recurring=true` dont `next_recurring_date <= today` et statut non annulé.
 * Pour chaque match :
 *  - Crée une nouvelle facture (statut brouillon) avec les mêmes lignes
 *  - Lien `parent_invoice_id` sur la facture mère
 *  - Met à jour `next_recurring_date` selon `recurring_frequency`
 *
 * Fréquences supportées : monthly, quarterly, yearly
 * La nouvelle facture reste en brouillon — l'utilisateur la valide après vérification.
 */
class GenerateRecurringInvoices extends Command
{
    protected $signature   = 'invoices:generate-recurring {--dry-run : N\'effectue aucune écriture, affiche seulement les factures qui seraient créées}';
    protected $description = 'Génère les factures récurrentes échues (cron quotidien)';

    public function handle(InvoiceService $service): int
    {
        $today  = Carbon::today();
        $dryRun = $this->option('dry-run');

        // [MED-2] Sélectionne uniquement les factures "mères" actives dont la date de renouvellement est arrivée.
        // On ne génère pas de factures à partir d'autres récurrences (évite l'effet boule de neige).
        $templates = Invoice::where('is_recurring', true)
            ->whereNotNull('recurring_frequency')
            ->whereNotNull('next_recurring_date')
            ->where('next_recurring_date', '<=', $today)
            ->whereNotIn('status', ['brouillon', 'annulee'])
            ->whereNull('parent_invoice_id')   // seulement les templates "mères"
            ->with('items')
            ->get();

        if ($templates->isEmpty()) {
            $this->info("Aucune facture récurrente à générer aujourd'hui.");
            return self::SUCCESS;
        }

        $this->info("📋 {$templates->count()} facture(s) récurrente(s) à traiter".($dryRun ? " (DRY-RUN)" : "")."\n");

        $created = 0;
        $errors  = 0;

        foreach ($templates as $template) {
            try {
                if ($dryRun) {
                    $this->line("  [DRY] Génération depuis #{$template->number} → client {$template->client_id}, fréquence: {$template->recurring_frequency}");
                    $created++;
                    continue;
                }

                DB::transaction(function () use ($template, $service, &$created) {
                    $items = $template->items->map(fn($item) => $item->only([
                        'product_id', 'description', 'unit_id', 'quantity', 'unit_price',
                        'discount_percent', 'tax_rate_id', 'tax_rate_value',
                    ]))->toArray();

                    $newInvoice = $service->create([
                        'client_id'          => $template->client_id,
                        'issued_at'          => today()->toDateString(),
                        'payment_term_id'    => $template->payment_term_id,
                        'currency_code'      => $template->currency_code,
                        'exchange_rate'      => $template->exchange_rate,
                        'billing_address'    => $template->billing_address,
                        'notes'              => $template->notes,
                        'terms'              => $template->terms,
                        'footer_note'        => $template->footer_note,
                        'parent_invoice_id'  => $template->id,
                        'is_recurring'       => false,                          // l'enfant n'est PAS récurrent
                        'type'               => 'standard',
                        'items'              => $items,
                    ]);

                    // Met à jour la date de prochain renouvellement sur le template
                    $template->update([
                        'next_recurring_date' => $this->nextDate(
                            $template->next_recurring_date,
                            $template->recurring_frequency
                        ),
                    ]);

                    $this->line("  ✓ Facture {$newInvoice->number} générée depuis {$template->number}");
                    $created++;
                });
            } catch (\Throwable $e) {
                $errors++;
                $this->error("  ✗ {$template->number} : " . $e->getMessage());
                Log::error('Échec génération facture récurrente', [
                    'template_id' => $template->id,
                    'error'       => $e->getMessage(),
                ]);
            }
        }

        $this->newLine();
        $this->info("✅ Total : {$created} facture(s) générée(s), {$errors} échec(s)");

        return $errors === 0 ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Calcule la prochaine date de récurrence selon la fréquence.
     */
    private function nextDate(Carbon $current, string $frequency): Carbon
    {
        return match ($frequency) {
            'monthly'   => $current->copy()->addMonth(),
            'quarterly' => $current->copy()->addMonths(3),
            'yearly'    => $current->copy()->addYear(),
            default     => $current->copy()->addMonth(),
        };
    }
}
