---
version: 1
slug: "resources-views-budget-layout-blade-php"
primary_target: "resources/views/budget/layout.blade.php"
related_targets: ["resources/views/livewire/dashboard.blade.php","resources/views/livewire/transactions.blade.php","resources/views/livewire/import-csv.blade.php"]
---

# /budget app (dashboard, transactions, import)

Scope: the whole Livewire front-end under /budget (shared layout + three screens). Not the Statamic CP.
Mode: Operate. One user, desktop, evenings in a dim room. Job: import CSVs, classify, then see where every franc went.
Constraints: French copy kept verbatim; colors must stay bound to meaning; no playful/gamified devices; no generic SaaS look; figures private in any capture.

## Direction contract

THESIS: Each money role owns a ninth-series franc-note colour, printed in fine intaglio line on a dark vault ground. Refuses the navy-card dashboard with an indigo accent.

OWN-WORLD: Warm vault black (#131315) with note-paper white text. Cool notes are money kept: 50-green for income, 100-blue for savings. Warm notes are money out: 20-red for spending and 200-copper for unallocated. The 10-yellow marks only what you can act on. Surfaces have hairline rules and no shadows. Condensed tracked caps for labels, wide bold tabular numerals, and microtext security strips.

STORY: One glance tells how much came in, went out and was kept. Any figure opens the transactions behind it.

FIRST VIEWPORT: The nav has a wordmark and yellow-underlined active station, over a microtext strip. The title sits left, with period tabs top-right. Five KPI denomination fields fill the width, each tinted in its note colour with a hatch and a large numeral. Ranked spending bars and monthly bars sit below.

FORM: Billet de banque (9th-series franc notes), my list #1, seed 865119b3. Signature interaction: every figure is cashable (KPI, bar or node → filtered Transactions); numerals roll on period change.

FINISH: unreviewed and undocumented is unfinished; this build ends with the finish review, the verdict, DESIGN.md, and every shipping raster carrying its provenance
