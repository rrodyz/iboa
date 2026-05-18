<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8"/>
<style>
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family: DejaVu Sans, sans-serif; font-size: 9pt; color: #111827; }
.page { padding: 14mm 12mm; }

.header { background:#7C2D12; color:#fff; padding:8px 12px; margin-bottom:3px; }
.header-top { display:table; width:100%; }
.header-left { display:table-cell; font-size:11pt; font-weight:bold; width:30%; }
.header-center { display:table-cell; text-align:center; font-size:11pt; font-weight:bold; width:40%; }
.header-right { display:table-cell; text-align:right; font-size:9pt; width:30%; }
.subheader { background:#B45309; color:#e5e7eb; padding:4px 12px; font-size:8pt; font-style:italic; display:table; width:100%; margin-bottom:12px; }
.subheader-left { display:table-cell; }
.subheader-right { display:table-cell; text-align:right; color:#FDE68A; }

.supplier-block { display:table; width:100%; margin-bottom:10px; }
.supplier-info { display:table-cell; width:60%; background:#FEF3C7; border:1px solid #F59E0B; padding:6px 10px; border-radius:3px; }
.supplier-solde { display:table-cell; width:38%; text-align:right; background:#FEF3C7; border:1px solid #F59E0B; padding:6px 10px; border-radius:3px; vertical-align:middle; margin-left:4px; padding-left:4px; }
.supplier-name { font-size:10pt; font-weight:bold; color:#7C2D12; }
.supplier-label { font-size:7pt; color:#92400E; text-transform:uppercase; letter-spacing:.05em; }
.solde-value { font-size:12pt; font-weight:bold; color:#7C2D12; }

table { width:100%; border-collapse:collapse; font-size:8.5pt; margin-top:4px; }
thead tr { background:#FEF3C7; }
thead th { padding:5px 6px; font-size:7.5pt; font-weight:bold; color:#7C2D12; border-top:1.5px solid #F97316; border-bottom:1.5px solid #F97316; }
th.left, td.left { text-align:left; }
th.center, td.center { text-align:center; }
th.right, td.right { text-align:right; }
tbody tr { border-bottom:0.5px solid #e5e7eb; }
tbody tr:nth-child(even) { background:#fafafa; }
tbody tr.ouv { background:#FEF9C3; font-style:italic; color:#92400E; }
td { padding:4px 6px; }
.num { font-family: DejaVu Sans Mono, monospace; }
.badge-facture { background:#FEE2E2; color:#B91C1C; padding:1px 4px; border-radius:2px; font-size:7pt; }
.badge-retour  { background:#FFEDD5; color:#C2410C; padding:1px 4px; border-radius:2px; font-size:7pt; }
.badge-paiement{ background:#DCFCE7; color:#15803D; padding:1px 4px; border-radius:2px; font-size:7pt; }
tfoot tr { background:#7C2D12; color:#fff; font-weight:bold; }
tfoot td { padding:5px 6px; border-top:2px solid #F97316; }
</style>
</head>
<body>
<div class="page">
@include('pdf-header')

<div class="header">
    <div class="header-top">
        <div class="header-left">{{ $company?->name }}</div>
        <div class="header-center">RELEVÉ FOURNISSEUR</div>
        <div class="header-right">
            @if($dateFrom && $dateTo)
                Du {{ \Carbon\Carbon::parse($dateFrom)->format('d/m/Y') }} au {{ \Carbon\Carbon::parse($dateTo)->format('d/m/Y') }}
            @endif
        </div>
    </div>
</div>
<div class="subheader">
    <div class="subheader-left">{{ $supplier?->name ?? '—' }}</div>
    <div class="subheader-right">Édition du {{ now()->format('d/m/Y') }}</div>
</div>

<div class="supplier-block">
    <div class="supplier-info">
        <div class="supplier-label">Fournisseur</div>
        <div class="supplier-name">{{ $supplier?->name ?? '—' }}</div>
        @if($supplier?->email)<div style="font-size:8pt;color:#6b7280;margin-top:2px;">{{ $supplier->email }}</div>@endif
    </div>
    <div style="display:table-cell;width:2%;"></div>
    <div class="supplier-solde">
        <div class="supplier-label">Solde d'ouverture</div>
        <div class="solde-value">{{ number_format(abs($soldeOuv), 0, ',', ' ') }} FCFA</div>
        @if($dateFrom)<div style="font-size:7pt;color:#6b7280;">Avant le {{ \Carbon\Carbon::parse($dateFrom)->format('d/m/Y') }}</div>@endif
    </div>
</div>

<table>
    <thead>
        <tr>
            <th class="left"   style="width:10%">Date</th>
            <th class="left"   style="width:18%">Référence</th>
            <th class="center" style="width:12%">Type</th>
            <th class="left"   style="width:10%">Échéance</th>
            <th class="right"  style="width:16%">Débit (FCFA)</th>
            <th class="right"  style="width:16%">Crédit (FCFA)</th>
            <th class="right"  style="width:16%">Solde (FCFA)</th>
        </tr>
    </thead>
    <tbody>
        @if($soldeOuv != 0)
        <tr class="ouv">
            <td colspan="3">Report antérieur</td>
            <td></td><td></td><td></td>
            <td class="right num">{{ number_format(abs($soldeOuv), 0, ',', ' ') }}</td>
        </tr>
        @endif
        @foreach($lines as $line)
        <tr>
            <td>{{ ($line['date'] instanceof \Carbon\Carbon ? $line['date'] : \Carbon\Carbon::parse($line['date']))->format('d/m/Y') }}</td>
            <td>{{ $line['reference'] }}</td>
            <td class="center">
                @if($line['type'] === 'facture')
                    <span class="badge-facture">Facture</span>
                @elseif($line['type'] === 'retour')
                    <span class="badge-retour">Retour</span>
                @else
                    <span class="badge-paiement">Paiement</span>
                @endif
            </td>
            <td>{{ $line['echeance'] ? (\Carbon\Carbon::parse($line['echeance'])->format('d/m/Y')) : '—' }}</td>
            <td class="right num">{{ $line['debit'] > 0  ? number_format($line['debit'],  0, ',', ' ') : '—' }}</td>
            <td class="right num">{{ $line['credit'] > 0 ? number_format($line['credit'], 0, ',', ' ') : '—' }}</td>
            <td class="right num">{{ number_format(abs($line['solde']), 0, ',', ' ') }}</td>
        </tr>
        @endforeach
    </tbody>
    @if($lines->count())
    <tfoot>
        <tr>
            <td colspan="4">Solde de clôture</td>
            <td class="right num">{{ number_format($lines->sum('debit'),  0, ',', ' ') }}</td>
            <td class="right num">{{ number_format($lines->sum('credit'), 0, ',', ' ') }}</td>
            <td class="right num">{{ number_format(abs($lines->last()['solde']), 0, ',', ' ') }}</td>
        </tr>
    </tfoot>
    @endif
</table>

</div>
</body>
</html>
