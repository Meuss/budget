# Budget

A personal-finance tool built on **Statamic (Solo) + Laravel + Livewire + MySQL**. Import bank
CSV exports, classify transactions into a category tree (manually or with auto-rules), and explore
the data through ranked-bar / monthly-bar / Sankey charts focused on your savings rate. Every
figure and chart mark opens the transactions behind it. The UI is in French.

![Dashboard — savings rate, spending by category, monthly income vs. spending vs. savings (synthetic data)](screenshot.png)

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
site path doesn't look like a site folder. The CP login requires two-factor authentication.
Details, secrets and recovery: [docs/deployment.md](docs/deployment.md).

## Tests

```bash
php artisan test
```

## Layout

`app/Services` holds the core logic (CSV parsing, importer, classifier, reporting); `app/Livewire`

- `resources/views` hold the UI. Content (categories, etc.) is stored in the database via
  [`statamic/eloquent-driver`](https://github.com/statamic/eloquent-driver). Charts use Apache ECharts.
