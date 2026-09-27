<div>
    <div class="head">
        <div>
            <h1>Transactions</h1>
            <p class="sub">Parcourez, filtrez et classez. Astuce : cochez des lignes (<em>Maj+clic</em> pour sélectionner une plage), puis « Appliquer à la sélection ».</p>
        </div>
    </div>

    {{-- Filtres --}}
    <div class="filters">
        <label class="grow">
            <x-icon name="search" />
            <input type="search" placeholder="Rechercher commerçant / description…" aria-label="Rechercher"
                   wire:model.live.debounce.350ms="search">
        </label>

        <select wire:model.live="category">
            <option value="all">Toutes les catégories</option>
            <option value="unclassified">Non classées uniquement</option>
            <option value="savings">Épargne uniquement</option>
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

        @if ($branchTitle)
            <span class="chip">{{ $branchTitle }} et sous-catégories
                <button wire:click="$set('branch', '')" title="Retirer ce filtre" aria-label="Retirer ce filtre"><x-icon name="close" size="14" /></button>
            </span>
        @endif
        @if ($monthTitle)
            <span class="chip neutral">{{ $monthTitle }}
                <button wire:click="$set('month', '')" title="Retirer ce filtre" aria-label="Retirer ce filtre"><x-icon name="close" size="14" /></button>
            </span>
        @endif
    </div>

    {{-- Barre d'actions --}}
    <div class="bulkbar">
        <span><span class="count">{{ number_format($matchCount, 0, ',', ' ') }}</span> <span class="muted">correspondent au filtre</span></span>
        @if (count($selected))
            <span class="sep"></span>
            <span><span class="count">{{ count($selected) }}</span> <span class="muted">sélectionnée(s)</span></span>
            <button class="btn sm ghost" wire:click="clearSelection">Tout désélectionner</button>
        @elseif ($matchCount > 0)
            <span class="sep"></span>
            <button class="btn sm ghost" wire:click="selectAllMatching">Sélectionner les {{ number_format($matchCount, 0, ',', ' ') }}</button>
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
        <button class="btn sm ghost" wire:click="bulkApplyRules"
                wire:confirm="Relancer les règles auto sur les lignes correspondantes (non manuelles) ?">
            Règles auto
        </button>
    </div>

    {{-- Tableau --}}
    @php
        $pageIds = $rows->getCollection()->map(fn ($r) => (string) $r->id)->all();
        $allPageSelected = $pageIds && empty(array_diff($pageIds, $selected));
    @endphp
    <div class="panel table-wrap" style="padding:0;">
        <table>
            <thead>
                <tr>
                    <th style="width:34px; text-align:center;">
                        <input type="checkbox" wire:click="toggleSelectPage" @checked($allPageSelected)
                               title="Tout sélectionner sur cette page">
                    </th>
                    <th class="caps" style="width:96px">Date</th>
                    <th class="caps">Commerçant</th>
                    <th class="caps">Type</th>
                    <th class="caps num" style="width:120px">Montant</th>
                    <th class="caps" style="width:230px">Catégorie</th>
                    <th class="caps" style="width:110px">Épargne</th>
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
                        <td class="small muted num" style="text-align:left">{{ $row->date->format('d.m.Y') }}</td>
                        <td>
                            <div>{{ \Illuminate\Support\Str::limit(str_replace(';', ' · ', $row->merchant ?? '—'), 48) }}</div>
                            @if ($row->details)
                                <div class="small quiet">{{ \Illuminate\Support\Str::limit($row->details, 60) }}</div>
                            @endif
                        </td>
                        <td class="small muted">{{ \Illuminate\Support\Str::limit($row->type ?? '', 22) }}</td>
                        {{-- Colour follows the money's role: kept, moved between own accounts, out, or in. --}}
                        <td class="num amount {{ $row->is_savings ? 'kept' : ($row->is_transfer ? 'muted' : ($row->amount < 0 ? 'neg' : 'pos')) }}">
                            {{ number_format($row->amount, 2, ',', ' ') }}
                        </td>
                        <td>
                            <select class="cat-select {{ ! $row->category_id && ! $row->is_savings ? 'todo' : '' }}"
                                    wire:change="assign({{ $row->id }}, $event.target.value)">
                                <option value="" @selected(! $row->category_id)>—</option>
                                @foreach ($cats as $c)
                                    <option value="{{ $c['id'] }}" @selected($row->category_id === $c['id'])>
                                        {!! str_repeat('&nbsp;&nbsp;', $c['depth']) !!}{{ $c['title'] }}
                                    </option>
                                @endforeach
                            </select>
                            @if ($row->source === 'rule')
                                <span class="mark rule caps" title="Classé automatiquement par une règle">auto</span>
                            @elseif ($row->source === 'manual')
                                <span class="mark manual caps" title="Défini manuellement">manuel</span>
                            @endif
                        </td>
                        <td>
                            <button class="btn sm {{ $row->is_savings ? 'kept' : 'ghost' }}"
                                    wire:click="toggleSavings({{ $row->id }})" aria-pressed="{{ $row->is_savings ? 'true' : 'false' }}">
                                @if ($row->is_savings) <x-icon name="check" size="14" /> Épargne @else Marquer @endif
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="muted" style="padding:48px 12px; text-align:center;">Aucune transaction ne correspond.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Pagination --}}
    <div class="pager">
        <span class="muted small">
            Page {{ $rows->currentPage() }} sur {{ max($rows->lastPage(), 1) }} · {{ number_format($rows->total(), 0, ',', ' ') }} lignes
        </span>
        <div style="display:flex; gap:8px;">
            <button class="btn sm" wire:click="previousPage" @disabled($rows->onFirstPage())><x-icon name="arrow-left" size="14" /> Préc.</button>
            <button class="btn sm" wire:click="nextPage" @disabled(! $rows->hasMorePages())>Suiv. <x-icon name="arrow-right" size="14" /></button>
        </div>
    </div>
</div>
