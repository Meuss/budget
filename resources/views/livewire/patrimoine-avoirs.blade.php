<div>
    {{-- Classe form --}}
    <form wire:submit="saveClasse" class="filters" style="margin-bottom:8px;">
        <input type="text" maxlength="60" placeholder="Nouvelle classe (ex. Liquidités)" aria-label="Titre de la classe" wire:model="classeTitle">
        <input type="text" maxlength="160" class="grow" placeholder="Note courte (facultatif)" aria-label="Description de la classe" wire:model="classeDescription">
        <button class="btn primary sm" type="submit">{{ $classeId ? 'Enregistrer la classe' : 'Ajouter la classe' }}</button>
        @if ($classeId) <button class="btn sm ghost" type="button" wire:click="cancel">Annuler</button> @endif
    </form>
    @error('classeTitle') <p class="error">{{ $message }}</p> @enderror
    @error('classeDescription') <p class="error">{{ $message }}</p> @enderror

    {{-- Avoir form --}}
    @if ($classes->isNotEmpty())
        <form wire:submit="saveAvoir" class="filters" style="margin:16px 0 8px;">
            <select wire:model="avoirClasse" aria-label="Classe">
                <option value="">Classe…</option>
                @foreach ($classes as $c) <option value="{{ $c->id }}">{{ $c->title }}</option> @endforeach
            </select>
            <input type="text" maxlength="60" placeholder="Nouvel avoir (ex. Compte courant)" aria-label="Titre de l'avoir" wire:model="avoirTitle">
            <input type="text" maxlength="160" class="grow" placeholder="Note courte (facultatif)" aria-label="Description de l'avoir" wire:model="avoirDescription">
            <input type="text" inputmode="decimal" style="width:170px" placeholder="Versement mensuel" aria-label="Versement mensuel (CHF, facultatif)"
                   title="Montant de l'ordre permanent. Le renseigner active le suivi des versements." wire:model="avoirVersementMensuel">
            <button class="btn primary sm" type="submit">{{ $avoirId ? "Enregistrer l'avoir" : "Ajouter l'avoir" }}</button>
            @if ($avoirId) <button class="btn sm ghost" type="button" wire:click="cancel">Annuler</button> @endif
        </form>
        @foreach (['avoirClasse', 'avoirTitle', 'avoirDescription', 'avoirVersementMensuel'] as $field)
            @error($field) <p class="error">{{ $message }}</p> @enderror
        @endforeach
    @endif

    {{-- Classes and their Avoirs --}}
    <table>
        <tbody>
        @foreach ($classes as $c)
            <tr wire:key="classe-{{ $c->id }}">
                <th colspan="3" style="text-align:left">{{ $c->title }}<span class="desc">{{ $c->description }}</span></th>
                <th style="text-align:right; white-space:nowrap">
                    <button class="btn sm ghost" wire:click="moveClasse({{ $c->id }}, -1)" aria-label="Monter">↑</button>
                    <button class="btn sm ghost" wire:click="moveClasse({{ $c->id }}, 1)" aria-label="Descendre">↓</button>
                    <button class="btn sm ghost" wire:click="editClasse({{ $c->id }})">Modifier</button>
                    <button class="btn sm ghost" wire:click="deleteClasse({{ $c->id }})" wire:confirm="Supprimer la classe « {{ $c->title }} » ?">Supprimer</button>
                </th>
            </tr>
            @forelse ($c->avoirs as $a)
                <tr wire:key="avoir-{{ $a->id }}" @class(['quiet' => $a->archived_on])>
                    <td class="indent">{{ $a->title }}<span class="desc">{{ $a->description }}</span></td>
                    <td class="muted">
                        @if ($a->suitLesVersements()) {{ number_format((float) $a->versement_mensuel, 0, ',', ' ') }} CHF / mois @endif
                    </td>
                    <td>
                        @if ($a->archived_on)
                            <span class="chip neutral">Archivé le {{ $a->archived_on->format('d.m.Y') }}</span>
                        @endif
                    </td>
                    <td style="text-align:right; white-space:nowrap">
                        <button class="btn sm ghost" wire:click="moveAvoir({{ $a->id }}, -1)" aria-label="Monter">↑</button>
                        <button class="btn sm ghost" wire:click="moveAvoir({{ $a->id }}, 1)" aria-label="Descendre">↓</button>
                        <button class="btn sm ghost" wire:click="editAvoir({{ $a->id }})">Modifier</button>
                        @if ($a->archived_on)
                            <button class="btn sm ghost" wire:click="unarchiveAvoir({{ $a->id }})">Désarchiver</button>
                        @else
                            <span x-data="{ asking: false }">
                                <button class="btn sm ghost" x-show="!asking" @click="asking = true">Archiver…</button>
                                <span x-show="asking" x-cloak>
                                    <input type="date" style="width:150px" aria-label="Archiver à partir du" title="Vide = aujourd'hui" wire:model="archiveOn.{{ $a->id }}">
                                    <button class="btn sm" wire:click="archiveAvoir({{ $a->id }})">Archiver</button>
                                    <button class="btn sm ghost" @click="asking = false" aria-label="Annuler">✕</button>
                                </span>
                            </span>
                        @endif
                        <button class="btn sm ghost" wire:click="deleteAvoir({{ $a->id }})" wire:confirm="Supprimer l'avoir « {{ $a->title }} » ?">Supprimer</button>
                    </td>
                </tr>
            @empty
                <tr><td colspan="4" class="quiet">Aucun avoir dans cette classe.</td></tr>
            @endforelse
        @endforeach
        </tbody>
    </table>
</div>
