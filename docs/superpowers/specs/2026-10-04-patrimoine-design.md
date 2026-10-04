# Patrimoine — design

Track total wealth over time from balances the owner types in by hand, roughly once a month. Vocabulary: see `CONTEXT.md` (Patrimoine section). Storage decision: `docs/adr/0002-patrimoine-outside-statamic.md`.

## Model

- **Classe**: title (≤ 60 chars, required), description (≤ 160, optional), manual order. Created by the owner; a fresh install has none.
- **Avoir**: title (≤ 60, required), description (≤ 160, optional), manual order within its Classe, exactly one Classe (required), optional **Versement mensuel** (CHF; setting it is what makes the Avoir track Versements), optional **archived on** date.
- **Relevé**: an exact date, unique (several per month allowed, past dates allowed). It holds one line per Avoir active on that date: a balance (may be 0 or negative) and, for Avoirs that track Versements, a Versement (may be negative). A balance is required when the Avoir has a balance in an earlier Relevé; otherwise it may be left empty (the Avoir did not exist yet) and no line is stored. A Relevé needs at least one balance.
- **Active on date D**: not archived, or archived on a date after D. Avoirs have no start date: when back-filling a Relevé from before an Avoir existed, its balance is left empty (entering 0 would turn its opening balance into return).
- An archive date must be after the latest Relevé that holds a balance for the Avoir, so archiving never rewrites a past total.
- Only Avoirs with no balance in any Relevé can be deleted; only empty Classes can be deleted. Otherwise archive.
- CHF only. Assets only (negative balances allowed so a debt could be added later). No yearly caps: reminders go in descriptions.

## Relevé form

- Opened from "Nouveau relevé" (date defaults to today) or by clicking a past Relevé (edit).
- Balances are pre-filled from the closest earlier Relevé; an Avoir with no earlier balance starts empty.
- Versements are pre-filled with Versement mensuel × number of calendar months between the previous Relevé and this one (01.09 → 20.11 = 2); 0 when there is no earlier Relevé, and 0 when a later Relevé exists (its Versement already covers the period; the form says so). Always editable.
- Amounts accept Swiss typing: `12'283`, `12 283,50`, `-1500`.
- Changing the date re-computes the pre-fill.

## Page (`/budget/patrimoine`, nav entry "Patrimoine")

Top to bottom:
1. Latest Relevé as a table: each Classe with its subtotal and its Avoirs, the total, the change since the previous Relevé and that Relevé's date.
2. Total Patrimoine over time (line, real time axis).
3. Stacked area by Classe.
4. Cumulative Versements vs cumulative return, over Avoirs that track Versements. Return for a period = Δ balance − Versement, counted only for Avoirs present in both Relevés.
5. List of past Relevés (date, total); click to edit, delete with confirmation.
6. Collapsible "Gérer les avoirs" panel: create, edit, reorder and delete Classes; create, edit, reorder, archive/unarchive and delete Avoirs. Open by default while there is no Avoir.

## Constraints

- Shares no data with Budget (transactions, Catégories).
- Colours follow DESIGN.md's Denomination Rule: Patrimoine is money kept, so Classes use shades of the 100-franc blue; return uses the 50-franc green.
- The repository is public: tests and docs use synthetic names and amounts only.
