@php $chf = fn ($v, $d = 0) => number_format((float) $v, $d, ',', ' '); @endphp
<div>
    <div class="head">
        <div>
            <h1>Patrimoine</h1>
            <p class="sub">Vos avoirs, relevé après relevé.</p>
        </div>
        @unless ($needsSetup || $formOpen)
            <button class="btn primary" wire:click="newReleve">Nouveau relevé</button>
        @endunless
    </div>

    {{-- Relevé form --}}
    @if ($formOpen)
        <section class="panel" style="margin-bottom:16px;">
            <h2 class="caps">{{ $releveId ? 'Modifier le relevé' : 'Nouveau relevé' }}
                <span class="hint">pré-rempli depuis le relevé précédent — ne changez que ce qui a bougé</span></h2>
            <form wire:submit="saveReleve">
                <div class="filters">
                    <label>Date <input type="date" wire:model.live="date" aria-label="Date du relevé"></label>
                </div>
                @error('date') <p class="error">{{ $message }}</p> @enderror
                @if ($nextReleve)
                    <p class="muted">Un relevé plus récent existe ({{ $nextReleve->date->format('d.m.Y') }}) : ses versements couvrent déjà cette période, ils ne sont donc pas proposés ici.</p>
                @endif
                <p class="quiet">Un avoir qui n'existait pas encore à cette date peut rester vide.</p>

                <table>
                    <thead><tr><th>Avoir</th><th style="text-align:right">Solde (CHF)</th><th style="text-align:right">Versement depuis le dernier relevé</th></tr></thead>
                    <tbody>
                    @foreach ($formAvoirs as $avoirs)
                        <tr><th colspan="3" style="text-align:left">{{ $avoirs->first()->classe->title }}<span class="desc">{{ $avoirs->first()->classe->description }}</span></th></tr>
                        @foreach ($avoirs as $a)
                            <tr wire:key="form-{{ $a->id }}">
                                <td class="indent">{{ $a->title }}<span class="desc">{{ $a->description }}</span></td>
                                <td style="text-align:right">
                                    <input type="text" inputmode="decimal" style="width:140px; text-align:right" aria-label="Solde {{ $a->title }}" wire:model="balances.{{ $a->id }}">
                                    @error("balances.{$a->id}") <p class="error">{{ $message }}</p> @enderror
                                </td>
                                <td style="text-align:right">
                                    @if ($a->suitLesVersements())
                                        <input type="text" inputmode="decimal" style="width:120px; text-align:right" aria-label="Versement {{ $a->title }}" wire:model="versements.{{ $a->id }}">
                                        @error("versements.{$a->id}") <p class="error">{{ $message }}</p> @enderror
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    @endforeach
                    </tbody>
                </table>

                <div class="filters" style="margin-top:12px;">
                    <button class="btn primary" type="submit">Enregistrer</button>
                    <button class="btn ghost" type="button" wire:click="cancel">Annuler</button>
                </div>
            </form>
        </section>
    @endif

    {{-- Latest Relevé: the total as a note, then the detail --}}
    @if ($latest)
        <div class="grid notes">
            <div class="note" style="--note: var(--savings)">
                <span class="label caps">Patrimoine au {{ $latest['date']->format('d.m.Y') }}</span>
                <span class="value"><span x-data="roll({{ $latest['total'] }}, 0, 'patrimoine')">{{ $chf($latest['total']) }}</span><span class="unit caps">CHF</span></span>
            </div>
            @if ($latest['previousDate'])
                <div class="note plain" style="--note: var(--paper-2)">
                    <span class="label caps">Depuis le {{ $latest['previousDate']->format('d.m.Y') }}</span>
                    <span class="value">{{ $latest['change'] >= 0 ? '+' : '−' }}{{ $chf(abs($latest['change'])) }}<span class="unit caps">CHF</span></span>
                </div>
            @endif
        </div>
    @endif
    <section class="panel" style="margin-bottom:16px;">
        @if ($latest)
            <h2 class="caps">Détail du relevé</h2>
            <table>
                <tbody>
                @foreach ($latest['groups'] as $g)
                    <tr><th style="text-align:left">{{ $g['title'] }}<span class="desc">{{ $g['description'] }}</span></th>
                        <th style="text-align:right" class="num">{{ $chf($g['subtotal']) }}</th><th></th></tr>
                    @foreach ($g['avoirs'] as $a)
                        <tr><td class="indent">{{ $a['title'] }}<span class="desc">{{ $a['description'] }}</span></td>
                            <td style="text-align:right" class="num">{{ $chf($a['balance']) }}</td>
                            <td style="text-align:right" @class(['num', 'pos' => ($a['change'] ?? 0) > 0, 'neg' => ($a['change'] ?? 0) < 0])>
                                @if ($a['change']) {{ $a['change'] > 0 ? '+' : '−' }}{{ $chf(abs($a['change'])) }} @endif
                            </td></tr>
                    @endforeach
                @endforeach
                <tr class="total"><th style="text-align:left">Total</th><th style="text-align:right" class="num">{{ $chf($latest['total']) }} CHF</th><th></th></tr>
                </tbody>
            </table>
        @else
            <h2 class="caps">Aucun relevé</h2>
            <p class="muted">{{ $needsSetup ? 'Commencez par créer vos classes et vos avoirs ci-dessous.' : 'Saisissez votre premier relevé avec « Nouveau relevé ». Vous pouvez le dater dans le passé.' }}</p>
        @endif
    </section>

    {{-- Charts --}}
    @if ($latest)
        <div class="grid two-col" style="margin-bottom:16px;">
            <section class="panel">
                <h2 class="caps">Patrimoine total</h2>
                <div class="chart" style="height:300px" wire:ignore x-data="echart(@js($charts['total']), 'total')"></div>
            </section>
            <section class="panel">
                <h2 class="caps">Répartition par classe</h2>
                <div class="chart" style="height:300px" wire:ignore x-data="echart(@js($charts['classes']), 'classes')"></div>
            </section>
        </div>
        @if ($hasVersements)
            <section class="panel" style="margin-bottom:16px;">
                <h2 class="caps">Versements et rendement <span class="hint">avoirs avec versements, cumulés depuis le premier relevé</span></h2>
                <div class="chart" style="height:300px" wire:ignore x-data="echart(@js($charts['versements']), 'versements')"></div>
            </section>
        @endif
    @endif

    {{-- History --}}
    @if ($history->isNotEmpty())
        <section class="panel" style="margin-bottom:16px;">
            <h2 class="caps">Relevés <span class="hint">cliquez pour modifier</span></h2>
            <table>
                <tbody>
                @foreach ($history as $r)
                    <tr wire:key="releve-{{ $r->id }}">
                        <td><button class="btn sm ghost" wire:click="editReleve({{ $r->id }})">{{ $r->date->format('d.m.Y') }}</button></td>
                        <td style="text-align:right" class="num">{{ $chf($r->lignes->sum('balance')) }} CHF</td>
                        <td style="text-align:right">
                            <button class="btn sm ghost" wire:click="deleteReleve({{ $r->id }})" wire:confirm="Supprimer le relevé du {{ $r->date->format('d.m.Y') }} ?">Supprimer</button>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </section>
    @endif

    {{-- Manage Classes and Avoirs (collapsible; Alpine keeps the open state across re-renders) --}}
    <section class="panel" x-data="{ open: @js($needsSetup) }">
        <h2 class="caps" style="margin-bottom:0" :style="open && { marginBottom: '22px' }">
            <button type="button" @click="open = !open" :aria-expanded="open"
                    style="all:unset; cursor:pointer; display:flex; gap:8px; align-items:center;">
                <span x-text="open ? '−' : '+'">+</span> Gérer les avoirs
            </button>
            <span class="hint">titres, ordre, versements mensuels, archivage</span>
        </h2>
        <div x-show="open" x-cloak>
            <livewire:patrimoine-avoirs />
        </div>
    </section>
</div>
