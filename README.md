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

## Deployment

Production lives at https://budget.example.com (Infomaniak). Pushing to `master` tests and
deploys automatically via GitHub Actions. The CP login requires two-factor authentication. See
[docs/deployment.md](docs/deployment.md).

## Tests

```bash
php artisan test
```

## Layout

`app/Services` holds the core logic (CSV parsing, importer, classifier, reporting); `app/Livewire`

- `resources/views` hold the UI. Content (categories, etc.) is stored in the database via
  [`statamic/eloquent-driver`](https://github.com/statamic/eloquent-driver). Charts use Apache ECharts.
