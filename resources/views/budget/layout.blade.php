<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Budget Épargne' }}</title>
    <script src="https://cdn.jsdelivr.net/npm/echarts@5/dist/echarts.min.js"></script>
    <style>
        :root {
            --bg: #0b1120; --panel: #0f172a; --panel-2: #111c33; --border: #1e293b;
            --text: #e2e8f0; --muted: #94a3b8; --accent: #6366f1;
            --green: #22c55e; --red: #ef4444; --blue: #3b82f6; --amber: #f59e0b;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0; background: var(--bg); color: var(--text);
            font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            font-size: 14px; line-height: 1.5;
        }
        a { color: inherit; text-decoration: none; }
        .nav {
            display: flex; align-items: center; gap: 4px; padding: 0 20px;
            height: 56px; background: var(--panel); border-bottom: 1px solid var(--border);
            position: sticky; top: 0; z-index: 10;
        }
        .nav .brand { font-weight: 700; margin-right: 18px; letter-spacing: .3px; }
        .nav .brand span { color: var(--accent); }
        .nav a.link {
            padding: 8px 14px; border-radius: 8px; color: var(--muted); font-weight: 500;
        }
        .nav a.link:hover { background: var(--panel-2); color: var(--text); }
        .nav a.link.active { background: var(--accent); color: #fff; }
        .nav .spacer { flex: 1; }
        .wrap { max-width: 1200px; margin: 0 auto; padding: 24px 20px 64px; }
        h1 { font-size: 20px; margin: 0 0 4px; }
        .sub { color: var(--muted); margin: 0 0 20px; }
        .grid { display: grid; gap: 16px; }
        .cards { grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); margin-bottom: 20px; }
        .card {
            background: var(--panel); border: 1px solid var(--border); border-radius: 14px; padding: 18px;
        }
        .card .label { color: var(--muted); font-size: 12px; text-transform: uppercase; letter-spacing: .6px; }
        .card .value { font-size: 26px; font-weight: 700; margin-top: 6px; }
        .card .value.green { color: var(--green); }
        .card .value.red { color: var(--red); }
        .card .value.blue { color: var(--blue); }
        .card .value.amber { color: var(--amber); }
        .panel {
            background: var(--panel); border: 1px solid var(--border); border-radius: 14px; padding: 18px;
        }
        .panel h2 { font-size: 14px; margin: 0 0 12px; color: var(--muted); font-weight: 600;
            text-transform: uppercase; letter-spacing: .5px; }
        .chart { width: 100%; }
        .two-col { grid-template-columns: 1fr 1fr; }
        @media (max-width: 880px) { .two-col { grid-template-columns: 1fr; } }
        .seg { display: inline-flex; background: var(--panel-2); border: 1px solid var(--border); border-radius: 10px; padding: 3px; gap: 2px; }
        .seg button {
            background: transparent; border: 0; color: var(--muted); padding: 6px 14px; border-radius: 7px;
            cursor: pointer; font-weight: 600; font-size: 13px;
        }
        .seg button.active { background: var(--accent); color: #fff; }
        input, select, .btn {
            background: var(--panel-2); border: 1px solid var(--border); color: var(--text);
            border-radius: 8px; padding: 8px 10px; font-size: 13px; font-family: inherit;
        }
        input:focus, select:focus { outline: none; border-color: var(--accent); }
        .btn { cursor: pointer; font-weight: 600; }
        .btn:hover { background: var(--border); }
        .btn.primary { background: var(--accent); border-color: var(--accent); color: #fff; }
        .btn.primary:hover { filter: brightness(1.1); }
        .btn.sm { padding: 5px 9px; font-size: 12px; }
        table { width: 100%; border-collapse: collapse; }
        table td:first-child, table th:first-child { user-select: none; }
        th, td { text-align: left; padding: 9px 10px; border-bottom: 1px solid var(--border); vertical-align: middle; }
        th { color: var(--muted); font-size: 11px; text-transform: uppercase; letter-spacing: .5px; font-weight: 600; }
        tr:hover td { background: rgba(99,102,241,.05); }
        tr.row-selected td { background: rgba(99,102,241,.14); }
        input[type=checkbox] { width: 16px; height: 16px; accent-color: var(--accent); cursor: pointer; }
        .num { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
        .neg { color: var(--red); } .pos { color: var(--green); }
        .pill { display: inline-block; padding: 2px 8px; border-radius: 999px; font-size: 11px; font-weight: 600; }
        .pill.savings { background: rgba(59,130,246,.18); color: #93c5fd; }
        .pill.none { background: rgba(148,163,184,.15); color: var(--muted); }
        .muted { color: var(--muted); }
        .filters { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; margin-bottom: 14px; }
        .filters .grow { flex: 1; min-width: 180px; }
        .bulkbar { display: flex; gap: 10px; align-items: center; flex-wrap: wrap;
            background: var(--panel-2); border: 1px solid var(--border); border-radius: 10px; padding: 10px 14px; margin-bottom: 14px; }
        .toast { position: fixed; bottom: 20px; right: 20px; background: var(--accent); color: #fff;
            padding: 12px 18px; border-radius: 10px; font-weight: 600; box-shadow: 0 8px 30px rgba(0,0,0,.4); }
        .dropzone { border: 2px dashed var(--border); border-radius: 14px; padding: 36px; text-align: center; color: var(--muted); }
        .small { font-size: 12px; }
        .cat-select { min-width: 150px; }
    </style>
    @livewireStyles
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('echart', (initialOption, key) => ({
                inst: null,
                init() {
                    this.inst = echarts.init(this.$el);
                    this.inst.setOption(initialOption);
                    window.addEventListener('resize', () => this.inst && this.inst.resize());
                    window.addEventListener('charts-updated', (e) => {
                        if (e.detail && e.detail[key]) this.inst.setOption(e.detail[key], true);
                    });
                },
            }));
        });
    </script>
</head>
<body>
    @php $r = request()->path(); @endphp
    <nav class="nav">
        <div class="brand">Budget<span>Épargne</span></div>
        <a class="link {{ $r === 'budget' ? 'active' : '' }}" href="/budget" wire:navigate>Tableau de bord</a>
        <a class="link {{ str_starts_with($r, 'budget/transactions') ? 'active' : '' }}" href="/budget/transactions" wire:navigate>Transactions</a>
        <a class="link {{ str_starts_with($r, 'budget/import') ? 'active' : '' }}" href="/budget/import" wire:navigate>Importer</a>
        <span class="spacer"></span>
        <a class="link" href="/cp/collections/categories" target="_blank">Catégories ↗</a>
        <a class="link" href="/cp" target="_blank">Admin ↗</a>
    </nav>
    <div class="wrap">
        {{ $slot }}
    </div>

    <div x-data="{ msg: '' }"
         @notify.window="msg = $event.detail.text; setTimeout(() => msg = '', 2600)"
         x-show="msg" x-transition class="toast" style="display:none;" x-text="msg"></div>

    @livewireScripts
</body>
</html>
