---
name: Budget Épargne
description: A private CHF budget ledger drawn as ninth-series franc notes on a dark vault ground.
colors:
  vault: "#121214"
  vault-panel: "#18181b"
  vault-raised: "#222226"
  rule: "#2a2a30"
  rule-control: "#3b3b43"
  paper: "#ece6d8"
  paper-secondary: "#aca698"
  paper-quiet: "#9a9488"
  income-green: "#3aa384"
  spending-red: "#e2573f"
  spending-red-deep: "#b8493a"
  savings-blue: "#5b8fe0"
  unallocated-copper: "#b8794a"
  act-yellow: "#e9b949"
  act-ink: "#1d1706"
typography:
  headline:
    fontFamily: "Archivo, ui-sans-serif, system-ui, sans-serif"
    fontSize: "30px"
    fontWeight: 750
    lineHeight: 1.1
    letterSpacing: "-0.02em"
    fontVariation: "'wdth' 118"
  figure:
    fontFamily: "Archivo, ui-sans-serif, system-ui, sans-serif"
    fontSize: "32px"
    fontWeight: 700
    lineHeight: 1
    letterSpacing: "-0.02em"
    fontFeature: "'tnum'"
    fontVariation: "'wdth' 118"
  body:
    fontFamily: "Archivo, ui-sans-serif, system-ui, sans-serif"
    fontSize: "14px"
    fontWeight: 400
    lineHeight: 1.5
    fontFeature: "'tnum'"
  label:
    fontFamily: "Archivo, ui-sans-serif, system-ui, sans-serif"
    fontSize: "12px"
    fontWeight: 600
    letterSpacing: "0.09em"
    fontVariation: "'wdth' 78"
  small:
    fontFamily: "Archivo, ui-sans-serif, system-ui, sans-serif"
    fontSize: "12.5px"
    fontWeight: 400
    lineHeight: 1.5
rounded:
  base: "5px"
  inner: "3px"
spacing:
  xs: "8px"
  sm: "12px"
  md: "16px"
  lg: "24px"
components:
  button:
    backgroundColor: "{colors.vault-raised}"
    textColor: "{colors.paper}"
    rounded: "{rounded.base}"
    padding: "8px 11px"
  button-primary:
    backgroundColor: "{colors.act-yellow}"
    textColor: "{colors.act-ink}"
    rounded: "{rounded.base}"
    padding: "8px 11px"
  button-ghost:
    backgroundColor: "transparent"
    textColor: "{colors.paper-secondary}"
    rounded: "{rounded.base}"
    padding: "6px 10px"
  input:
    backgroundColor: "{colors.vault-raised}"
    textColor: "{colors.paper}"
    rounded: "{rounded.base}"
    padding: "8px 11px"
  panel:
    backgroundColor: "{colors.vault-panel}"
    textColor: "{colors.paper}"
    rounded: "{rounded.base}"
    padding: "18px 20px 20px"
  denomination-field:
    textColor: "{colors.paper}"
    rounded: "{rounded.base}"
    padding: "16px 18px 18px"
  toast:
    backgroundColor: "{colors.vault-raised}"
    textColor: "{colors.paper}"
    rounded: "{rounded.base}"
    padding: "12px 16px"
---

# Design System: Budget Épargne

Scope: the Livewire front-end under `/budget` (layout, dashboard, transactions, import). The Statamic control panel and its login are outside this system.

## Overview

**Creative North Star: "Billet de banque"**

Every money role is printed as one of the Swiss ninth-series franc notes: the 50 green for money in, the 20 red for money out, the 100 blue for money kept, the 200 copper for what is left on the account, and the 10 yellow for whatever you can act on. The notes sit on a dark vault ground and are drawn in note-paper ink, with fine intaglio line work, hairline rules, and microtext security strips instead of cards, shadows and gradients. The figures carry the weight: wide, bold tabular numerals, with small print set in condensed tracked caps.

