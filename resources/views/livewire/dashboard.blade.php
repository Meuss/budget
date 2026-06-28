<div>
    <div style="display:flex; align-items:flex-start; justify-content:space-between; gap:16px; flex-wrap:wrap;">
        <div>
            <h1>Tableau de bord</h1>
            <p class="sub">Où va votre argent — et combien vous gardez.</p>
        </div>
        <div class="seg" wire:key="period-seg">
            <button class="{{ $period === 'all' ? 'active' : '' }}" wire:click="$set('period', 'all')">Tout</button>
            @foreach ($years as $y)
                <button class="{{ $period === $y ? 'active' : '' }}" wire:click="$set('period', '{{ $y }}')">{{ $y }}</button>
            @endforeach
        </div>
    </div>

    <div class="grid cards">
        <div class="card">
            <div class="label">Revenus</div>
            <div class="value green">{{ number_format($kpis['income'], 0, ',', ' ') }} <span class="small muted">CHF</span></div>
        </div>
        <div class="card">
            <div class="label">Dépenses</div>
            <div class="value red">{{ number_format($kpis['spending'], 0, ',', ' ') }} <span class="small muted">CHF</span></div>
        </div>
        <div class="card">
            <div class="label">Épargne</div>
            <div class="value blue">{{ number_format($kpis['savings'], 0, ',', ' ') }} <span class="small muted">CHF</span></div>
        </div>
        <div class="card">
            <div class="label">Taux d'épargne</div>
            <div class="value amber">{{ number_format($kpis['rate'], 1, ',', ' ') }}<span class="small muted">%</span></div>
        </div>
        <div class="card">
            <div class="label">Non alloué</div>
            <div class="value">{{ number_format($kpis['net'], 0, ',', ' ') }} <span class="small muted">CHF</span></div>
        </div>
    </div>

    <div class="grid two-col" style="margin-bottom:16px;">
        <div class="panel">
            <h2>Dépenses par catégorie</h2>
            <div class="chart" style="height:340px" wire:ignore x-data="echart(@js($pie), 'pie')"></div>
        </div>
        <div class="panel">
            <h2>Revenus · dépenses · épargne par mois</h2>
            <div class="chart" style="height:340px" wire:ignore x-data="echart(@js($bar), 'bar')"></div>
        </div>
    </div>

    <div class="panel">
        <h2>Flux d'argent (revenus → catégories → sous-catégories)</h2>
        <div class="chart" style="height:480px" wire:ignore x-data="echart(@js($sankey), 'sankey')"></div>
    </div>
</div>
