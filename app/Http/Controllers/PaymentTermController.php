<?php

namespace App\Http\Controllers;

use App\Models\PaymentTerm;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PaymentTermController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:settings.manage')->except(['index']);
    }

    public function index(): View
    {
        $paymentTerms = PaymentTerm::orderBy('days')->orderBy('name')->get();
        return view('settings.payment-terms', compact('paymentTerms'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'            => ['required', 'string', 'max:100', 'unique:payment_terms,name'],
            'days'            => ['required', 'integer', 'min:0', 'max:365'],
            'end_of_month'    => ['boolean'],
            'additional_days' => ['integer', 'min:0', 'max:60'],
            'is_active'       => ['boolean'],
        ]);

        PaymentTerm::create([
            ...$data,
            'end_of_month'    => $request->boolean('end_of_month'),
            'additional_days' => $request->integer('additional_days', 0),
            'is_active'       => $request->boolean('is_active', true),
        ]);

        return back()->with('success', "Condition « {$data['name']} » créée.");
    }

    public function update(Request $request, PaymentTerm $paymentTerm): RedirectResponse
    {
        $data = $request->validate([
            'name'            => ['required', 'string', 'max:100', 'unique:payment_terms,name,' . $paymentTerm->id],
            'days'            => ['required', 'integer', 'min:0', 'max:365'],
            'end_of_month'    => ['boolean'],
            'additional_days' => ['integer', 'min:0', 'max:60'],
            'is_active'       => ['boolean'],
        ]);

        $paymentTerm->update([
            ...$data,
            'end_of_month'    => $request->boolean('end_of_month'),
            'additional_days' => $request->integer('additional_days', 0),
            'is_active'       => $request->boolean('is_active'),
        ]);

        return back()->with('success', "Condition « {$paymentTerm->name} » mise à jour.");
    }

    public function destroy(PaymentTerm $paymentTerm): RedirectResponse
    {
        if ($paymentTerm->invoices()->exists() || $paymentTerm->supplierInvoices()->exists()) {
            return back()->with('error', 'Cette condition est utilisée par des factures et ne peut pas être supprimée.');
        }

        $paymentTerm->delete();
        return back()->with('success', "Condition « {$paymentTerm->name} » supprimée.");
    }
}
