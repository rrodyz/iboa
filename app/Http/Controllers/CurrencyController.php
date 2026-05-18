<?php

namespace App\Http\Controllers;

use App\Models\Currency;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class CurrencyController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:settings.manage')->except(['index']);
    }

    public function index(): View
    {
        $currencies = Currency::orderByDesc('is_default')->orderBy('code')->get();
        return view('settings.currencies', compact('currencies'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'code'                => ['required', 'string', 'size:3', 'unique:currencies,code'],
            'name'                => ['required', 'string', 'max:80'],
            'symbol'              => ['required', 'string', 'max:10'],
            'decimal_places'      => ['required', 'integer', 'min:0', 'max:4'],
            // nullable + max:1 : le middleware TrimStrings convertit " " en "", donc on accepte le vide
            // et on défaulte plus bas. Cela évite l'erreur "le champ est obligatoire" sur le séparateur espace.
            'thousands_separator' => ['nullable', 'string', 'max:1'],
            'decimal_separator'   => ['nullable', 'string', 'max:1'],
            'is_default'          => ['boolean'],
            'is_active'           => ['boolean'],
        ]);

        // Récupère les séparateurs depuis la requête brute (avant TrimStrings) si possible.
        // Fallback : " " (espace, standard typographique français) pour les milliers, "," pour les décimales.
        $rawSeparators = [
            'thousands_separator' => $request->input('thousands_separator', ' ') ?: ' ',
            'decimal_separator'   => $request->input('decimal_separator', ',') ?: ',',
        ];

        DB::transaction(function () use ($data, $request, $rawSeparators) {
            if ($request->boolean('is_default')) {
                Currency::where('is_default', true)->update(['is_default' => false]);
            }
            Currency::create([
                ...$data,
                ...$rawSeparators,
                'is_default' => $request->boolean('is_default'),
                'is_active'  => $request->boolean('is_active', true),
            ]);
        });

        return back()->with('success', "Devise « {$data['code']} » créée.");
    }

    public function update(Request $request, Currency $currency): RedirectResponse
    {
        $data = $request->validate([
            'name'                => ['required', 'string', 'max:80'],
            'symbol'              => ['required', 'string', 'max:10'],
            'decimal_places'      => ['required', 'integer', 'min:0', 'max:4'],
            // nullable + max:1 : le middleware TrimStrings convertit " " en "", donc on accepte le vide
            // et on défaulte plus bas. Cela évite l'erreur "le champ est obligatoire" sur le séparateur espace.
            'thousands_separator' => ['nullable', 'string', 'max:1'],
            'decimal_separator'   => ['nullable', 'string', 'max:1'],
            'is_active'           => ['boolean'],
        ]);

        $currency->update([
            ...$data,
            'thousands_separator' => $request->input('thousands_separator', ' ') ?: ' ',
            'decimal_separator'   => $request->input('decimal_separator', ',') ?: ',',
            'is_active'           => $request->boolean('is_active'),
        ]);

        return back()->with('success', "Devise « {$currency->code} » mise à jour.");
    }

    public function setDefault(Currency $currency): RedirectResponse
    {
        if (!$currency->is_active) {
            return back()->with('error', 'Impossible de définir une devise inactive comme devise par défaut.');
        }

        DB::transaction(function () use ($currency) {
            Currency::where('is_default', true)->update(['is_default' => false]);
            $currency->update(['is_default' => true]);
        });

        return back()->with('success', "« {$currency->code} » définie comme devise par défaut.");
    }

    public function destroy(Currency $currency): RedirectResponse
    {
        if ($currency->is_default) {
            return back()->with('error', 'Impossible de supprimer la devise par défaut.');
        }

        $currency->delete();
        return back()->with('success', "Devise supprimée.");
    }
}
