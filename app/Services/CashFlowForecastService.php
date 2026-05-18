<?php

namespace App\Services;

use App\Models\CashAccount;
use App\Models\CashFlowForecast;
use App\Models\CashFlowForecastLine;
use App\Models\Company;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CashFlowForecastService
{
    public function __construct(private DocumentSequenceService $seq) {}

    // ─────────────────────────────────────────────────────────────────────────
    // Create
    // ─────────────────────────────────────────────────────────────────────────
    public function create(array $data): CashFlowForecast
    {
        return DB::transaction(function () use ($data) {
            $lines = $data['lines'] ?? [];
            unset($data['lines']);

            $company            = Company::firstOrFail();
            $data['company_id'] = $company->id;
            $data['number']     = $this->seq->nextNumber($company, 'prevision_tresorerie');
            $data['created_by'] = Auth::id();
            $data['status']     = 'brouillon';

            // Auto-set opening balance from all active cash accounts if not provided
            if (empty($data['opening_balance'])) {
                $data['opening_balance'] = (int) CashAccount::where('is_active', true)->sum('current_balance');
            }

            $forecast = CashFlowForecast::create($data);

            foreach ($lines as $i => $line) {
                if (empty($line['label']) || empty($line['forecast_amount'])) continue;
                $forecast->lines()->create([
                    'category'        => $line['category'],
                    'label'           => $line['label'],
                    'is_inflow'       => in_array($line['category'], CashFlowForecastLine::inflowCategories()),
                    'forecast_amount' => (int) ($line['forecast_amount'] ?? 0),
                    'actual_amount'   => (int) ($line['actual_amount'] ?? 0),
                    'sort_order'      => $i,
                ]);
            }

            $this->recalculate($forecast);
            return $forecast->fresh();
        });
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Update actuals (fill in real amounts after period)
    // ─────────────────────────────────────────────────────────────────────────
    public function updateActuals(CashFlowForecast $forecast, array $actuals): CashFlowForecast
    {
        return DB::transaction(function () use ($forecast, $actuals) {
            foreach ($actuals as $lineId => $amount) {
                $forecast->lines()->where('id', $lineId)->update(['actual_amount' => (int) $amount]);
            }

            // Pull actual data from CashTransactions for the period
            $this->syncActualsFromTransactions($forecast);
            $this->recalculate($forecast);
            return $forecast->fresh();
        });
    }

    public function validateForecast(CashFlowForecast $forecast): CashFlowForecast
    {
        if ($forecast->status !== 'brouillon') {
            throw new \RuntimeException('Seules les prévisions en brouillon peuvent être validées.');
        }
        $forecast->update(['status' => 'valide']);
        return $forecast->fresh();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Auto-populate actuals from CashTransaction records
    // ─────────────────────────────────────────────────────────────────────────
    private function syncActualsFromTransactions(CashFlowForecast $forecast): void
    {
        $from = $forecast->period_start->toDateString();
        $to   = $forecast->period_end->toDateString();

        $actualIn  = (int) \App\Models\CashTransaction::whereBetween('transaction_date', [$from, $to])
            ->where('type', 'credit')->sum('amount');
        $actualOut = (int) \App\Models\CashTransaction::whereBetween('transaction_date', [$from, $to])
            ->where('type', 'debit')->sum('amount');

        $forecast->update([
            'actual_inflows'  => $actualIn,
            'actual_outflows' => $actualOut,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    private function recalculate(CashFlowForecast $forecast): void
    {
        $forecast->load('lines');
        $totalIn  = (int) $forecast->lines->where('is_inflow', true)->sum('forecast_amount');
        $totalOut = (int) $forecast->lines->where('is_inflow', false)->sum('forecast_amount');
        $net      = $totalIn - $totalOut;

        $forecast->update([
            'total_inflows'            => $totalIn,
            'total_outflows'           => $totalOut,
            'net_flow'                 => $net,
            'closing_balance_forecast' => $forecast->opening_balance + $net,
        ]);
    }
}
