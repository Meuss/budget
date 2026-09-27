# Budget

A personal savings-budget app. Import your bank and credit-card statements, sort every
transaction into categories (by hand or with auto-match rules), and see where your money goes and
how much of it you actually save. Every figure and chart links back to the transactions behind it.

It was built for **UBS** CSV exports (formats detailed in [Bank exports](#bank-exports-ubs)), but
the parsers are small and self-contained, so it can be adapted to any bank's format: fork the repo
and add a parser for your export, then point `TransactionImporter::parserFor()` at it.

![Dashboard — savings rate, spending by category, monthly income vs. spending vs. savings (synthetic data)](screenshot.png)

## How it's built

- **Stack:** [Statamic](https://statamic.com) (Solo) on Laravel, Livewire for the UI, MySQL, and
  Apache ECharts for the ranked-bar, monthly-bar and Sankey charts. The UI is in French.
- **Core logic** lives in `app/Services`: CSV parsers, importer, classifier and reporting. The pages
  are Livewire components in `app/Livewire` with their views in `resources/views`.
- **Categories** are Statamic entries (a nested tree with auto-match terms), stored in the database
  via [`statamic/eloquent-driver`](https://github.com/statamic/eloquent-driver) and edited in the CP.
- **Access:** everything sits behind the Statamic control-panel login, with two-factor
  authentication required.

## Requirements

PHP **8.5**, Composer, MySQL, and **Node 24+ / pnpm 11+**.

## Setup

```bash
composer install
pnpm install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan seed:budget
php artisan serve
```

Create a control-panel user with `php artisan make:user`. Everything is behind the Statamic CP
login (`/cp`); the app lives at `/budget`.

## Usage

- **Import** — drop CSV files in `storage/app/imports/` and run `php artisan import:transactions`,
  or upload them on the in-app Import page. Re-importing is safe (rows are de-duped by transaction
  number).
- **Categories & rules** — managed in the CP at `/cp/collections/categories`: nest via a _Parent_
  field, set a _kind_ (expense / income / savings), and add _auto-match terms_ (case-insensitive
  substrings matched against a transaction's merchant/description).
- **Re-classify** — `php artisan classify:transactions` (unclassified) or `--all` (all non-manual
  rows). Manual classifications are never overwritten. Manageable in the CP directly.
- **Browse & classify** — filter and bulk-assign on the `/budget/transactions` page.

![Transactions — filter, bulk-assign, and auto/manual classification (synthetic data)](screenshot-2.png)

## Bank exports (UBS)

The importer auto-detects which of the two UBS CSV exports a file is, so both can be dropped in
together:

| | Account statement | Credit-card statement |
| --- | --- | --- |
| Parser | `app/Services/UbsCsvParser.php` | `app/Services/CreditCardCsvParser.php` |
| Encoding | UTF-8 with BOM | Windows-1252 (converted to UTF-8) |
| Start | account metadata block, data after the `Date de transaction` header row | `sep=;` hint, then the header row |
| Delimiter | `;` (quoted fields may contain `;`) | `;` |
| Dates | `YYYY-MM-DD` | `DD.MM.YYYY` (purchase date + booking date) |
| Amounts | signed `Débit` / `Crédit` columns, running `Solde` | unsigned CHF `Débit` / `Crédit`; foreign rows keep the original currency and rate in the raw row |
| Dedupe key | `No de transaction` | none in the export: a stable hash of the row (identical same-day rows are kept, numbered) |
| Skipped | lines without a transaction number | pending authorisations (no Débit/Crédit) and the footer totals |

Merchant text (`Description1` / `Texte comptable`) is what auto-match terms are compared against.
The real exports belong in `storage/app/imports/`, which is git-ignored; the tests use small
synthetic exports.

## CI & deployment

A single GitHub Actions workflow (`.github/workflows/deploy.yml`) runs on every push to `master`
(or by hand):

1. **Test**: PHP 8.5, `composer install`, `php artisan test` against in-memory SQLite.
2. **Deploy** (only if the tests pass, in the `production` environment), to shared hosting over SSH:
   `composer install --no-dev` on the runner → `artisan down` → `rsync --delete` of the code →
   `migrate --force` → `optimize` → `please stache:refresh` → `artisan up`.

Server-owned state is never overwritten: `.env`, `users/` (the admin login), `storage/`. Production
is the source of truth for data, so deploys ship code and migrations only
([ADR 0001](docs/adr/0001-production-is-source-of-truth.md)). The SSH key, host, user,
`known_hosts` and site path come from repository secrets, and the deploy refuses to run if the
site path doesn't look like a site folder.
Details, secrets and recovery: [docs/deployment.md](docs/deployment.md).

## Tests

```bash
php artisan test
```
