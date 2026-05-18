<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Compte de Résultat — {{ now()->format('d/m/Y') }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 9px; color: #1f2937; background: #fff; }

        .header { background: #1e3a5f; color: #fff; padding: 14px 20px; margin-bottom: 12px; }
        .header h1 { font-size: 16px; font-weight: bold; }
        .header .sub { font-size: 9px; color: #93c5fd; margin-top: 2px; }
        .header .meta { font-size: 8px; color: #bfdbfe; margin-top: 4px; }

        .columns { display: table; width: 100%; border-collapse: collapse; }
        .col { display: table-cell; width: 50%; vertical-align: top; padding: 0 5px; }
        .col:first-child { padding-left: 0; }
        .col:last-child  { padding-right: 0; border-left: 1px solid #e5e7eb; padding-left: 8px; }

        h2 { font-size: 11px; font-weight: bold; border-bottom: 2px solid; padding-bottom: 4px; margin-bottom: 6px; }
        h2.charges  { color: #991b1b; border-color: #fca5a5; }
        h2.produits { color: #065f46; border-color: #6ee7b7; }

        .section { margin-bottom: 10px; }
        .section-title { font-size: 8px; font-weight: bold; text-transform: uppercase; color: #6b7280; background: #f3f4f6; padding: 3px 5px; margin-bottom: 2px; }
        table.acct { width: 100%; border-collapse: collapse; }
        table.acct tr td { padding: 2px 4px; font-size: 8px; }
        table.acct tr:nth-child(even) td { background: #f9fafb; }
        table.acct td.code { color: #6b7280; width: 50px; font-family: monospace; }
        table.acct td.name { color: #374151; }
        table.acct td.amount { text-align: right; font-weight: 600; color: #111827; white-space: nowrap; width: 80px; }
        .section-total-charges  { display: flex; justify-content: space-between; padding: 3px 4px; font-size: 8px; font-weight: bold; background: #fee2e2; border-top: 1px solid #fca5a5; }
        .section-total-produits { display: flex; justify-content: space-between; padding: 3px 4px; font-size: 8px; font-weight: bold; background: #d1fae5; border-top: 1px solid #6ee7b7; }

        .grand-total { display: table; width: 100%; border-collapse: collapse; margin-top: 14px; }
        .grand-total td { display: table-cell; text-align: center; padding: 8px 4px; }
        .box { border-radius: 4px; padding: 8px; }
        .box-charges  { background: #fef2f2; border: 1px solid #fca5a5; }
        .box-produits { background: #f0fdf4; border: 1px solid #86efac; }
        .box-resultat-pos { background: #eff6ff; border: 2px solid #3b82f6; }
        .box-resultat-neg { background: #fef2f2; border: 2px solid #ef4444; }
        .box .label  { font-size: 7px; text-transform: uppercase; font-weight: bold; color: #6b7280; }
        .box .amount { font-size: 13px; font-weight: bold; margin-top: 2px; }
        .box .currency { font-size: 7px; color: #9ca3af; }

        .footer { border-top: 1px solid #e5e7eb; padding-top: 6px; margin-top: 14px; font-size: 7px; color: #9ca3af; text-align: center; }
    </style>
</head>
<body>
@include('pdf-header')

<div class="header">
    <h1>COMPTE DE RÉSULTAT SYSCOHADA</h1>
    <div class="sub">{{ $company?->trade_name ?? $company?->name ?? 'A3 ERP' }}</div>
    <div class="meta">
        Imprimé le {{ $printedAt }}
        @if(isset($selectedFy) && $selectedFy)
        — Exercice {{ $selectedFy->label }} ({{ $selectedFy->starts_at->format('d/m/Y') }} au {{ $selectedFy->ends_at->format('d/m/Y') }})
        @else
        — Soldes cumulés des comptes de charges (6) et produits (7)
        @endif
    </div>
</div>

<div class="columns">
    {{-- CHARGES --}}
    <div class="col">
        <h2 class="charges">CHARGES (Classe 6)</h2>
        @foreach($charges as $title => $accounts)
            @if($accounts->isNotEmpty())
            <div class="section">
                <div class="section-title">{{ $title }}</div>
                <table class="acct">
                    @foreach($accounts as $a)
                    <tr>
                        <td class="code">{{ $a->code }}</td>
                        <td class="name">{{ $a->name }}</td>
                        <td class="amount">{{ number_format($a->net, 0, ',', ' ') }}</td>
                    </tr>
                    @endforeach
                </table>
                <div class="section-total-charges">
                    <span>Sous-total</span>
                    <span>{{ number_format($accounts->sum('net'), 0, ',', ' ') }} FCFA</span>
                </div>
            </div>
            @endif
        @endforeach
    </div>

    {{-- PRODUITS --}}
    <div class="col">
        <h2 class="produits">PRODUITS (Classe 7)</h2>
        @foreach($produits as $title => $accounts)
            @if($accounts->isNotEmpty())
            <div class="section">
                <div class="section-title">{{ $title }}</div>
                <table class="acct">
                    @foreach($accounts as $a)
                    <tr>
                        <td class="code">{{ $a->code }}</td>
                        <td class="name">{{ $a->name }}</td>
                        <td class="amount">{{ number_format($a->net, 0, ',', ' ') }}</td>
                    </tr>
                    @endforeach
                </table>
                <div class="section-total-produits">
                    <span>Sous-total</span>
                    <span>{{ number_format($accounts->sum('net'), 0, ',', ' ') }} FCFA</span>
                </div>
            </div>
            @endif
        @endforeach
    </div>
</div>

{{-- Résultat net --}}
<table class="grand-total">
    <tr>
        <td style="width:33%"><div class="box box-charges">
            <div class="label" style="color:#991b1b;">Total Charges</div>
            <div class="amount" style="color:#991b1b;">{{ number_format($totalCharges, 0, ',', ' ') }}</div>
            <div class="currency">FCFA</div>
        </div></td>
        <td style="width:34%"><div class="box {{ $resultat >= 0 ? 'box-resultat-pos' : 'box-resultat-neg' }}">
            <div class="label" style="color:{{ $resultat >= 0 ? '#1d4ed8' : '#b91c1c' }};">
                {{ $resultat >= 0 ? 'Résultat Net (bénéfice)' : 'Résultat Net (déficit)' }}
            </div>
            <div class="amount" style="color:{{ $resultat >= 0 ? '#1d4ed8' : '#b91c1c' }};">
                {{ $resultat >= 0 ? '+' : '-' }}{{ number_format(abs($resultat), 0, ',', ' ') }}
            </div>
            <div class="currency">FCFA</div>
        </div></td>
        <td style="width:33%"><div class="box box-produits">
            <div class="label" style="color:#065f46;">Total Produits</div>
            <div class="amount" style="color:#065f46;">{{ number_format($totalProduits, 0, ',', ' ') }}</div>
            <div class="currency">FCFA</div>
        </div></td>
    </tr>
</table>

<div class="footer">
    Document généré automatiquement par {{ $company?->trade_name ?? $company?->name ?? 'A3 ERP' }} — Conforme SYSCOHADA révisé
</div>

</body>
</html>
