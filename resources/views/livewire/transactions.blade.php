<div>
    <h1>Transactions</h1>
    <p class="sub">Parcourez, filtrez et classez. Astuce : cochez des lignes (<em>Maj+clic</em> pour sélectionner une plage), puis « Appliquer à la sélection ».</p>

    {{-- Filtres --}}
    <div class="filters">
        <input class="grow" type="search" placeholder="Rechercher commerçant / description…"
               wire:model.live.debounce.350ms="search">

        <select wire:model.live="category">
            <option value="all">Toutes les catégories</option>
            <option value="unclassified">⚠ Non classées uniquement</option>
            @foreach ($cats as $c)
                <option value="{{ $c['id'] }}">{!! str_repeat('&nbsp;&nbsp;', $c['depth']) !!}{{ $c['title'] }}</option>
            @endforeach
        </select>

        <select wire:model.live="direction">
            <option value="all">Tout</option>
            <option value="debit">Dépenses</option>
            <option value="credit">Revenus</option>
        </select>

        <select wire:model.live="year">
            <option value="all">Toutes les années</option>
            @foreach (['2026','2025','2024'] as $y)
                <option value="{{ $y }}">{{ $y }}</option>
            @endforeach
        </select>

        <input type="number" step="0.01" min="0" style="width:120px"
               placeholder="Montant ≥" wire:model.live.debounce.400ms="amountMin"
               title="Montant minimum (valeur absolue, CHF)">
        <input type="number" step="0.01" min="0" style="width:120px"
               placeholder="Montant ≤" wire:model.live.debounce.400ms="amountMax"
               title="Montant maximum (valeur absolue, CHF)">
    </div>

    {{-- Barre d'actions --}}
    <div class="bulkbar">
        <span><strong>{{ number_format($matchCount, 0, ',', ' ') }}</strong> <span class="muted">correspondent au filtre</span></span>
        @if (count($selected))
            <span class="muted">·</span>
            <span><strong>{{ count($selected) }}</strong> <span class="muted">sélectionnée(s)</span></span>
            <button class="btn sm" wire:click="clearSelection">Tout désélectionner</button>
        @elseif ($matchCount > 0)
            <span class="muted">·</span>
            <button class="btn sm" wire:click="selectAllMatching">Sélectionner les {{ number_format($matchCount, 0, ',', ' ') }}</button>
        @endif

        <span class="spacer" style="flex:1"></span>

        <select wire:model="bulkCategory" class="cat-select">
            <option value="">Définir la catégorie…</option>
            <option value="__clear__">— Effacer la catégorie —</option>
            @foreach ($cats as $c)
                <option value="{{ $c['id'] }}">{!! str_repeat('&nbsp;&nbsp;', $c['depth']) !!}{{ $c['title'] }}</option>
            @endforeach
        </select>

        <button class="btn primary sm" wire:click="assignSelected" @disabled(! count($selected))
                title="Attribuer la catégorie aux lignes cochées">
            Appliquer à la sélection ({{ count($selected) }})
        </button>
        <button class="btn sm" wire:click="bulkAssign"
                wire:confirm="Appliquer cette catégorie aux {{ $matchCount }} transactions correspondant au filtre ?">
            Appliquer à toutes les correspondances
        </button>
        <button class="btn sm" wire:click="bulkApplyRules"
                wire:confirm="Relancer les règles auto sur les lignes correspondantes (non manuelles) ?">
            Règles auto
        </button>
    </div>

    {{-- Tableau --}}
    @php
        $pageIds = $rows->getCollection()->map(fn ($r) => (string) $r->id)->all();
        $allPageSelected = $pageIds && empty(array_diff($pageIds, $selected));
    @endphp
    <div class="panel" style="padding:0; overflow:hidden;">
        <table>
            <thead>
                <tr>
                    <th style="width:34px; text-align:center;">
                        <input type="checkbox" wire:click="toggleSelectPage" @checked($allPageSelected)
                               title="Tout sélectionner sur cette page">
                    </th>
                    <th style="width:90px">Date</th>
                    <th>Commerçant</th>
                    <th>Type</th>
                    <th class="num" style="width:120px">Montant</th>
                    <th style="width:230px">Catégorie</th>
                    <th style="width:90px">Épargne</th>
                </tr>
            </thead>
            <tbody x-data="{
                last: null,
                toggle(e) {
                    const boxes = [...this.$root.querySelectorAll('input[type=checkbox][data-row]')];
                    const ids = boxes.map(cb => cb.value);
                    const idx = boxes.indexOf(e.target);
                    const checked = e.target.checked;
                    const targets = (e.shiftKey && this.last !== null && this.last < ids.length)
                        ? ids.slice(Math.min(this.last, idx), Math.max(this.last, idx) + 1)
                        : [e.target.value];
                    const next = new Set(this.$wire.get('selected'));
                    targets.forEach(id => checked ? next.add(id) : next.delete(id));
                    boxes.forEach(cb => { if (targets.includes(cb.value)) cb.checked = checked; });
                    if (e.shiftKey) window.getSelection().removeAllRanges(); // drop accidental text highlight
                    this.$wire.set('selected', [...next]);
                    this.last = idx;
                }
            }">
                @forelse ($rows as $row)
                    <tr wire:key="txn-{{ $row->id }}" class="{{ in_array((string) $row->id, $selected) ? 'row-selected' : '' }}">
                        <td style="text-align:center;">
                            <input type="checkbox" data-row value="{{ $row->id }}"
                                   @checked(in_array((string) $row->id, $selected))
                                   @click="toggle($event)">
                        </td>
                        <td class="small muted">{{ $row->date->format('d.m.Y') }}</td>
                        <td>
                            <div>{{ \Illuminate\Support\Str::limit(str_replace(';', ' · ', $row->merchant ?? '—'), 48) }}</div>
                            @if ($row->details)
                                <div class="small muted">{{ \Illuminate\Support\Str::limit($row->details, 60) }}</div>
                            @endif
                        </td>
                        <td class="small muted">{{ \Illuminate\Support\Str::limit($row->type ?? '', 22) }}</td>
                        <td class="num {{ $row->amount < 0 ? 'neg' : 'pos' }}">
                            {{ number_format($row->amount, 2, ',', ' ') }}
                        </td>
                        <td>
                            <select class="cat-select"
                                    wire:change="assign({{ $row->id }}, $event.target.value)">
                                <option value="" @selected(! $row->category_id)>—</option>
                                @foreach ($cats as $c)
                                    <option value="{{ $c['id'] }}" @selected($row->category_id === $c['id'])>
                                        {!! str_repeat('&nbsp;&nbsp;', $c['depth']) !!}{{ $c['title'] }}
                                    </option>
                                @endforeach
                            </select>
                            @if ($row->source === 'rule')
                                <span class="small muted" title="Classé automatiquement par une règle">· auto</span>
                            @elseif ($row->source === 'manual')
                                <span class="small muted" title="Défini manuellement">· manuel</span>
                            @endif
                        </td>
                        <td>
                            <button class="btn sm {{ $row->is_savings ? 'primary' : '' }}"
                                    wire:click="toggleSavings({{ $row->id }})">
                                {{ $row->is_savings ? '✓ Épargne' : 'Marquer' }}
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="muted" style="padding:28px; text-align:center;">Aucune transaction ne correspond.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Pagination --}}
    <div style="display:flex; align-items:center; justify-content:space-between; margin-top:14px;">
        <span class="muted small">
            Page {{ $rows->currentPage() }} sur {{ max($rows->lastPage(), 1) }} · {{ number_format($rows->total(), 0, ',', ' ') }} lignes
        </span>
        <div style="display:flex; gap:8px;">
            <button class="btn sm" wire:click="previousPage" @disabled($rows->onFirstPage())>← Préc.</button>
            <button class="btn sm" wire:click="nextPage" @disabled(! $rows->hasMorePages())>Suiv. →</button>
        </div>
    </div>
</div>
