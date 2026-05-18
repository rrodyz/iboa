<?php

namespace App\Services;

use App\Models\CommercialEffect;
use App\Models\Company;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CommercialEffectService
{
    public function __construct(private DocumentSequenceService $seq) {}

    public function create(array $data): CommercialEffect
    {
        return DB::transaction(function () use ($data) {
            $company            = Company::firstOrFail();
            $data['company_id'] = $company->id;
            $data['number']     = $this->seq->nextNumber($company, 'effet_commerce');
            $data['created_by'] = Auth::id();
            $data['status']     = 'en_attente';

            return CommercialEffect::create($data);
        });
    }

    public function accept(CommercialEffect $effect, ?string $date = null): CommercialEffect
    {
        if (!in_array($effect->status, ['en_attente'])) {
            throw new \RuntimeException('Cet effet ne peut pas être accepté dans son état actuel.');
        }
        $effect->update([
            'status'          => 'accepte',
            'acceptance_date' => $date ?? today()->toDateString(),
        ]);
        return $effect->fresh();
    }

    public function markEncaisse(CommercialEffect $effect, string $paymentDate): CommercialEffect
    {
        if (!in_array($effect->status, ['accepte', 'remis_banque'])) {
            throw new \RuntimeException('L\'effet doit être accepté ou remis en banque pour être encaissé.');
        }
        $effect->update([
            'status'       => 'encaisse',
            'payment_date' => $paymentDate,
        ]);
        return $effect->fresh();
    }

    public function reject(CommercialEffect $effect, string $reason): CommercialEffect
    {
        $effect->update([
            'status'           => 'rejete',
            'rejection_reason' => $reason,
        ]);
        return $effect->fresh();
    }

    public function protest(CommercialEffect $effect, string $reason): CommercialEffect
    {
        $effect->update([
            'status'           => 'proteste',
            'rejection_reason' => $reason,
        ]);
        return $effect->fresh();
    }

    public function cancel(CommercialEffect $effect): CommercialEffect
    {
        if (!$effect->isEditable()) {
            throw new \RuntimeException('Cet effet ne peut plus être annulé.');
        }
        $effect->update(['status' => 'annule']);
        return $effect->fresh();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Effects due soon (for dashboard alerts)
    // ─────────────────────────────────────────────────────────────────────────
    public function getUpcomingDue(int $days = 30): \Illuminate\Support\Collection
    {
        return CommercialEffect::with(['client', 'supplier'])
            ->whereIn('status', ['en_attente', 'accepte', 'remis_banque'])
            ->whereNotNull('due_date')
            ->where('due_date', '<=', now()->addDays($days))
            ->orderBy('due_date')
            ->get();
    }
}