The system is dense, desktop-first and dark-only. It was chosen for the use scene: occasional evening sessions in a dim room, one person at a desk (`color-scheme: dark`, and no light theme). Every figure is cashable: a KPI field, a bar or a Sankey node opens the filtered transactions behind it. Headline numerals roll to their new value when the period changes.

It rejects the generic navy-card dashboard with an indigo accent, and it rejects decorative colour. A hue always stands for a money role.

**Key Characteristics:**
- Dark vault ground with three tonal steps and hairline rules. No shadows.
- Five note colours bound to money roles across charts, KPI fields and transaction amounts.
- Yellow is reserved for actionable things: active station, primary button, focus, unclassified to-dos.
- Archivo variable, used at two widths: wide (118%) for numerals and titles, condensed (78%) for tracked caps.
- Intaglio hatch on denomination fields and microtext strips under the nav and every panel head.
- Figures are links, and numerals roll on period change (700ms, ease-out quart, disabled under reduced motion).

## Colors

A neutral vault ground and warm paper ink, with five saturated note colours that each belong to exactly one money role.

### Primary
- **Ten-Franc Yellow** (act-yellow): the action colour. It marks the active nav station and period tab (a 2px underline), the primary button, focus rings, text selection, checkboxes, the import dropzone icon and hover, the toast icon, and every unclassified item (the "Non classées" field, the to-do category picker, and the unclassified node in charts). Its ink partner **Act Ink** (act-ink) is used for text on yellow fills.

### Secondary (money roles)
- **Fifty-Franc Green** (income-green): money in. Income series, positive amounts, the Revenus field, and the import success banner.
- **Twenty-Franc Red** (spending-red): money out. Spending series, negative amounts, the Dépenses field, and ranked spending bars. **Twenty Deep** (spending-red-deep) is one step darker and is used only for sub-category nodes in the Sankey.
- **Hundred-Franc Blue** (savings-blue): money kept. Savings series, savings rows in the ledger, the Épargne and savings-rate fields, the "kept" toggle, and the accented "Épargne" in the wordmark.

### Tertiary
- **Two-Hundred Copper** (unallocated-copper): money left on the account. It is used only for the Non alloué field and its Sankey node.

### Neutral
- **Vault** (vault): the page ground, the sticky nav and the microtext strip.
- **Vault Panel** (vault-panel): panels, table heads, the bulk bar.
- **Vault Raised** (vault-raised): controls, tooltips, the toast.
- **Hairline** (rule): every divider, table rule, panel border and chart split line.
- **Control Rule** (rule-control): borders on inputs, buttons, the dropzone and the toast.
- **Note Paper** (paper): primary ink.
- **Paper Secondary** (paper-secondary): secondary text, inactive nav and tabs, table heads, chart legend.
- **Paper Quiet** (paper-quiet): hints, placeholders, axis labels. It is kept at 4.5:1 or better on vault-raised.

### Named Rules
**The Denomination Rule.** Colour is bound to money role everywhere: charts, KPI fields, transaction amounts and toggles. Savings rows read blue even though they are debits, and transfers read muted paper because they are neither income nor spending. Do not use a note colour for decoration or to mean anything else.

**The Ten-Franc Rule.** Yellow means "you can act here". It goes on the active station, the primary action, focus, and unclassified to-dos, and never on a figure that is simply informational.

**The Tint-From-The-Note Rule.** Role surfaces are mixed from their note colour into the vault in OKLab. For example, fills use 9% (13% on hover), borders use 26% (60% on hover), and labels mix 45% note into paper. Do not introduce a separate hex for a tinted state.

**The Verified Palette Rule.** The green, red and blue chart trio (income-green, spending-red, savings-blue) was checked for colour-vision deficiency on the dark surface (deutan ΔE 9.6). Any substitute must pass the same check on the vault before it ships.

## Typography

**Display / Numeral Font:** Archivo variable at width 118% (self-hosted `/fonts/archivo-latin.woff2`, weight 100–900, width 62–125%)
**Body Font:** Archivo at width 100% (with ui-sans-serif, system-ui fallback)
**Label Font:** Archivo at width 78%, uppercase and tracked

