# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Users

A single user: the owner, working alone at a desktop computer. Sessions are occasional and deliberate: import the latest bank exports, classify what the auto-rules missed, then review where the money went. No shared or household use, and no phone use case.

## Product Purpose

Budget Épargne gives full clarity on spending: where every franc goes, by category and over time. The savings rate is a headline metric, but it is one of several. Success means the owner can answer "where did my money go this month/year, and how does that compare?" without leaving anything unclassified or unexplained.

## Positioning

It's a private, self-hosted tool built around the owner's own bank exports (UBS account CSVs and credit-card CSVs) and their own category tree. It connects to no bank and relies on no third-party aggregator. Data lives only in the owner's database.

## Operating Context

- **Monthly ritual:** export CSVs from the bank, then upload them on the Import page (`/budget/import`) or drop them in `storage/app/imports/` and run `php artisan import:transactions`. Re-imports are de-duplicated by transaction number.
- **Classification:** auto-rules (case-insensitive substrings on merchant/description) run first. The owner then filters and bulk-assigns the rest on `/budget/transactions`. Manual classifications are never overwritten by auto-rules.
- **Review:** the dashboard (`/budget`) shows income, spending, savings, savings rate and the unallocated amount for a chosen year or all time, plus spending-by-category, monthly income/spending/savings and Sankey charts.
- **Category and rule management** happens in the Statamic control panel (`/cp/collections/categories`), not in the app.
- Production (budget.example.com) is the source of truth for data (see `docs/adr/0001-production-is-source-of-truth.md`).

## Capabilities and Constraints

- Stack: Statamic 6 (Solo) + Laravel + Livewire 4 + MySQL. Charts use Apache ECharts. The `/budget` views live in `resources/views/budget` and `resources/views/livewire`.
- Currency: CHF, with Swiss number and date formatting (`27.06.2026`, `-71,45`).
- Category kinds: expense, income, savings. Categories nest through a Parent field. Transactions carry `is_savings` and `is_transfer` flags that exclude them from income and spending.
- **French-only UI.** Copy stays in French, and there are no plans for i18n.
- **Stays inside Statamic CP auth.** The app is gated by the CP login with mandatory 2FA. Category and rule editing stays in the CP.
- There's no public front-end: the site root redirects to `/budget`.

## Brand Commitments

- Name: **Budget Épargne** (wordmark rendered as "Budget" + accented "Épargne").
- Voice: plain, direct French addressed to the user as "vous" (e.g. "Où va votre argent — et combien vous gardez.").

## Evidence on Hand

- `screenshot.png` (dashboard) and `screenshot-2.png` (transactions) show the current UI with amounts masked.
- **Figures are private.** Real amounts, merchants and transaction numbers must never appear in screenshots, demos, docs or committed fixtures. Mask them (`XXX`) or use synthetic data, as the CSV import test does.

## Product Principles

1. **Every franc accounted for.** Unclassified or unallocated money is a problem to surface, not hide.
2. **Classification should be fast.** Auto-rules and bulk actions come first, and manual choices always win.
3. **Private by construction.** The data stays self-hosted, and nothing leaks into shared artifacts.
4. **Built for one person at a desk.** Optimize for dense, scannable desktop use over broad-audience onboarding.
