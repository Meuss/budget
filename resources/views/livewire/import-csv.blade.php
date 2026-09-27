<div>
    <div class="head">
        <div>
            <h1>Importer des transactions</h1>
            <p class="sub">Importez votre/vos export(s) CSV UBS — compte bancaire ou carte de crédit (le format est détecté automatiquement). Les lignes en double sont ignorées : réimporter est sans risque.</p>
        </div>
    </div>

    <div class="grid notes">
        <div class="note plain"><span class="label caps">En base</span><span class="value">{{ number_format($total, 0, ',', ' ') }}</span></div>
        <div class="note plain"><span class="label caps">Classées</span><span class="value">{{ number_format($classified, 0, ',', ' ') }}</span></div>
        <a class="note" style="--note: var(--act)" href="/budget/transactions?category=unclassified" wire:navigate title="Voir les transactions">
            <span class="label caps">Non classées <x-icon name="arrow-right" size="14" class="go" /></span>
            <span class="value">{{ number_format($unclassified, 0, ',', ' ') }}</span>
        </a>
        <a class="note" style="--note: var(--savings)" href="/budget/transactions?category=savings" wire:navigate title="Voir les transactions">
            <span class="label caps">Lignes épargne <x-icon name="arrow-right" size="14" class="go" /></span>
            <span class="value">{{ number_format($savings, 0, ',', ' ') }}</span>
        </a>
    </div>

    <section class="panel" style="margin-bottom:16px;">
        <h2 class="caps">Importer un CSV</h2>
        <form wire:submit="import">
            <label class="dropzone">
                <input type="file" wire:model="files" multiple accept=".csv" style="display:none;">
                <x-icon name="upload" size="22" />
                <span wire:loading.remove wire:target="files">
                    Cliquez pour choisir un ou plusieurs fichiers <strong>.csv</strong>
                </span>
                <span wire:loading wire:target="files">Lecture des fichiers…</span>
            </label>

            @error('files.*') <p class="small error">{{ $message }}</p> @enderror

            @if (!empty($files))
                <ul class="files small">
                    @foreach ($files as $f)
                        <li><x-icon name="file" size="14" class="quiet" />{{ $f->getClientOriginalName() }}</li>
                    @endforeach
                </ul>
                <button type="submit" class="btn primary" wire:loading.attr="disabled" wire:target="import">
                    <span wire:loading.remove wire:target="import">Importer {{ count($files) }} fichier(s)</span>
                    <span wire:loading wire:target="import">Import en cours…</span>
                </button>
            @endif
        </form>

        @if ($result)
            <div class="result">
                <x-icon name="check" />
                <span><strong>{{ number_format($result['new'], 0, ',', ' ') }}</strong> importée(s),
                <strong>{{ number_format($result['duplicates'], 0, ',', ' ') }}</strong> doublon(s) ignoré(s)</span>
                <span class="muted">({{ implode(', ', $result['files']) }})</span>
            </div>
        @endif
    </section>

    <section class="panel">
        <h2 class="caps">Maintenance</h2>
        <p class="small muted">
            Vous avez modifié vos règles de catégories dans l'admin ? Relancez-les sur les lignes encore non classées.
            Les classements manuels sont toujours préservés.
        </p>
        <button class="btn" wire:click="reapplyRules" wire:loading.attr="disabled" wire:target="reapplyRules">
            <span wire:loading.remove wire:target="reapplyRules">Appliquer les règles aux non classées</span>
            <span wire:loading wire:target="reapplyRules">En cours…</span>
        </button>
    </section>
</div>
