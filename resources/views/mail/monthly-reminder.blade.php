@php
    $fmt = fn ($date) => $date ? \Illuminate\Support\Carbon::parse($date)->format('d.m.Y') : 'jamais';
@endphp
<x-mail::message>
# Nouveau mois, nouvelles données

C'est le moment de mettre le budget à jour :

- **Importer** les relevés du compte et de la carte de crédit. Dernière transaction : {{ $fmt($lastTransaction) }}.
- **Saisir un Relevé** du patrimoine. Dernier Relevé : {{ $fmt($lastReleve) }}.

<x-mail::button :url="route('budget.import')">
Importer les relevés
</x-mail::button>

<x-mail::button :url="route('budget.patrimoine')" color="success">
Saisir un Relevé
</x-mail::button>
</x-mail::message>