**Character:** A single grotesque stretched in two directions, like the lettering on a banknote. Wide and heavy for the denomination, narrow and spaced for the small print.

### Hierarchy
- **Figure** (700, 32px, 28px under 720px, line-height 1, wide): headline numerals in denomination fields, coloured by their note. Units follow as 12px tracked caps.
- **Headline** (750, 30px, 26px under 720px, line-height 1.1, wide, balanced wrap): one page title per view.
- **Wordmark** (750, 16px, wide): "Budget" in paper and "Épargne" in savings-blue.
- **Body** (400, 14px, line-height 1.5, tabular numerals): base text. Subtitles are paper-secondary, capped at 72ch, and emphasise key terms with paper at 600 weight rather than italics.
- **Label** (600, 12px, 0.09em tracking, uppercase, condensed): panel heads, field labels, table heads, units, and the auto/manuel marks (11px).
- **Small** (12.5px): dates, compact buttons, chips, metadata.
- **Microtext** (600, 5.5px, 0.32em tracking, condensed caps): security strips only, at near-ground contrast. This is texture, not content, so it is always `aria-hidden` or rendered as alt-empty generated content.

### Named Rules
**The Two-Widths Rule.** Numerals, titles, tab labels and amounts are set wide (118%). Small print is set condensed (78%) in tracked caps. Body text stays at normal width.

**The Tabular Rule.** All numbers use tabular figures, set right-aligned in tables, with Swiss formatting (space-grouped thousands, comma decimals, CHF after the figure).

## Layout

The layout is a single centred column (max 1240px, padded 36px 24px 80px, and 24px 16px 64px under 720px). A 56px sticky nav sits on top with a 9px sticky microtext strip below it. Each page opens with a head row: the title and subtitle on the left and controls on the right (period tabs, flex-wrapped). Denomination fields run across in an auto-fit grid (min 190px, 12px gap). Panels follow in a 16px grid. The two-column row splits 5fr / 7fr, top-aligned, and collapses to one column under 960px.

Spacing uses a rhythm of 8, 12, 16 and 24px. Ranked horizontal bar charts size to their row count (40px per row plus 8, with a 120px minimum), so there are no dead gaps. Wide tables scroll horizontally inside their panel (min 960px). Under 720px the nav scrolls horizontally, fades out at its right edge, and hides its external links. The product is desktop-first, but mobile must still work.

## Elevation & Depth

The system is flat. Depth comes only from tonal steps (vault, vault-panel, vault-raised) and hairline borders. Chart tooltips explicitly set `box-shadow: none`. The only box-shadow in the system is the focus halo on inputs (3px, act-yellow at 22%), and that is a state signal rather than elevation.

### Named Rules
**The Hairline Rule.** Separate surfaces with a 1px rule or a tonal step, never with a shadow.

## Shapes

All surfaces use one gentle radius (5px): fields, panels, controls, chips, toast and tooltip. Small inner hit targets use 3px. Active underlines have 2px top-rounded caps. Bars have 3px ends on ranked bars and 2px tops on monthly bars. Engraving is drawn as a 118° repeating hatch (1px line every 5–6px). On denomination fields it is masked to strengthen toward the right edge.

Icons come from one authored set: 16px grid, 1.6 stroke, round caps and joins, `currentColor`, `aria-hidden`. The set covers external, arrow-right, arrow-left, check, upload, file, close, search and alert. New glyphs are drawn to the same grid and stroke.

## Components

### Buttons
- **Shape:** gentle corners (5px), 1px control-rule border, 600 weight, 13px, with an inline icon gap of 6px.
- **Default:** vault-raised fill. Hover lifts the fill one step darker and brightens the border.
- **Primary:** act-yellow fill with act-ink text. Hover brightens to a lighter yellow. This is the only filled yellow control, used for the one committing action (import, apply to selection).
- **Ghost:** transparent, in paper-secondary. Hover shows a vault-raised fill with paper text.
- **Kept:** a savings-blue tinted toggle that marks a row as savings.
- **Small:** 6px 10px padding at 12.5px. Disabled buttons drop to 42% opacity.

