<div class="tbl-scroll">
    <table class="tbl">
        <thead>
            <tr>
                <th class="text-left">Date</th>
                <th class="text-left">Journal</th>
                <th class="text-left">N° pièce</th>
                <th class="text-left">Libellé</th>
                <th class="text-right">Débit</th>
                <th class="text-right">Crédit</th>
                <th class="text-right">Solde cumulé</th>
            </tr>
        </thead>
        <tbody>
            @php $running = 0; @endphp
            @forelse($lines as $line)
            @php $running += $line->debit - $line->credit; @endphp
            <tr>
                <td class="text-gray-600 whitespace-nowrap">
                    {{ $line->journalEntry?->entry_date?->format('d/m/Y') ?? '—' }}
                </td>
                <td class="whitespace-nowrap">
                    <span class="font-mono text-xs bg-gray-100 text-gray-700 px-1.5 py-0.5 rounded">
                        {{ $line->journalEntry?->journalType?->code ?? '—' }}
                    </span>
                </td>
                <td class="whitespace-nowrap">
                    @if($line->journalEntry)
                    <a href="{{ route('comptabilite.journaux.show', $line->journal_entry_id) }}"
                       class="font-mono text-violet-600 hover:text-violet-800 hover:underline text-xs">
                        {{ $line->journalEntry->number ?? '—' }}
                    </a>
                    @else
                    <span class="text-gray-400 text-xs">—</span>
                    @endif
                </td>
                <td class="text-gray-700 max-w-xs truncate" title="{{ $line->label ?: $line->journalEntry?->description }}">
                    {{ $line->label ?: $line->journalEntry?->description ?: '—' }}
                </td>
                <td class="text-right tabular-nums {{ $line->debit > 0 ? 'font-semibold text-gray-900' : 'text-gray-300' }}">
                    {{ $line->debit > 0 ? number_format($line->debit, 0, ',', ' ') : '—' }}
                </td>
                <td class="text-right tabular-nums {{ $line->credit > 0 ? 'font-semibold text-gray-900' : 'text-gray-300' }}">
                    {{ $line->credit > 0 ? number_format($line->credit, 0, ',', ' ') : '—' }}
                </td>
                <td class="text-right tabular-nums whitespace-nowrap
                    {{ $running > 0 ? 'text-blue-700' : ($running < 0 ? 'text-red-700' : 'text-gray-400') }}">
                    @if($running == 0)
                        —
                    @else
                        {{ number_format(abs($running), 0, ',', ' ') }}
                        <span class="text-xs font-normal ml-0.5">{{ $running > 0 ? 'D' : 'C' }}</span>
                    @endif
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="7" class="px-4 py-8 text-center text-gray-400 text-sm">Aucun mouvement.</td>
            </tr>
            @endforelse
        </tbody>

        {{-- Totals footer --}}
        @if($lines->isNotEmpty())
        @php
            $footDebit   = $lines->sum('debit');
            $footCredit  = $lines->sum('credit');
            $footBalance = $footDebit - $footCredit;
        @endphp
        <tfoot>
            <tr class="border-t-2 border-gray-300 bg-gray-50 font-bold">
                <td colspan="4" class="text-xs text-gray-600 uppercase tracking-wider">
                    Total — {{ $lines->count() }} ligne(s)
                </td>
                <td class="text-right tabular-nums text-blue-700">
                    {{ number_format($footDebit, 0, ',', ' ') }}
                </td>
                <td class="text-right tabular-nums text-red-700">
                    {{ number_format($footCredit, 0, ',', ' ') }}
                </td>
                <td class="text-right tabular-nums whitespace-nowrap
                    {{ $footBalance > 0 ? 'text-blue-700' : ($footBalance < 0 ? 'text-red-700' : 'text-gray-400') }}">
                    @if($footBalance == 0)
                        <span class="text-gray-400 font-normal">Équilibré</span>
                    @else
                        {{ number_format(abs($footBalance), 0, ',', ' ') }}
                        <span class="text-xs font-normal ml-0.5">{{ $footBalance > 0 ? 'D' : 'C' }}</span>
                    @endif
                </td>
            </tr>
        </tfoot>
        @endif
    </table>
</div>
