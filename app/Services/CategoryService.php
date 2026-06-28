<?php

namespace App\Services;

use Statamic\Facades\Entry;

/**
 * Reads the flat-file "categories" collection from Statamic and exposes helpers
 * for nesting (parent chains), roll-ups and chart metadata. Categories are few
 * (a few dozen) so we load them all once per request.
 */
class CategoryService
{
    protected ?array $rows = null;

    /** id => ['id','title','kind','parent','match_terms','color','depth'] */
    public function map(): array
    {
        if ($this->rows !== null) {
            return $this->rows;
        }

        $rows = [];
        foreach (Entry::query()->where('collection', 'categories')->get() as $entry) {
            $parent = $entry->get('parent');
            if (is_array($parent)) {
                $parent = $parent[0] ?? null;
            }

            $terms = $entry->get('match_terms') ?? [];
            $terms = array_values(array_filter(array_map('trim', (array) $terms), fn ($t) => $t !== ''));

            $rows[$entry->id()] = [
                'id' => $entry->id(),
                'title' => (string) $entry->get('title'),
                'kind' => $entry->get('kind') ?: 'expense',
                'parent' => $parent ?: null,
                'match_terms' => $terms,
                'color' => $entry->get('color') ?: null,
            ];
        }

        // Compute depth now that all rows are known.
        foreach ($rows as $id => $row) {
            $rows[$id]['depth'] = $this->computeDepth($id, $rows);
        }

        return $this->rows = $rows;
    }

    protected function computeDepth(string $id, array $rows, int $guard = 0): int
    {
        $parent = $rows[$id]['parent'] ?? null;
        if (! $parent || ! isset($rows[$parent]) || $guard > 10) {
            return 0;
        }

        return 1 + $this->computeDepth($parent, $rows, $guard + 1);
    }

    public function find(?string $id): ?array
    {
        return $id ? ($this->map()[$id] ?? null) : null;
    }

    public function title(?string $id): string
    {
        return $this->find($id)['title'] ?? 'Non classé';
    }

    /** Walk up to the top-level ancestor id (the category with no parent). */
    public function topLevel(?string $id): ?string
    {
        $rows = $this->map();
        $guard = 0;
        while ($id && isset($rows[$id]) && $rows[$id]['parent'] && $guard++ < 10) {
            $id = $rows[$id]['parent'];
        }

        return $id;
    }

    /** Full path of titles from root to the category, e.g. ["Food", "Groceries"]. */
    public function path(?string $id): array
    {
        $rows = $this->map();
        $chain = [];
        $guard = 0;
        while ($id && isset($rows[$id]) && $guard++ < 10) {
            array_unshift($chain, $rows[$id]['title']);
            $id = $rows[$id]['parent'];
        }

        return $chain;
    }

    public function savingsCategoryId(): ?string
    {
        foreach ($this->map() as $id => $row) {
            if ($row['kind'] === 'savings') {
                return $id;
            }
        }

        return null;
    }

    public function kindOf(?string $id): string
    {
        return $this->find($id)['kind'] ?? 'expense';
    }

    /**
     * Categories flattened in tree order (parents before their children),
     * each as ['id','title','depth','kind'] — handy for indented dropdowns.
     */
    public function ordered(): array
    {
        $map = $this->map();
        $byParent = [];
        foreach ($map as $id => $row) {
            $byParent[$row['parent'] ?? 'root'][] = $id;
        }
        foreach ($byParent as &$ids) {
            usort($ids, fn ($a, $b) => strcmp($map[$a]['title'], $map[$b]['title']));
        }
        unset($ids);

        $out = [];
        $walk = function ($parent) use (&$walk, &$out, $byParent, $map) {
            foreach (($byParent[$parent] ?? []) as $id) {
                $out[] = [
                    'id' => $id,
                    'title' => $map[$id]['title'],
                    'depth' => $map[$id]['depth'],
                    'kind' => $map[$id]['kind'],
                ];
                $walk($id);
            }
        };
        $walk('root');

        return $out;
    }
}