### Chips
- **Style:** active filter tokens with a tinted fill and border, 12.5px at 600 weight, and a close button (3px radius, faint white hover). The tint follows the Tint-From-The-Note Rule. Current chips are spending-tinted because category drill-downs come from spending.

### Cards / Containers
- **Panel:** vault-panel fill, hairline border, 5px radius, 18px 20px 20px padding, no shadow. Every panel head is a condensed caps label with an optional quiet hint on the right and a microtext security line 12px below it.
- **Bulk bar:** a panel-toned strip holding counts (wide 700), hairline separators and compact buttons.

### Inputs / Fields
- **Style:** vault-raised fill, 1px control-rule border, 5px radius, 8px 11px padding. Selects use the authored chevron.
- **Focus:** the border turns act-yellow with a 3px act-yellow halo at 22%. Global `:focus-visible` is a 2px act-yellow outline with a 2px offset.
- **To-do picker:** an unclassified row's category select wears a yellow tint and border.
- **Error:** text in a light spending tone. There is no red-bordered field state.

### Navigation
- The wordmark sits on the left, followed by stations in paper-secondary at 500 weight (paper on hover and active). The active station has a 2px act-yellow underline flush to the bar's bottom. External CP links sit on the right with the external glyph. The period tabs repeat the same station pattern (wide 600 labels over a hairline, yellow underline, `role="tab"`).

### Denomination Field (signature)
Headline figures. Each field is tinted by its note colour through `--note`, carries the 118° intaglio hatch masked toward its right edge, and shows a caps label above a large wide numeral in the note colour. When a field links to transactions, hovering or focusing it deepens the tint and slides in an arrow glyph. Neutral counts use the plain variant in paper. Numerals roll from their previous value on period change (700ms, ease-out quart), and not under reduced motion.

### Charts
ECharts draw through a single "billet" theme read from the CSS tokens: transparent ground, no axis lines or ticks, dashed hairline split lines, paper-quiet axis labels, 10px rounded-square legend keys, and flat vault-raised tooltips with money-formatted values. Series colours come from the money-role constants that mirror the tokens. Every mark that represents a filterable set carries an href, and clicking it opens Transactions filtered to that set.

### Ledger Table
Hairline rows with a faint hover. Selected rows get a yellow tint. Amounts are right-aligned, wide 600, and coloured by role: income green, spending red, savings blue, transfers muted. Classification provenance appears as small caps marks (auto, manuel).

### Toast
A fixed element at the bottom right: vault-raised, control-rule border, a yellow check glyph, `role="status"`. It fades over 200ms and dismisses after 2.6s.

## Do's and Don'ts

### Do:
- **Do** bind every hue to a money role: income-green in, spending-red out, savings-blue kept, unallocated-copper left over, act-yellow actionable.
- **Do** make every figure cashable. A new KPI, bar or node that stands for a set of transactions links to that filtered set.
- **Do** mix tinted states from the note colour into the vault with `color-mix(in oklab, …)`.
- **Do** set figures wide (118%) and bold with tabular numerals, and small print as condensed (78%) tracked caps.
- **Do** separate surfaces with hairlines and tonal steps, and keep one 5px radius.
- **Do** draw new icons on the 16px, 1.6-stroke grid.
- **Do** honour `prefers-reduced-motion`: no transitions and no numeral roll.

### Don't:
- **Don't** add shadows, glows or gradients for depth. Tooltips and the toast stay flat.
- **Don't** use act-yellow for decoration or for informational figures. It is only for what you can act on.
- **Don't** use a note colour outside its money role, and don't introduce an extra accent (no indigo, no navy cards).
- **Don't** add a light theme. The system is dark-only by design.
- **Don't** ship raster imagery in `/budget`. The world is drawn from type, rule and hatch.
- **Don't** put real amounts, merchants or transaction numbers in screenshots or fixtures. Mask them or use synthetic data.
