{{-- CP dashboard widget (see statamic.cp.widgets): shortcuts to the only pages that matter. --}}
@php
    $links = [
        ['url' => route('budget.dashboard'), 'icon' => 'charts-donut-graph', 'title' => 'Tableau de bord', 'text' => 'Taux d’épargne, dépenses par catégorie, flux mensuels.'],
        ['url' => route('budget.import'), 'icon' => 'upload', 'title' => 'Importer des transactions', 'text' => 'Déposer les exports CSV UBS / carte de crédit.'],
        ['url' => cp_route('collections.show', 'categories'), 'icon' => 'taxonomies', 'title' => 'Catégories', 'text' => 'Arborescence, type et termes de classement automatique.'],
    ];
@endphp
<ui-widget title="Budget" icon="dashboard">
    <div class="grid gap-2 p-2 @2xl/widget:grid-cols-3">
        @foreach ($links as $link)
            <a href="{{ $link['url'] }}" class="flex items-start gap-3 rounded-lg p-3 hover:bg-gray-100 dark:hover:bg-gray-800">
                <ui-icon name="{{ $link['icon'] }}" class="size-5 shrink-0 text-gray-500"></ui-icon>
                <span>
                    <ui-heading text="{{ $link['title'] }}"></ui-heading>
                    <ui-description text="{{ $link['text'] }}"></ui-description>
                </span>
            </a>
        @endforeach
    </div>
</ui-widget>
