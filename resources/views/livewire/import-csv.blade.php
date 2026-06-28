<div>
    <h1>Importer des transactions</h1>
    <p class="sub">Importez votre/vos export(s) CSV UBS. Les lignes sont dédupliquées par numéro de transaction : réimporter est sans risque.</p>

    <div class="grid cards" style="grid-template-columns:repeat(auto-fit,minmax(150px,1fr));">
        <div class="card"><div class="label">En base</div><div class="value">{{ number_format($total, 0, ',', ' ') }}</div></div>
        <div class="card"><div class="label">Classées</div><div class="value green">{{ number_format($classified, 0, ',', ' ') }}</div></div>
        <div class="card"><div class="label">Non classées</div><div class="value amber">{{ number_format($unclassified, 0, ',', ' ') }}</div></div>
        <div class="card"><div class="label">Lignes épargne</div><div class="value blue">{{ number_format($savings, 0, ',', ' ') }}</div></div>
    </div>

    <div class="panel" style="margin-bottom:16px;">
        <h2>Importer un CSV</h2>
        <form wire:submit="import">
            <label class="dropzone" style="display:block; cursor:pointer;">
                <input type="file" wire:model="files" multiple accept=".csv" style="display:none;">
                <div wire:loading.remove wire:target="files">
                    📄 Cliquez pour choisir un ou plusieurs fichiers <strong>.csv</strong>
                </div>
                <div wire:loading wire:target="files">Lecture des fichiers…</div>
            </label>

            @error('files.*') <p class="small" style="color:var(--red)">{{ $message }}</p> @enderror

            @if (!empty($files))
                <ul class="small" style="margin:12px 0;">
                    @foreach ($files as $f)
                        <li>{{ $f->getClientOriginalName() }}</li>
                    @endforeach
                </ul>
                <button type="submit" class="btn primary" wire:loading.attr="disabled" wire:target="import">
                    <span wire:loading.remove wire:target="import">Importer {{ count($files) }} fichier(s)</span>
                    <span wire:loading wire:target="import">Import en cours…</span>
                </button>
            @endif
        </form>

        @if ($result)
            <div class="bulkbar" style="margin-top:16px;">
                ✅ <strong>{{ number_format($result['new'], 0, ',', ' ') }}</strong> importée(s),
                <strong>{{ number_format($result['duplicates'], 0, ',', ' ') }}</strong> doublon(s) ignoré(s)
                <span class="muted">({{ implode(', ', $result['files']) }})</span>
            </div>
        @endif
    </div>

    <div class="panel">
        <h2>Maintenance</h2>
        <p class="small muted" style="margin-top:0;">
            Vous avez modifié vos règles de catégories dans l'admin ? Relancez-les sur les lignes encore non classées.
            Les classements manuels sont toujours préservés.
        </p>
        <button class="btn" wire:click="reapplyRules" wire:loading.attr="disabled" wire:target="reapplyRules">
            <span wire:loading.remove wire:target="reapplyRules">Appliquer les règles aux non classées</span>
            <span wire:loading wire:target="reapplyRules">En cours…</span>
        </button>
    </div>
</div>
