<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="color-scheme" content="dark">
    <title>{{ $title ?? 'Budget Épargne' }}</title>
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="icon" href="/favicon.ico" sizes="48x48 32x32 16x16">
    <meta name="theme-color" content="#121214">
    <link rel="preload" href="/fonts/archivo-latin.woff2" as="font" type="font/woff2" crossorigin>
    <script src="https://cdn.jsdelivr.net/npm/echarts@5/dist/echarts.min.js"></script>
    <style>
        @font-face {
            font-family: "Archivo";
            src: url("/fonts/archivo-latin.woff2") format("woff2");
            font-weight: 100 900; font-stretch: 62% 125%; font-display: swap;
        }

        /*
         * Billet de banque: a dark vault ground, note-paper ink, and one ninth-series
         * franc-note colour per money role. Series colours are mirrored in BudgetReport.
         */
        :root {
            --vault: #121214;     /* page ground */
            --vault-2: #18181b;   /* panels */
            --vault-3: #222226;   /* controls, raised */
            --rule: #2a2a30;      /* hairlines */
            --rule-2: #3b3b43;    /* control borders */
            --paper: #ece6d8;     /* primary ink */
            --paper-2: #aca698;   /* secondary ink */
            --paper-3: #9a9488;   /* quiet ink: hints, placeholders, axis labels (>= 4.5:1 on --vault-3) */

            --income: #3aa384;    /* 50: money in */
            --spending: #e2573f;  /* 20: money out */
            --savings: #5b8fe0;   /* 100: money kept */
            --copper: #b8794a;    /* 200: left on the account */
            --act: #e9b949;       /* 10: what you can act on */
            --act-ink: #1d1706;

            --radius: 5px;
            --wide: 118%;         /* font-stretch for numerals and titles */
            --narrow: 78%;        /* font-stretch for tracked caps */
        }
        * { box-sizing: border-box; }
        html { scrollbar-color: var(--rule-2) var(--vault); }
        body {
            margin: 0; background: var(--vault); color: var(--paper);
            font-family: "Archivo", ui-sans-serif, system-ui, sans-serif;
            font-size: 14px; line-height: 1.5; font-variant-numeric: tabular-nums;
            -webkit-font-smoothing: antialiased;
        }
        ::selection { background: color-mix(in oklab, var(--act) 38%, transparent); color: var(--paper); }
        a { color: inherit; text-decoration: none; }
        :focus-visible { outline: 2px solid var(--act); outline-offset: 2px; border-radius: 3px; }
        .icon { flex: none; vertical-align: -3px; }

        /* Tracked condensed caps: the note's small print. */
        .caps {
            font-stretch: var(--narrow); text-transform: uppercase; letter-spacing: .09em;
            font-weight: 600; font-size: 12px;
        }

        /* ---- Top bar ------------------------------------------------------ */
        .nav {
            display: flex; align-items: stretch; gap: 2px; padding: 0 24px; height: 56px;
            background: var(--vault); position: sticky; top: 0; z-index: 10;
        }
        .brand {
            display: flex; align-items: center; margin-right: 28px;
            font-stretch: var(--wide); font-weight: 750; font-size: 16px; letter-spacing: -.01em;
        }
        .brand span { color: var(--savings); }
        .nav a.link {
            display: flex; align-items: center; gap: 6px; padding: 0 12px;
            color: var(--paper-2); font-weight: 500; position: relative; white-space: nowrap;
            transition: color .15s ease-out;
        }
        .nav a.link:hover { color: var(--paper); }
        .nav a.link.active { color: var(--paper); }
        .nav a.link.active::after {
            content: ""; position: absolute; left: 12px; right: 12px; bottom: 0; height: 2px;
            background: var(--act); border-radius: 2px 2px 0 0;
        }
        .nav .spacer { flex: 1; }
        .nav .out { font-size: 13px; }
        .nav .out .icon { opacity: .7; }

        /* Microtext strip under the bar: the banknote's security line. */
        .microtext {
            height: 9px; overflow: hidden; white-space: nowrap; user-select: none;
            border-top: 1px solid var(--rule); border-bottom: 1px solid var(--rule);
            font-size: 5.5px; line-height: 7px; letter-spacing: .32em; font-stretch: var(--narrow);
            font-weight: 600; color: #34343b; text-transform: uppercase;
            position: sticky; top: 56px; z-index: 10; background: var(--vault);
        }

        .wrap { max-width: 1240px; margin: 0 auto; padding: 36px 24px 80px; }

        /* ---- Page heads ---------------------------------------------------- */
        .head { display: flex; align-items: flex-end; justify-content: space-between; gap: 20px; flex-wrap: wrap; margin-bottom: 28px; }
        h1 {
            font-stretch: var(--wide); font-size: 30px; font-weight: 750; letter-spacing: -.02em;
            line-height: 1.1; margin: 0 0 8px; text-wrap: balance;
        }
        .sub { color: var(--paper-2); margin: 0; max-width: 72ch; }
        .sub em { color: var(--paper); font-style: normal; font-weight: 600; }

        .grid { display: grid; gap: 16px; }
        .two-col { grid-template-columns: minmax(0, 5fr) minmax(0, 7fr); align-items: start; }
        @media (max-width: 960px) { .two-col { grid-template-columns: 1fr; } }

        /* ---- Denomination fields (headline figures) ------------------------ */
        .notes { grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: 12px; margin-bottom: 16px; }
        .note {
            --note: var(--paper-2);
            position: relative; overflow: hidden; display: flex; flex-direction: column; gap: 14px;
            padding: 16px 18px 18px; border-radius: var(--radius);
            background: color-mix(in oklab, var(--note) 9%, var(--vault));
            border: 1px solid color-mix(in oklab, var(--note) 26%, var(--vault));
            transition: border-color .2s ease-out, background-color .2s ease-out;
        }
        /* Intaglio line work: fine diagonal engraving, strongest at the right edge. */
        .note::before {
            content: ""; position: absolute; inset: 0; pointer-events: none;
            background: repeating-linear-gradient(118deg,
                color-mix(in oklab, var(--note) 30%, transparent) 0 1px, transparent 1px 5px);
            -webkit-mask-image: linear-gradient(90deg, transparent 35%, #000 100%);
                    mask-image: linear-gradient(90deg, transparent 35%, #000 100%);
            opacity: .55;
        }
        .note > * { position: relative; }
        .note .label { color: color-mix(in oklab, var(--note) 45%, var(--paper)); display: flex; justify-content: space-between; align-items: center; }
        .note .value {
            font-stretch: var(--wide); font-weight: 700; font-size: 32px; line-height: 1;
            letter-spacing: -.02em; color: var(--note); white-space: nowrap;
        }
        .note .unit { font-size: 12px; margin-left: 6px; letter-spacing: .09em; color: color-mix(in oklab, var(--note) 55%, var(--paper-3)); }
        .note .go { opacity: 0; transform: translateX(-4px); transition: opacity .2s ease-out, transform .2s ease-out; }
        a.note:hover { border-color: color-mix(in oklab, var(--note) 60%, var(--vault)); background: color-mix(in oklab, var(--note) 13%, var(--vault)); }
        a.note:hover .go, a.note:focus-visible .go { opacity: 1; transform: none; }
        .note.plain { --note: var(--paper); }
        .note.plain .value { color: var(--paper); }

        /* ---- Panels -------------------------------------------------------- */
        .panel {
            background: var(--vault-2); border: 1px solid var(--rule); border-radius: var(--radius); padding: 18px 20px 20px;
        }
        .panel h2 {
            margin: 0 0 22px; position: relative; color: var(--paper); display: flex; flex-wrap: wrap; align-items: baseline; justify-content: space-between; gap: 4px 12px;
        }
        /* Microtext rule under every panel head: the note's security line, carried below the fold. */
        .panel h2::after {
            content: "Budget Épargne · Franc suisse · Revenus · Dépenses · Épargne · Budget Épargne · Franc suisse · Revenus · Dépenses · Épargne · Budget Épargne · Franc suisse · Revenus · Dépenses · Épargne · Budget Épargne · Franc suisse · Revenus · Dépenses · Épargne" / "";
            position: absolute; left: 0; right: 0; bottom: -12px; height: 7px; overflow: hidden; white-space: nowrap;
            font-size: 5.5px; line-height: 7px; letter-spacing: .32em; font-weight: 600; color: #3a3a41;
            border-top: 1px solid var(--rule); padding-top: 1px;
        }
        .panel h2 .hint { font-size: 12px; font-weight: 500; letter-spacing: 0; text-transform: none; color: var(--paper-3); font-stretch: 100%; }
        .panel p { margin: 0 0 14px; }
        .chart { width: 100%; }

        /* ---- Period tabs (stations) ---------------------------------------- */
        .seg { display: inline-flex; gap: 2px; border-bottom: 1px solid var(--rule); }
        .seg button {
            background: none; border: 0; color: var(--paper-2); padding: 8px 12px 10px; cursor: pointer;
            font: inherit; font-weight: 600; font-stretch: var(--wide); position: relative; transition: color .15s ease-out;
        }
        .seg button:hover { color: var(--paper); }
        .seg button.active { color: var(--paper); }
        .seg button.active::after {
            content: ""; position: absolute; left: 8px; right: 8px; bottom: -1px; height: 2px; background: var(--act); border-radius: 2px 2px 0 0;
        }

        /* ---- Controls ------------------------------------------------------ */
        input, select, .btn {
            background: var(--vault-3); border: 1px solid var(--rule-2); color: var(--paper);
            border-radius: var(--radius); padding: 8px 11px; font: inherit; font-size: 13px; line-height: 1.3;
            transition: border-color .15s ease-out, background-color .15s ease-out, box-shadow .15s ease-out;
        }
        input::placeholder { color: var(--paper-3); }
        input:hover, select:hover { border-color: #4a4a54; }
        input:focus, select:focus { outline: none; border-color: var(--act); box-shadow: 0 0 0 3px color-mix(in oklab, var(--act) 22%, transparent); }
        select {
            appearance: none; padding-right: 30px; cursor: pointer;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='none' stroke='%23aca698' stroke-width='1.6' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='m4.5 6.5 3.5 3.5 3.5-3.5'/%3E%3C/svg%3E");
            background-repeat: no-repeat; background-position: right 8px center;
        }
        select option { background: var(--vault-3); color: var(--paper); }
        input[type=search]::-webkit-search-cancel-button { filter: invert(.7); }
        input[type=number] { font-variant-numeric: tabular-nums; }
        .btn { cursor: pointer; font-weight: 600; display: inline-flex; align-items: center; gap: 6px; white-space: nowrap; }
        .btn:hover { background: #2b2b31; border-color: #4a4a54; }
        .btn:disabled { opacity: .42; cursor: not-allowed; }
        .btn.primary { background: var(--act); border-color: var(--act); color: var(--act-ink); }
        .btn.primary:hover:not(:disabled) { background: #f2c85e; border-color: #f2c85e; }
        .btn.sm { padding: 6px 10px; font-size: 12.5px; }
        .btn.ghost { background: transparent; border-color: transparent; color: var(--paper-2); }
        .btn.ghost:hover { color: var(--paper); background: var(--vault-3); }
        .btn.kept { background: color-mix(in oklab, var(--savings) 16%, var(--vault-2)); border-color: color-mix(in oklab, var(--savings) 55%, var(--vault)); color: #a9c5f2; }
        .btn.kept:hover { background: color-mix(in oklab, var(--savings) 24%, var(--vault-2)); }
        input[type=checkbox] { width: 16px; height: 16px; padding: 0; accent-color: var(--act); cursor: pointer; box-shadow: none; }

        /* ---- Tables -------------------------------------------------------- */
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        .table-wrap table { min-width: 960px; }
        table td:first-child, table th:first-child { user-select: none; }
        th, td { text-align: left; padding: 10px 12px; border-bottom: 1px solid var(--rule); vertical-align: middle; }
        tbody tr:last-child td { border-bottom: 0; }
        th { color: var(--paper-2); background: var(--vault-2); }
        tbody tr { transition: background-color .12s ease-out; }
        tbody tr:hover td { background: #1c1c20; }
        tr.row-selected td, tr.row-selected:hover td { background: color-mix(in oklab, var(--act) 9%, var(--vault-2)); }
        .num { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
        .amount { font-stretch: var(--wide); font-weight: 600; }
        .neg { color: var(--spending); } .pos { color: var(--income); } .amount.kept { color: var(--savings); }
        .muted { color: var(--paper-2); }
        .quiet { color: var(--paper-3); }
        .small { font-size: 12.5px; }
        .mark { font-size: 11px; margin-top: 4px; display: block; }
        .mark.manual { color: var(--paper-2); }
        .mark.rule { color: var(--paper-3); }
        .cat-select { min-width: 170px; max-width: 100%; }
        /* An unclassified row is a to-do: its picker wears the "act here" yellow. */
        .cat-select.todo { border-color: color-mix(in oklab, var(--act) 55%, var(--vault)); background-color: color-mix(in oklab, var(--act) 7%, var(--vault-3)); }

        /* ---- Filters & action bar ----------------------------------------- */
        .filters { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; margin-bottom: 12px; }
        .filters .grow { flex: 1; min-width: 200px; position: relative; display: flex; }
        .filters .grow input { flex: 1; padding-left: 32px; }
        .filters .grow .icon { position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: var(--paper-3); pointer-events: none; }
        .chip {
            display: inline-flex; align-items: center; gap: 6px; padding: 5px 6px 5px 10px; border-radius: var(--radius);
            background: color-mix(in oklab, var(--spending) 12%, var(--vault-2)); border: 1px solid color-mix(in oklab, var(--spending) 40%, var(--vault));
            font-size: 12.5px; font-weight: 600;
        }
        .chip.neutral { background: var(--vault-3); border-color: var(--rule-2); }
        .chip button { background: none; border: 0; color: inherit; cursor: pointer; padding: 2px; display: flex; border-radius: 3px; }
        .chip button:hover { background: rgba(255,255,255,.08); }
        .bulkbar {
            display: flex; gap: 10px; align-items: center; flex-wrap: wrap;
            border: 1px solid var(--rule); border-radius: var(--radius); padding: 10px 12px; margin-bottom: 12px;
            background: var(--vault-2);
        }
        .bulkbar .count { font-stretch: var(--wide); font-weight: 700; }
        .bulkbar .sep { width: 1px; align-self: stretch; background: var(--rule); margin: 0 2px; }
        .pager { display: flex; align-items: center; justify-content: space-between; margin-top: 14px; gap: 12px; }

        /* ---- Import -------------------------------------------------------- */
        .dropzone {
            display: flex; flex-direction: column; align-items: center; gap: 10px; cursor: pointer;
            border: 1px dashed var(--rule-2); border-radius: var(--radius); padding: 40px 20px; text-align: center; color: var(--paper-2);
            background: repeating-linear-gradient(118deg, rgba(236,230,216,.025) 0 1px, transparent 1px 6px);
            transition: border-color .15s ease-out, color .15s ease-out;
        }
        .dropzone:hover { border-color: var(--act); color: var(--paper); }
        .dropzone .icon { color: var(--act); }
        .dropzone strong { color: var(--paper); }
        .files { list-style: none; padding: 0; margin: 14px 0; display: grid; gap: 6px; }
        .files li { display: flex; align-items: center; gap: 8px; }
        .result {
            display: flex; gap: 8px; align-items: center; flex-wrap: wrap; margin-top: 16px; padding: 12px 14px;
            border-radius: var(--radius); background: color-mix(in oklab, var(--income) 10%, var(--vault-2));
            border: 1px solid color-mix(in oklab, var(--income) 35%, var(--vault));
        }
        .result .icon { color: var(--income); }
        .error { color: #f08a78; margin: 10px 0 0; }

        /* ---- Toast --------------------------------------------------------- */
        .toast {
            position: fixed; bottom: 24px; right: 24px; z-index: 20; display: flex; align-items: center; gap: 10px;
            background: var(--vault-3); color: var(--paper); border: 1px solid var(--rule-2);
            padding: 12px 16px; border-radius: var(--radius); font-weight: 600;
        }
        .toast .icon { color: var(--act); }

        @media (max-width: 720px) {
            .nav { padding: 0 12px; overflow-x: auto; -webkit-mask-image: linear-gradient(90deg, #000 82%, transparent); mask-image: linear-gradient(90deg, #000 82%, transparent); }
            .brand { margin-right: 12px; }
            .nav .out { display: none; }
            .wrap { padding: 24px 16px 64px; }
            h1 { font-size: 26px; }
            .note .value { font-size: 28px; }
        }
        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after { transition: none !important; }
        }
    </style>
    @livewireStyles
    <script>
        document.addEventListener('alpine:init', () => {
            const css = getComputedStyle(document.documentElement);
            const t = (name) => css.getPropertyValue(name).trim();
            const chf = new Intl.NumberFormat('fr-CH', { maximumFractionDigits: 0 });
            const font = 'Archivo, ui-sans-serif, system-ui, sans-serif';

            // One chart theme, drawn from the page tokens.
            const axis = {
                axisLine: { show: false }, axisTick: { show: false },
                axisLabel: { color: t('--paper-3'), fontSize: 11 },
                splitLine: { lineStyle: { color: t('--rule'), type: [2, 4] } },
            };
            echarts.registerTheme('billet', {
                backgroundColor: 'transparent',
                textStyle: { fontFamily: font, color: t('--paper-2') },
                categoryAxis: { ...axis, splitLine: { show: false } },
                valueAxis: axis,
                legend: { textStyle: { color: t('--paper-2'), fontSize: 12 }, icon: 'roundRect', itemWidth: 10, itemHeight: 10, itemGap: 18 },
                tooltip: {
                    backgroundColor: t('--vault-3'), borderColor: t('--rule-2'), borderWidth: 1, padding: [8, 12],
                    textStyle: { color: t('--paper'), fontFamily: font, fontSize: 12 },
                    extraCssText: 'box-shadow: none; border-radius: 5px;',
                },
            });

            const money = (v) => chf.format(v) + ' CHF';
            const dress = (opt) => {
                opt = JSON.parse(JSON.stringify(opt)); // plain copy; also unwraps Alpine proxies
                opt.tooltip = { ...(opt.tooltip || {}), valueFormatter: money };
                (opt.yAxis || []).forEach((a) => { if (a.type === 'value') a.axisLabel = { ...a.axisLabel, formatter: (v) => chf.format(v) }; });
                (opt.series || []).forEach((s) => {
                    if (s.type === 'bar' && s.label && s.label.show) {
                        s.label = { ...s.label, color: t('--paper-2'), fontSize: 12, formatter: (p) => chf.format(p.value) };
                    }
                    if (s.type === 'bar' && opt.yAxis?.[0]?.type === 'category') {
                        opt.yAxis[0].axisLabel = { color: t('--paper'), fontSize: 13, margin: 12 };
                    }
                    if (s.type === 'sankey') {
                        s.label = { color: t('--paper'), fontSize: 12, fontFamily: font };
                        s.itemStyle = { borderWidth: 0 };
                        s.tooltip = { valueFormatter: money };
                    }
                });
                return opt;
            };

            // A chart whose marks may carry an href: clicking opens the transactions behind it.
            // The ECharts instance and its option live in the closure, not on `this`:
            // Alpine would wrap them in reactive proxies, which ECharts and cloning choke on.
            Alpine.data('echart', (initialOption, key) => {
                let inst = null;
                let opt = initialOption;

                // Ranked (horizontal) bars take their height from the row count: no dead gaps.
                const fit = (el) => {
                    const rows = opt.yAxis?.[0]?.type === 'category' ? opt.yAxis[0].data.length : 0;
                    if (!rows) return;
                    el.style.height = Math.max(120, rows * 40 + 8) + 'px';
                    inst.resize();
                };

                return {
                    init() {
                        inst = echarts.init(this.$el, 'billet', { renderer: 'svg' });
                        inst.setOption(dress(opt));
                        fit(this.$el);
                        inst.on('click', (p) => {
                            const href = p.data?.href ?? opt.series?.[p.seriesIndex]?.hrefs?.[p.dataIndex];
                            if (href) Livewire.navigate(href);
                        });
                        window.addEventListener('resize', () => inst.resize());
                        window.addEventListener('charts-updated', (e) => {
                            if (!e.detail || !e.detail[key]) return;
                            opt = e.detail[key];
                            inst.setOption(dress(opt), true);
                            fit(this.$el);
                        });
                    },
                };
            });

            // Headline numerals roll from their previous value when the period changes.
            const last = (window.__rolled ??= {});
            const still = matchMedia('(prefers-reduced-motion: reduce)').matches;
            Alpine.data('roll', (value, decimals, key) => ({
                init() {
                    const fmt = new Intl.NumberFormat('fr-CH', { minimumFractionDigits: decimals, maximumFractionDigits: decimals });
                    const from = last[key];
                    last[key] = value;
                    if (still || from === undefined || from === value) return;
                    const start = performance.now(), dur = 700;
                    const step = (now) => {
                        const k = Math.min(1, (now - start) / dur), e = 1 - Math.pow(1 - k, 4);
                        this.$el.textContent = fmt.format(from + (value - from) * e);
                        if (k < 1) requestAnimationFrame(step);
                    };
                    requestAnimationFrame(step);
                },
            }));
        });
    </script>
</head>
<body>
    @php $r = request()->path(); @endphp
    <nav class="nav">
        <a class="brand" href="/budget" wire:navigate>Budget<span>Épargne</span></a>
        <a class="link {{ $r === 'budget' ? 'active' : '' }}" href="/budget" wire:navigate>Tableau de bord</a>
        <a class="link {{ str_starts_with($r, 'budget/transactions') ? 'active' : '' }}" href="/budget/transactions" wire:navigate>Transactions</a>
        <a class="link {{ str_starts_with($r, 'budget/import') ? 'active' : '' }}" href="/budget/import" wire:navigate>Importer</a>
        <span class="spacer"></span>
        <a class="link out" href="/cp/collections/categories" target="_blank">Catégories <x-icon name="external" size="14" /></a>
        <a class="link out" href="/cp" target="_blank">Admin <x-icon name="external" size="14" /></a>
    </nav>
    <div class="microtext" aria-hidden="true">{{ str_repeat('Budget Épargne · Franc suisse · Revenus · Dépenses · Épargne · ', 12) }}</div>

    <main class="wrap">
        {{ $slot }}
    </main>

    <div x-data="{ msg: '' }"
         @notify.window="msg = $event.detail.text; setTimeout(() => msg = '', 2600)"
         x-show="msg" x-transition.opacity.duration.200ms class="toast" style="display:none;" role="status">
        <x-icon name="check" /><span x-text="msg"></span>
    </div>

    @livewireScripts
</body>
</html>
