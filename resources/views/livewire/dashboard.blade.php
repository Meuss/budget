<div>
    <div class="head">
        <div>
            <h1>Tableau de bord</h1>
            <p class="sub">Où va votre argent — et combien vous gardez.</p>
        </div>
        <div class="seg" wire:key="period-seg" role="tablist" aria-label="Période">
            <button role="tab" aria-selected="{{ $period === 'all' ? 'true' : 'false' }}" class="{{ $period === 'all' ? 'active' : '' }}" wire:click="$set('period', 'all')">Tout</button>
            @foreach ($years as $y)
                <button role="tab" aria-selected="{{ $period === $y ? 'true' : 'false' }}" class="{{ $period === $y ? 'active' : '' }}" wire:click="$set('period', '{{ $y }}')">{{ $y }}</button>
            @endforeach
        </div>
    </div>

    @php
        $notes = [
            ['key' => 'income', 'label' => 'Revenus', 'color' => '--income', 'unit' => 'CHF', 'dec' => 0, 'href' => $links['income']],
            ['key' => 'spending', 'label' => 'Dépenses', 'color' => '--spending', 'unit' => 'CHF', 'dec' => 0, 'href' => $links['spending']],
            ['key' => 'savings', 'label' => 'Épargne', 'color' => '--savings', 'unit' => 'CHF', 'dec' => 0, 'href' => $links['savings']],
            ['key' => 'rate', 'label' => "Taux d'épargne", 'color' => '--savings', 'unit' => '%', 'dec' => 1, 'href' => $links['rate']],
            ['key' => 'net', 'label' => 'Non alloué', 'color' => '--copper', 'unit' => 'CHF', 'dec' => 0, 'href' => $links['net']],
        ];
    @endphp
    <div class="grid notes">
        @foreach ($notes as $n)
            @php $tag = $n['href'] ? 'a' : 'div'; @endphp
            <{{ $tag }} class="note" style="--note: var({{ $n['color'] }})" wire:key="note-{{ $n['key'] }}-{{ $period }}"
                @if ($n['href']) href="{{ $n['href'] }}" wire:navigate title="Voir les transactions" @endif>
                <span class="label caps">
                    {{ $n['label'] }}
                    @if ($n['href']) <x-icon name="arrow-right" size="14" class="go" /> @endif
                </span>
                <span class="value"><span x-data="roll({{ (float) $kpis[$n['key']] }}, {{ $n['dec'] }}, '{{ $n['key'] }}')">{{ number_format($kpis[$n['key']], $n['dec'], ',', ' ') }}</span><span class="unit caps">{{ $n['unit'] }}</span></span>
            </{{ $tag }}>
        @endforeach
    </div>

    <div class="grid two-col" style="margin-bottom:16px;">
        <section class="panel">
            <h2 class="caps">Dépenses par catégorie <span class="hint">cliquez pour voir le détail</span></h2>
            <div class="chart" style="height:{{ max(120, count($spending['yAxis'][0]['data']) * 40 + 8) }}px" wire:ignore x-data="echart(@js($spending), 'spending')"></div>
        </section>
        <section class="panel">
            <h2 class="caps">Revenus · dépenses · épargne par mois</h2>
            <div class="chart" style="height:360px" wire:ignore x-data="echart(@js($bar), 'bar')"></div>
        </section>
    </div>

    <section class="panel">
        <h2 class="caps">Flux d'argent (revenus → catégories → sous-catégories)</h2>
        <div class="chart" style="height:520px" wire:ignore x-data="echart(@js($sankey), 'sankey')"></div>
    </section>
</div>
