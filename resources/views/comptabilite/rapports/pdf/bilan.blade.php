<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Bilan SYSCOHADA — {{ now()->format('d/m/Y') }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 9px; color: #1f2937; background: #fff; }

        .header { background: #1e3a5f; color: #fff; padding: 14px 20px; margin-bottom: 12px; }
        .header h1 { font-size: 16px; font-weight: bold; }
        .header .sub { font-size: 9px; color: #93c5fd; margin-top: 2px; }
        .header .meta { font-size: 8px; color: #bfdbfe; margin-top: 4px; }

        .totals-bar { display: table; width: 100%; margin-bottom: 12px; border-collapse: collapse; }
        .totals-bar td { display: table-cell; width: 33%; text-align: center; padding: 8px 4px; }
        .totals-bar .box-actif  { background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 4px; }
        .totals-bar .box-check  { background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 4px; }
        .totals-bar .box-passif { background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 4px; }
        .totals-bar .label { font-size: 7px; text-transform: uppercase; font-weight: bold; color: #6b7280; }
        .totals-bar .amount { font-size: 13px; font-weight: bold; color: #1e3a5f; margin-top: 2px; }
        .totals-bar .currency { font-size: 7px; color: #9ca3af; }

        .columns { display: table; width: 100%; border-collapse: collapse; }
        .col { display: table-cell; width: 50%; vertical-align: top; padding: 0 5px; }
        .col:first-child { padding-left: 0; }
        .col:last-child  { padding-right: 0; border-left: 1px solid #e5e7eb; padding-left: 8px; }

        h2 { font-size: 11px; font-weight: bold; color: #1e3a5f; border-bottom: 2px solid #1e3a5f; padding-bottom: 4px; margin-bottom: 6px; }

        .section { margin-bottom: 10px; }
        .section-title { font-size: 8px; font-weight: bold; text-transform: uppercase; color: #6b7280; background: #f3f4f6; padding: 3px 5px; margin-bottom: 2px; }
        table.acct { width: 100%; border-collapse: collapse; }
        table.acct tr td { padding: 2px 4px; font-size: 8px; }
        table.acct tr:nth-child(even) td { background: #f9fafb; }
        table.acct td.code { color: #6b7280; width: 50px; font-family: monospace; }
        table.acct td.name { color: #374151; }
        table.acct td.amount { text-align: right; font-weight: 600; color: #111827; white-space: nowrap; width: 80px; }
        .section-total { display: flex; justify-content: space-between; padding: 3px 4px; font-size: 8px; font-weight: bold; background: #e0e7ff; border-top: 1px solid #c7d2fe; }

        .footer { border-top: 1px solid #e5e7eb; padding-top: 6px; margin-top: 14px; font-size: 7px; color: #9ca3af; text-align: center; }
    </style>
</head>
<body>
@include('pdf-header')

<div class="header">
    <h1>BILAN SYSCOHADA</h1>
    <div class="sub">{{ $company?->trade_name ?? $company?->name ?? 'A3 ERP' }}</div>
    <div class="meta">
        Imprimé le {{ $printedAt }}
        @if(isset($selectedFy) && $selectedFy)
        — Exercice {{ $selectedFy->label }} (au {{ $selectedFy->ends_at->format('d/m/Y') }})
        @else
        — Soldes cumulés des comptes actifs
        @endif
    </div>
</div>

{{-- Totals bar --}}
<table class="totals-bar">
    <tr>
        <td><div class="box-actif">
            <div class="label">Total Actif</div>
            <div class="amount">{{ number_format($totalActif, 0, ',', ' ') }}</div>
            <div class="currency">FCFA</div>
        </div></td>
        <td><div class="box-check">
            @if(abs($totalActif - $totalPassif) < 1)
                <div class="label" style="color:#15803d;">Bilan équilibré</div>
                <div class="amount" style="color:#15803d;">✓</div>
            @else
                <div class="label" style="color:#b45309;">Écart</div>
                <div class="amount" style="color:#b45309; font-size:10px;">{{ number_format(abs($totalActif - $totalPassif), 0, ',', ' ') }}</div>
            @endif
        </div></td>
        <td><div class="box-passif">
            <div class="label">Total Passif</div>
            <div class="amount">{{ number_format($totalPassif, 0, ',', ' ') }}</div>
            <div class="currency">FCFA</div>
        </div></td>
    </tr>
</table>

{{-- Two-column layout --}}
<div class="columns">
    {{-- ACTIF --}}
    <div class="col">
        <h2>ACTIF</h2>
        @foreach($actif as $title => $accounts)
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
                <div class="section-total">
                    <span>Sous-total {{ $title }}</span>
                    <span>{{ number_format($accounts->sum('net'), 0, ',', ' ') }} FCFA</span>
                </div>
            </div>
            @endif
        @endforeach
    </div>

    {{-- PASSIF --}}
    <div class="col">
        <h2>PASSIF</h2>
        @foreach($passif as $title => $accounts)
            @if($accounts->isNotEmpty())
            <div class="section">
                <div class="section-title">{{ $title }}</div>
                <table class="acct">
                    @foreach($accounts as $a)
                    <tr>
                        <td class="code">{{ $a->code }}</td>
                        <td class="name">{{ $a->name }}</td>
                        <td class="amount">{{ number_format(abs($a->net), 0, ',', ' ') }}</td>
                    </tr>
                    @endforeach
                </table>
                <div class="section-total">
                    <span>Sous-total {{ $title }}</span>
                    <span>{{ number_format($accounts->sum(fn($a) => abs($a->net)), 0, ',', ' ') }} FCFA</span>
                </div>
            </div>
            @endif
        @endforeach
    </div>
</div>

<div class="footer">
    Document généré automatiquement par {{ $company?->trade_name ?? $company?->name ?? 'A3 ERP' }} — Conforme SYSCOHADA révisé
</div>

</body>
</html>
