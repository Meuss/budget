# Patrimoine Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** A `/budget/patrimoine` page where the owner keeps Classes and Avoirs, enters dated Relevés of their balances (and Versements), and sees the latest totals plus their evolution in charts.

**Architecture:** Four plain Eloquent tables (`patrimoine_*`), independent of transactions and Statamic. Write logic (pre-fill, save, amount parsing) lives in `App\Services\PatrimoineService`; read logic (latest table, ECharts options) in `App\Services\PatrimoineReport`, mirroring `BudgetReport`. Two Livewire components: the page (`Patrimoine`) and the collapsible management panel (`PatrimoineAvoirs`), which tells the page to refresh via an `avoirs-changed` event.

**Tech Stack:** Laravel + Livewire 4 (Statamic 6 host app), MySQL in prod / in-memory SQLite in tests, PHPUnit (`php artisan test`), Apache ECharts 5 via the existing Alpine `echart(option, key)` helper in `resources/views/budget/layout.blade.php`.

**Spec:** `docs/superpowers/specs/2026-10-04-patrimoine-design.md` (vocabulary in `CONTEXT.md`, storage decision in `docs/adr/0002-patrimoine-outside-statamic.md`).

## Global Constraints

- Work on a branch (`git switch -c feat/patrimoine`). Every push to `master` deploys to production.
- UI copy is French. Code identifiers are English, except the domain nouns from `CONTEXT.md`: `Classe`, `Avoir`, `Releve`, `ReleveLigne`, `versement`, `versement_mensuel`.
- Patrimoine shares no tables or services with transactions or Catégories. It only reuses the layout, CSS classes and the `echart` helper.
- CHF only. Money columns are decimals in francs: balance `decimal(14,2)`, versement and versement mensuel `decimal(12,2)`.
- Titles are at most 60 characters and descriptions at most 160.
- Colours follow DESIGN.md's Denomination Rule. Classes use shades of the 100-franc blue (`BudgetReport::SAVINGS`), and return uses the 50-franc green (`BudgetReport::INCOME`). Use no other note colour.
- The repository is public. Fixtures, docs and commit messages use only synthetic names and amounts (`Compte courant`, `Compte titres`, round numbers).
- Migrations must run on both SQLite (tests) and MySQL (prod). Avoid driver-specific SQL.
- Commit messages follow the repo's gitmoji style (`:sparkles: …`, `:white_check_mark: …`).

## Review Focus

1. **Swiss-formatted amounts** (`12'283`, `12 283,50`, `-1500`, `abc`, `12,283`). Valid input is parsed. Invalid input shows a field error and never saves a wrong number. Covered by `parseAmount` tests (Task 2) and a page test (Task 5).
2. **A second Relevé on an existing date**, whether created or reached by editing another Relevé's date. This shows a "déjà un relevé" validation error, not a SQL exception (Task 5).
3. **A tracked Avoir that first appears mid-history.** Its whole opening balance must not count as return; only periods where it is in both Relevés count (Task 3).
4. **An Avoir archived on D.** It is absent from Relevés on or after D, but still present when editing an earlier Relevé (Task 2).
5. **Deleting a Classe that has Avoirs, or an Avoir that has balances.** This is refused with a toast and nothing is deleted; there is no 500 (Task 4).

---

### Task 1: Schema and models

**Files:**
- Create: `database/migrations/2026_10_04_000001_create_patrimoine_tables.php`
- Create: `app/Models/Classe.php`, `app/Models/Avoir.php`, `app/Models/Releve.php`, `app/Models/ReleveLigne.php`
- Create: `tests/Concerns/PatrimoineFixtures.php`
- Test: `tests/Feature/Patrimoine/ModelsTest.php`

**Interfaces:**
- Produces:
  - `Classe` (table `patrimoine_classes`: `id, title, description?, position`). `avoirs(): HasMany` is ordered by position.
  - `Avoir` (table `patrimoine_avoirs`: `id, classe_id, title, description?, position, versement_mensuel? (decimal:2), archived_on? (date)`):
    - `classe(): BelongsTo` and `lignes(): HasMany`
    - `suitLesVersements(): bool`
    - `scopeActiveOn($query, string $date)`, i.e. `archived_on` is null or later than `$date` (`Y-m-d`)
  - `Releve` (table `patrimoine_releves`: `id, date (date, unique)`). `lignes(): HasMany`.
  - `ReleveLigne` (table `patrimoine_releve_lignes`: `id, releve_id, avoir_id, balance (decimal:2), versement? (decimal:2)`, unique on `releve_id + avoir_id`). Has `releve()` and `avoir()`.
  - The trait `Tests\Concerns\PatrimoineFixtures` provides `makeHoldings(): array{liq: Classe, prev: Classe, courant: Avoir, titres: Avoir}` and `makeReleve(string $date, array $lines): Releve`, where `$lines` is `[avoirId => [balance, versement|null]]`.

- [ ] **Step 1: Create the branch**

```bash
git switch -c feat/patrimoine
```

- [ ] **Step 2: Write the fixtures trait and the failing test**

`tests/Concerns/PatrimoineFixtures.php`:

```php
<?php

namespace Tests\Concerns;

use App\Models\Avoir;
use App\Models\Classe;
use App\Models\Releve;

/** Synthetic Patrimoine data: the repository is public, never use real figures. */
trait PatrimoineFixtures
{
    protected function makeHoldings(): array
    {
        $liq = Classe::create(['title' => 'Liquidités', 'position' => 0]);
        $prev = Classe::create(['title' => 'Prévoyance', 'description' => 'Bloqué', 'position' => 1]);
        $courant = Avoir::create(['classe_id' => $liq->id, 'title' => 'Compte courant', 'position' => 0]);
        $titres = Avoir::create(['classe_id' => $prev->id, 'title' => 'Compte titres', 'position' => 0, 'versement_mensuel' => '300.00']);

        return compact('liq', 'prev', 'courant', 'titres');
    }

    /** @param array<int, array{0: string|float, 1: string|float|null}> $lines avoirId => [balance, versement] */
    protected function makeReleve(string $date, array $lines): Releve
    {
        $releve = Releve::create(['date' => $date]);
        foreach ($lines as $avoirId => [$balance, $versement]) {
            $releve->lignes()->create(['avoir_id' => $avoirId, 'balance' => $balance, 'versement' => $versement]);
        }

        return $releve->load('lignes');
    }
}
```

`tests/Feature/Patrimoine/ModelsTest.php`:

```php
<?php

namespace Tests\Feature\Patrimoine;

use App\Models\Avoir;
use App\Models\ReleveLigne;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\PatrimoineFixtures;
use Tests\TestCase;

class ModelsTest extends TestCase
{
    use PatrimoineFixtures, RefreshDatabase;

    public function test_relations_and_versement_tracking(): void
    {
        ['liq' => $liq, 'courant' => $courant, 'titres' => $titres] = $this->makeHoldings();

        $this->assertSame(['Compte courant'], $liq->avoirs->pluck('title')->all());
        $this->assertSame('Liquidités', $courant->classe->title);
        $this->assertFalse($courant->suitLesVersements());
        $this->assertTrue($titres->suitLesVersements());
    }

    public function test_active_on_excludes_avoirs_archived_on_or_before_the_date(): void
    {
        ['courant' => $courant, 'titres' => $titres] = $this->makeHoldings();
        $titres->update(['archived_on' => '2026-03-01']);

        $this->assertEqualsCanonicalizing([$courant->id, $titres->id], Avoir::activeOn('2026-02-28')->pluck('id')->all());
        $this->assertSame([$courant->id], Avoir::activeOn('2026-03-01')->pluck('id')->all());
    }

    public function test_deleting_a_releve_deletes_its_lines_but_an_avoir_with_lines_cannot_be_deleted(): void
    {
        ['courant' => $courant] = $this->makeHoldings();
        $releve = $this->makeReleve('2026-01-15', [$courant->id => ['1000', null]]);

        try {
            $courant->delete();
            $this->fail('An Avoir with balances must not be deletable.');
        } catch (QueryException) {
            $this->assertNotNull($courant->fresh());
        }

        $releve->delete();
        $this->assertSame(0, ReleveLigne::count());
    }

    public function test_releve_dates_are_unique(): void
    {
        $this->makeReleve('2026-01-15', []);

        $this->expectException(QueryException::class);
        $this->makeReleve('2026-01-15', []);
    }
}
```

- [ ] **Step 3: Run the test to check that it fails**

Run: `php artisan test --filter=ModelsTest`
Expected: FAIL with `Class "App\Models\Classe" not found`.

- [ ] **Step 4: Write the migration**

`database/migrations/2026_10_04_000001_create_patrimoine_tables.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Patrimoine: manually entered balances, independent of transactions (docs/adr/0002).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patrimoine_classes', function (Blueprint $table) {
            $table->id();
            $table->string('title', 60);
            $table->string('description', 160)->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('patrimoine_avoirs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('classe_id')->constrained('patrimoine_classes')->restrictOnDelete();
            $table->string('title', 60);
            $table->string('description', 160)->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->decimal('versement_mensuel', 12, 2)->nullable(); // set = this Avoir tracks Versements
            $table->date('archived_on')->nullable();                  // left out of Relevés from this date
            $table->timestamps();
        });

        Schema::create('patrimoine_releves', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique();
            $table->timestamps();
        });

        Schema::create('patrimoine_releve_lignes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('releve_id')->constrained('patrimoine_releves')->cascadeOnDelete();
            $table->foreignId('avoir_id')->constrained('patrimoine_avoirs')->restrictOnDelete();
            $table->decimal('balance', 14, 2);
            $table->decimal('versement', 12, 2)->nullable(); // null = the Avoir did not track Versements
            $table->timestamps();

            $table->unique(['releve_id', 'avoir_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patrimoine_releve_lignes');
        Schema::dropIfExists('patrimoine_releves');
        Schema::dropIfExists('patrimoine_avoirs');
        Schema::dropIfExists('patrimoine_classes');
    }
};
```

- [ ] **Step 5: Write the models**

`app/Models/Classe.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A Patrimoine Classe: an owner-defined group of Avoirs (see CONTEXT.md). */
class Classe extends Model
{
    protected $table = 'patrimoine_classes';

    protected $guarded = [];

    public function avoirs(): HasMany
    {
        return $this->hasMany(Avoir::class)->orderBy('position')->orderBy('id');
    }
}
```

`app/Models/Avoir.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** One place that holds wealth and has a balance (see CONTEXT.md). */
class Avoir extends Model
{
    protected $table = 'patrimoine_avoirs';

    protected $guarded = [];

    protected $casts = [
        'classe_id' => 'integer', // strict comparisons must hold on MySQL as on SQLite
        'versement_mensuel' => 'decimal:2',
        'archived_on' => 'date',
    ];

    public function classe(): BelongsTo
    {
        return $this->belongsTo(Classe::class);
    }

    public function lignes(): HasMany
    {
        return $this->hasMany(ReleveLigne::class);
    }

    public function suitLesVersements(): bool
    {
        return $this->versement_mensuel !== null;
    }

    /** Avoirs that belong in a Relevé dated $date (Y-m-d): archived ones drop out from their archive date. */
    public function scopeActiveOn($query, string $date)
    {
        return $query->where(fn ($q) => $q->whereNull('archived_on')->orWhere('archived_on', '>', $date));
    }
}
```

`app/Models/Releve.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** The balance of every active Avoir on one date (see CONTEXT.md). */
class Releve extends Model
{
    protected $table = 'patrimoine_releves';

    protected $guarded = [];

    protected $casts = [
        'date' => 'date',
    ];

    public function lignes(): HasMany
    {
        return $this->hasMany(ReleveLigne::class);
    }
}
```

`app/Models/ReleveLigne.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One Avoir's balance (and Versement, if it tracks them) in a Relevé. */
class ReleveLigne extends Model
{
    protected $table = 'patrimoine_releve_lignes';

    protected $guarded = [];

    protected $casts = [
        'avoir_id' => 'integer',
        'balance' => 'decimal:2',
        'versement' => 'decimal:2',
    ];

    public function releve(): BelongsTo
    {
        return $this->belongsTo(Releve::class);
    }

    public function avoir(): BelongsTo
    {
        return $this->belongsTo(Avoir::class);
    }
}
```

- [ ] **Step 6: Run the tests to check that they pass**

Run: `php artisan test --filter=ModelsTest`
Expected: 4 passed. If the FK test does not throw on SQLite, check that `config/database.php` has `'foreign_key_constraints' => env('DB_FOREIGN_KEYS', true)` for sqlite. That is Laravel's default; do not disable it.

- [ ] **Step 7: Commit**

```bash
git add database/migrations/2026_10_04_000001_create_patrimoine_tables.php app/Models/Classe.php app/Models/Avoir.php app/Models/Releve.php app/Models/ReleveLigne.php tests/Concerns/PatrimoineFixtures.php tests/Feature/Patrimoine/ModelsTest.php
git commit -m ":card_file_box: Patrimoine tables and models (Classe, Avoir, Relevé)"
```

---

### Task 2: PatrimoineService (amount parsing, pre-fill, save)

**Files:**
- Create: `app/Services/PatrimoineService.php`
- Test: `tests/Feature/Patrimoine/ServiceTest.php`

**Interfaces:**
- Consumes: the Task 1 models, `Avoir::activeOn`, and the `PatrimoineFixtures` trait.
- Produces (`App\Services\PatrimoineService`):
  - `static parseAmount(?string $input): ?string` returns a normalised `"12283.00"`, or null when the input is empty or invalid.
  - `static monthsBetween(CarbonInterface $from, CarbonInterface $to): int` counts calendar months and is never negative.
  - `activeAvoirs(string $date): Collection<Avoir>` returns Avoirs ordered by Classe position, then Avoir position, with `classe` eager-loaded.
  - `draft(string $date, ?Releve $releve = null): array<int, array{balance: string, versement: ?string}>`, keyed by Avoir id in display order. `versement` is null for Avoirs that don't track Versements.
  - `save(string $date, array $balances, array $versements, ?Releve $releve = null): Releve`. The `$balances`/`$versements` arrays are `[avoirId => "123.00"]` and must already be parsed by `parseAmount`.

- [ ] **Step 1: Write the failing test**

`tests/Feature/Patrimoine/ServiceTest.php`:

```php
<?php

namespace Tests\Feature\Patrimoine;

use App\Models\Avoir;
use App\Models\Releve;
use App\Services\PatrimoineService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\PatrimoineFixtures;
use Tests\TestCase;

class ServiceTest extends TestCase
{
    use PatrimoineFixtures, RefreshDatabase;

    public function test_parse_amount_accepts_swiss_typing_and_rejects_the_rest(): void
    {
        $this->assertSame('12283.00', PatrimoineService::parseAmount("12'283"));
        $this->assertSame('12283.50', PatrimoineService::parseAmount('12 283,50'));
        $this->assertSame('12283.00', PatrimoineService::parseAmount("12\u{2019}283"));
        $this->assertSame('-1500.00', PatrimoineService::parseAmount('-1500'));
        $this->assertSame('0.00', PatrimoineService::parseAmount('0'));
        $this->assertNull(PatrimoineService::parseAmount(''));
        $this->assertNull(PatrimoineService::parseAmount('abc'));
        $this->assertNull(PatrimoineService::parseAmount('12,283')); // three decimals: ambiguous, refuse
    }

    public function test_months_between_counts_calendar_months(): void
    {
        $this->assertSame(2, PatrimoineService::monthsBetween(Carbon::parse('2026-09-01'), Carbon::parse('2026-11-20')));
        $this->assertSame(1, PatrimoineService::monthsBetween(Carbon::parse('2026-12-28'), Carbon::parse('2027-01-03')));
        $this->assertSame(0, PatrimoineService::monthsBetween(Carbon::parse('2026-09-01'), Carbon::parse('2026-09-30')));
        $this->assertSame(0, PatrimoineService::monthsBetween(Carbon::parse('2026-11-01'), Carbon::parse('2026-09-01')));
    }

    public function test_active_avoirs_are_ordered_by_classe_then_position(): void
    {
        ['liq' => $liq, 'courant' => $courant, 'titres' => $titres] = $this->makeHoldings();
        $epargne = Avoir::create(['classe_id' => $liq->id, 'title' => 'Épargne', 'position' => 1]);

        $ids = app(PatrimoineService::class)->activeAvoirs('2026-01-01')->pluck('id')->all();

        $this->assertSame([$courant->id, $epargne->id, $titres->id], $ids);
    }

    public function test_first_draft_has_empty_balances_and_zero_versements(): void
    {
        ['courant' => $courant, 'titres' => $titres] = $this->makeHoldings();

        $draft = app(PatrimoineService::class)->draft('2026-01-15');

        $this->assertSame(['balance' => '', 'versement' => null], $draft[$courant->id]);
        $this->assertSame(['balance' => '', 'versement' => '0.00'], $draft[$titres->id]);
    }

    public function test_draft_prefills_from_the_closest_earlier_releve_and_multiplies_versement_mensuel(): void
    {
        ['courant' => $courant, 'titres' => $titres] = $this->makeHoldings();
        $this->makeReleve('2026-06-01', [$courant->id => ['500', null], $titres->id => ['900', '300']]);
        $this->makeReleve('2026-09-01', [$courant->id => ['1000', null], $titres->id => ['2000', '300']]);
        $this->makeReleve('2026-12-01', [$courant->id => ['9999', null], $titres->id => ['9999', '300']]); // later: ignored

        $draft = app(PatrimoineService::class)->draft('2026-11-20');

        $this->assertSame('1000.00', $draft[$courant->id]['balance']);
        $this->assertSame('2000.00', $draft[$titres->id]['balance']);
        $this->assertSame('600.00', $draft[$titres->id]['versement']); // 2 months × 300
    }

    public function test_draft_of_an_existing_releve_shows_its_stored_values(): void
    {
        ['courant' => $courant, 'titres' => $titres] = $this->makeHoldings();
        $releve = $this->makeReleve('2026-09-01', [$courant->id => ['1000', null], $titres->id => ['2000', '-50']]);

        $draft = app(PatrimoineService::class)->draft('2026-09-01', $releve);

        $this->assertSame(['balance' => '1000.00', 'versement' => null], $draft[$courant->id]);
        $this->assertSame(['balance' => '2000.00', 'versement' => '-50.00'], $draft[$titres->id]);
    }

    public function test_archived_avoir_is_left_out_from_its_archive_date_but_kept_before(): void
    {
        ['courant' => $courant, 'titres' => $titres] = $this->makeHoldings();
        $titres->update(['archived_on' => '2026-10-01']);
        $service = app(PatrimoineService::class);

        $this->assertArrayNotHasKey($titres->id, $service->draft('2026-10-01'));
        $this->assertArrayHasKey($titres->id, $service->draft('2026-09-30'));
    }

    public function test_save_creates_then_updates_a_releve(): void
    {
        ['courant' => $courant, 'titres' => $titres] = $this->makeHoldings();
        $service = app(PatrimoineService::class);

        $releve = $service->save('2026-09-01', [$courant->id => '1000.00', $titres->id => '2000.00'], [$titres->id => '300.00']);

        $this->assertSame('2026-09-01', $releve->date->toDateString());
        $this->assertSame(2, $releve->lignes()->count());
        $this->assertNull($releve->lignes()->where('avoir_id', $courant->id)->value('versement'));

        $titres->update(['archived_on' => '2026-09-01']);
        $service->save('2026-09-01', [$courant->id => '1100.00'], [], $releve);

        $this->assertSame(1, Releve::count());
        $this->assertSame([$courant->id], $releve->lignes()->pluck('avoir_id')->all()); // archived line dropped
        $this->assertSame('1100.00', (string) $releve->lignes()->first()->balance);
    }
}
```

- [ ] **Step 2: Run the test to check that it fails**

Run: `php artisan test --filter=ServiceTest`
Expected: FAIL with `Class "App\Services\PatrimoineService" not found`.

- [ ] **Step 3: Write the implementation**

`app/Services/PatrimoineService.php`:

```php
<?php

namespace App\Services;

use App\Models\Avoir;
use App\Models\Releve;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Writing Relevés: which Avoirs belong in one, what the form proposes, and saving it.
 * Amounts travel as decimal strings ("12283.00") so nothing is lost to float rounding.
 */
class PatrimoineService
{
    /** "12'283", "12 283,50", "-1500" → "12283.00"; empty or anything else → null. */
    public static function parseAmount(?string $input): ?string
    {
        $s = str_replace(["'", "\u{2019}", ' ', "\u{00A0}", "\u{202F}"], '', trim((string) $input));
        $s = str_replace(',', '.', $s);

        if (! preg_match('/^-?\d+(\.\d{1,2})?$/', $s)) {
            return null;
        }

        return number_format((float) $s, 2, '.', '');
    }

    /** Calendar months from one date to the next (01.09 → 20.11 = 2): standing orders run once a month. */
    public static function monthsBetween(CarbonInterface $from, CarbonInterface $to): int
    {
        return max(0, ($to->year * 12 + $to->month) - ($from->year * 12 + $from->month));
    }

    /** The Avoirs a Relevé dated $date holds, in display order (Classe, then Avoir). */
    public function activeAvoirs(string $date): Collection
    {
        return Avoir::query()
            ->activeOn($date)
            ->join('patrimoine_classes', 'patrimoine_classes.id', '=', 'patrimoine_avoirs.classe_id')
            ->orderBy('patrimoine_classes.position')->orderBy('patrimoine_classes.id')
            ->orderBy('patrimoine_avoirs.position')->orderBy('patrimoine_avoirs.id')
            ->select('patrimoine_avoirs.*')
            ->with('classe')
            ->get();
    }

    /**
     * What the form shows for a Relevé dated $date: stored values when editing $releve, otherwise
     * balances from the closest earlier Relevé and Versement mensuel × months since it.
     *
     * @return array<int, array{balance: string, versement: ?string}>
     */
    public function draft(string $date, ?Releve $releve = null): array
    {
        $stored = $releve?->lignes()->get()->keyBy('avoir_id') ?? collect();

        $previous = Releve::query()
            ->where('date', '<', $date)
            ->when($releve, fn ($q) => $q->whereKeyNot($releve->id))
            ->orderByDesc('date')
            ->with('lignes')
            ->first();
        $previousLines = $previous?->lignes->keyBy('avoir_id') ?? collect();
        $months = $previous ? self::monthsBetween($previous->date, Carbon::parse($date)) : 0;

        $draft = [];
        foreach ($this->activeAvoirs($date) as $avoir) {
            $line = $stored->get($avoir->id);

            if ($line) {
                $balance = (string) $line->balance;
                $versement = $avoir->suitLesVersements() ? (string) ($line->versement ?? '0.00') : null;
            } else {
                $balance = (string) ($previousLines->get($avoir->id)?->balance ?? '');
                $versement = $avoir->suitLesVersements()
                    ? number_format((float) $avoir->versement_mensuel * $months, 2, '.', '')
                    : null;
            }

            $draft[$avoir->id] = ['balance' => $balance, 'versement' => $versement];
        }

        return $draft;
    }

    /**
     * Create or update a Relevé with one line per Avoir active on $date; lines for Avoirs no longer
     * active on that date (date moved past an archive date) are removed.
     *
     * @param  array<int, string>  $balances  avoirId => parsed amount
     * @param  array<int, string>  $versements  avoirId => parsed amount (tracked Avoirs only)
     */
    public function save(string $date, array $balances, array $versements, ?Releve $releve = null): Releve
    {
        return DB::transaction(function () use ($date, $balances, $versements, $releve) {
            $releve ??= new Releve;
            $releve->date = $date;
            $releve->save();

            $kept = [];
            foreach ($this->activeAvoirs($date) as $avoir) {
                $releve->lignes()->updateOrCreate(['avoir_id' => $avoir->id], [
                    'balance' => $balances[$avoir->id],
                    'versement' => $avoir->suitLesVersements() ? ($versements[$avoir->id] ?? '0.00') : null,
                ]);
                $kept[] = $avoir->id;
            }
            $releve->lignes()->whereNotIn('avoir_id', $kept)->delete();

            return $releve->load('lignes');
        });
    }
}
```

- [ ] **Step 4: Run the tests to check that they pass**

Run: `php artisan test --filter=ServiceTest`
Expected: 8 passed.

- [ ] **Step 5: Commit**

```bash
git add app/Services/PatrimoineService.php tests/Feature/Patrimoine/ServiceTest.php
git commit -m ":sparkles: Patrimoine: Relevé pre-fill, Swiss amount parsing and save"
```

---

### Task 3: PatrimoineReport (latest table and chart options)

**Files:**
- Create: `app/Services/PatrimoineReport.php`
- Test: `tests/Feature/Patrimoine/ReportTest.php`

**Interfaces:**
- Consumes: the Task 1 models, `BudgetReport::SAVINGS` and `BudgetReport::INCOME` (colour constants), and `PatrimoineFixtures`.
- Produces (`App\Services\PatrimoineReport`):
  - `releves(): Collection<Releve>` returns Relevés by ascending date, with `lignes.avoir` loaded.
  - `latest(Collection $releves): ?array` returns `{date: Carbon, total: float, previousDate: ?Carbon, change: ?float, groups: list<{title, description, subtotal: float, avoirs: list<{title, description, balance: float, change: ?float}>}>}`.
  - `totalOption(Collection $releves): array` is an ECharts option for a line on a time axis.
  - `classesOption(Collection $releves): array` is an ECharts option for areas stacked by Classe.
  - `versementsOption(Collection $releves): array` is an ECharts option with two lines: cumulative Versements and cumulative return.
  - `cumulativeVersements(Collection $releves): list<array{0: string, 1: float, 2: float}>` returns `[date, Versements, return]`.
  - `hasVersements(Collection $releves): bool`.

- [ ] **Step 1: Write the failing test**

`tests/Feature/Patrimoine/ReportTest.php`:

```php
<?php

namespace Tests\Feature\Patrimoine;

use App\Models\Avoir;
use App\Services\PatrimoineReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\PatrimoineFixtures;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use PatrimoineFixtures, RefreshDatabase;

    public function test_latest_is_null_without_releves(): void
    {
        $report = app(PatrimoineReport::class);

        $this->assertNull($report->latest($report->releves()));
    }

    public function test_latest_groups_by_classe_with_subtotals_total_and_change(): void
    {
        ['courant' => $courant, 'titres' => $titres] = $this->makeHoldings();
        $this->makeReleve('2026-08-01', [$courant->id => ['1000', null], $titres->id => ['2000', '0']]);
        $this->makeReleve('2026-09-01', [$courant->id => ['1500', null], $titres->id => ['2300', '300']]);
        $report = app(PatrimoineReport::class);

        $latest = $report->latest($report->releves());

        $this->assertSame('2026-09-01', $latest['date']->toDateString());
        $this->assertSame('2026-08-01', $latest['previousDate']->toDateString());
        $this->assertSame(3800.0, $latest['total']);
        $this->assertSame(800.0, $latest['change']);
        $this->assertSame(['Liquidités', 'Prévoyance'], array_column($latest['groups'], 'title'));
        $this->assertSame(1500.0, $latest['groups'][0]['subtotal']);
        $this->assertSame(500.0, $latest['groups'][0]['avoirs'][0]['change']);
    }

    public function test_total_and_classes_options_use_a_time_axis(): void
    {
        ['courant' => $courant, 'titres' => $titres] = $this->makeHoldings();
        $this->makeReleve('2026-08-01', [$courant->id => ['1000', null], $titres->id => ['2000', '0']]);
        $this->makeReleve('2026-09-15', [$courant->id => ['1500', null], $titres->id => ['2300', '300']]);
        $report = app(PatrimoineReport::class);
        $releves = $report->releves();

        $total = $report->totalOption($releves);
        $this->assertSame('time', $total['xAxis'][0]['type']);
        $this->assertSame([['2026-08-01', 3000.0], ['2026-09-15', 3800.0]], $total['series'][0]['data']);

        $classes = $report->classesOption($releves);
        $this->assertSame(['Liquidités', 'Prévoyance'], array_column($classes['series'], 'name'));
        $this->assertSame([['2026-08-01', 1000.0], ['2026-09-15', 1500.0]], $classes['series'][0]['data']);
        $this->assertSame('patrimoine', $classes['series'][1]['stack']);
    }

    public function test_return_only_counts_tracked_avoirs_present_in_both_releves(): void
    {
        ['prev' => $prev, 'courant' => $courant, 'titres' => $titres] = $this->makeHoldings();
        $this->makeReleve('2026-01-15', [$courant->id => ['500', null], $titres->id => ['1000', '0']]);
        // A tracked Avoir that appears only from the second Relevé: its opening balance is not return.
        $nouveau = Avoir::create(['classe_id' => $prev->id, 'title' => 'Nouveau', 'position' => 1, 'versement_mensuel' => '100']);
        $this->makeReleve('2026-03-10', [$courant->id => ['800', null], $titres->id => ['1700', '600'], $nouveau->id => ['5000', '0']]);
        $report = app(PatrimoineReport::class);
        $releves = $report->releves();

        $this->assertSame(
            [['2026-01-15', 0.0, 0.0], ['2026-03-10', 600.0, 100.0]],
            $report->cumulativeVersements($releves),
        );
        $this->assertTrue($report->hasVersements($releves));
    }

    public function test_has_versements_is_false_when_no_line_tracks_them(): void
    {
        ['courant' => $courant] = $this->makeHoldings();
        $this->makeReleve('2026-01-15', [$courant->id => ['500', null]]);
        $report = app(PatrimoineReport::class);

        $this->assertFalse($report->hasVersements($report->releves()));
    }
}
```

- [ ] **Step 2: Run the test to check that it fails**

Run: `php artisan test --filter=ReportTest`
Expected: FAIL with `Class "App\Services\PatrimoineReport" not found`.

- [ ] **Step 3: Write the implementation**

`app/Services/PatrimoineReport.php`:

```php
<?php

namespace App\Services;

use App\Models\Classe;
use App\Models\Releve;
use Illuminate\Support\Collection;

/**
 * Reads Relevés back as the latest-Relevé table and ready-to-use Apache ECharts options.
 * Patrimoine is money kept, so its series are drawn in the 100-franc blue (DESIGN.md, Denomination Rule).
 */
class PatrimoineReport
{
    /** One shade of the 100-franc blue per Classe, cycling when there are more Classes than shades. */
    public const CLASS_SHADES = ['#5b8fe0', '#8fb2ec', '#3f6fb8', '#b9cff4', '#2d5290', '#7f9cc9'];

    public function releves(): Collection
    {
        return Releve::with('lignes.avoir')->orderBy('date')->get();
    }

    protected function total(Releve $releve): float
    {
        return round((float) $releve->lignes->sum('balance'), 2);
    }

    public function latest(Collection $releves): ?array
    {
        $last = $releves->last();
        if (! $last) {
            return null;
        }
        $previous = $releves->count() > 1 ? $releves[$releves->count() - 2] : null;
        $previousLines = $previous?->lignes->keyBy('avoir_id') ?? collect();

        $groups = [];
        foreach (Classe::orderBy('position')->orderBy('id')->get() as $classe) {
            $lines = $last->lignes
                ->filter(fn ($l) => $l->avoir->classe_id === $classe->id)
                ->sortBy(fn ($l) => sprintf('%08d-%08d', $l->avoir->position, $l->avoir_id));
            if ($lines->isEmpty()) {
                continue;
            }

            $groups[] = [
                'title' => $classe->title,
                'description' => $classe->description,
                'subtotal' => round((float) $lines->sum('balance'), 2),
                'avoirs' => $lines->map(fn ($l) => [
                    'title' => $l->avoir->title,
                    'description' => $l->avoir->description,
                    'balance' => (float) $l->balance,
                    'change' => $previousLines->has($l->avoir_id)
                        ? round((float) $l->balance - (float) $previousLines[$l->avoir_id]->balance, 2)
                        : null,
                ])->values()->all(),
            ];
        }

        return [
            'date' => $last->date,
            'total' => $this->total($last),
            'previousDate' => $previous?->date,
            'change' => $previous ? round($this->total($last) - $this->total($previous), 2) : null,
            'groups' => $groups,
        ];
    }

    protected function frame(array $series, bool $legend = false): array
    {
        return [
            'grid' => ['left' => 8, 'right' => 16, 'top' => $legend ? 40 : 16, 'bottom' => 8, 'containLabel' => true],
            'legend' => ['show' => $legend, 'top' => 0, 'left' => 0],
            'tooltip' => ['trigger' => 'axis'],
            'xAxis' => [['type' => 'time']],
            'yAxis' => [['type' => 'value', 'scale' => true]],
            'series' => $series,
        ];
    }

    public function totalOption(Collection $releves): array
    {
        return $this->frame([[
            'name' => 'Patrimoine', 'type' => 'line', 'symbolSize' => 6,
            'color' => BudgetReport::SAVINGS, 'areaStyle' => ['opacity' => 0.12],
            'data' => $releves->map(fn ($r) => [$r->date->toDateString(), $this->total($r)])->values()->all(),
        ]]);
    }

    public function classesOption(Collection $releves): array
    {
        $usedClasses = $releves->flatMap(fn ($r) => $r->lignes->map(fn ($l) => $l->avoir->classe_id))->unique();
        $classes = Classe::whereIn('id', $usedClasses)->orderBy('position')->orderBy('id')->get()->values();

        $series = $classes->map(fn ($classe, $i) => [
            'name' => $classe->title, 'type' => 'line', 'stack' => 'patrimoine', 'showSymbol' => false,
            'color' => self::CLASS_SHADES[$i % count(self::CLASS_SHADES)],
            'lineStyle' => ['width' => 0], 'areaStyle' => ['opacity' => 0.85],
            'data' => $releves->map(fn ($r) => [
                $r->date->toDateString(),
                round((float) $r->lignes->filter(fn ($l) => $l->avoir->classe_id === $classe->id)->sum('balance'), 2),
            ])->values()->all(),
        ])->all();

        $option = $this->frame($series, legend: true);
        $option['yAxis'][0]['scale'] = false; // stacked areas must start at 0

        return $option;
    }

    /**
     * Running totals per Relevé: [date, Versements, return]. For each period, only lines that track
     * Versements and also exist in the previous Relevé count; return = Δ balance − Versement.
     */
    public function cumulativeVersements(Collection $releves): array
    {
        $points = [];
        $versements = 0.0;
        $return = 0.0;
        $previous = null;

        foreach ($releves as $releve) {
            if ($previous) {
                $before = $previous->lignes->keyBy('avoir_id');
                foreach ($releve->lignes as $line) {
                    if ($line->versement === null || ! $before->has($line->avoir_id)) {
                        continue;
                    }
                    $versements += (float) $line->versement;
                    $return += (float) $line->balance - (float) $before[$line->avoir_id]->balance - (float) $line->versement;
                }
            }
            $points[] = [$releve->date->toDateString(), round($versements, 2), round($return, 2)];
            $previous = $releve;
        }

        return $points;
    }

    public function versementsOption(Collection $releves): array
    {
        $points = $this->cumulativeVersements($releves);

        return $this->frame([
            ['name' => 'Versements cumulés', 'type' => 'line', 'symbolSize' => 6, 'color' => BudgetReport::SAVINGS,
                'data' => array_map(fn ($p) => [$p[0], $p[1]], $points)],
            ['name' => 'Rendement cumulé', 'type' => 'line', 'symbolSize' => 6, 'color' => BudgetReport::INCOME,
                'data' => array_map(fn ($p) => [$p[0], $p[2]], $points)],
        ], legend: true);
    }

    public function hasVersements(Collection $releves): bool
    {
        return $releves->contains(fn ($r) => $r->lignes->contains(fn ($l) => $l->versement !== null));
    }
}
```

- [ ] **Step 4: Run the tests to check that they pass**

Run: `php artisan test --filter=ReportTest`
Expected: 5 passed.

- [ ] **Step 5: Commit**

```bash
git add app/Services/PatrimoineReport.php tests/Feature/Patrimoine/ReportTest.php
git commit -m ":chart_with_upwards_trend: Patrimoine report: latest Relevé table and chart options"
```

---

### Task 4: Managing Classes and Avoirs (`PatrimoineAvoirs` component)

**Files:**
- Create: `app/Livewire/PatrimoineAvoirs.php`
- Create: `resources/views/livewire/patrimoine-avoirs.blade.php`
- Test: `tests/Feature/Patrimoine/AvoirsTest.php`

**Interfaces:**
- Consumes: the Task 1 models and `PatrimoineService::parseAmount` (Task 2).
- Produces: the Livewire component `patrimoine-avoirs` (embedded by Task 5), which dispatches the browser/Livewire event `avoirs-changed` after every change. Its public actions are:
  - `saveClasse()`, `editClasse(int)`, `deleteClasse(int)`, `moveClasse(int $id, int $dir)`
  - `saveAvoir()`, `editAvoir(int)`, `deleteAvoir(int)`, `moveAvoir(int $id, int $dir)`
  - `archiveAvoir(int)`, `unarchiveAvoir(int)`, `cancel()`

- [ ] **Step 1: Write the failing test**

`tests/Feature/Patrimoine/AvoirsTest.php`:

```php
<?php

namespace Tests\Feature\Patrimoine;

use App\Livewire\PatrimoineAvoirs;
use App\Models\Avoir;
use App\Models\Classe;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\PatrimoineFixtures;
use Tests\TestCase;

class AvoirsTest extends TestCase
{
    use PatrimoineFixtures, RefreshDatabase;

    public function test_creates_classes_and_avoirs_in_order(): void
    {
        $component = Livewire::test(PatrimoineAvoirs::class)
            ->set('classeTitle', 'Liquidités')->call('saveClasse')
            ->set('classeTitle', 'Prévoyance')->set('classeDescription', 'Bloqué')->call('saveClasse')
            ->assertDispatched('avoirs-changed');

        $prev = Classe::where('title', 'Prévoyance')->first();
        $this->assertSame([0, 1], Classe::orderBy('position')->pluck('position')->all());

        $component
            ->set('avoirClasse', (string) $prev->id)->set('avoirTitle', 'Compte titres')
            ->set('avoirVersementMensuel', "1'000")->call('saveAvoir')
            ->assertHasNoErrors();

        $avoir = Avoir::first();
        $this->assertSame('Compte titres', $avoir->title);
        $this->assertSame('1000.00', (string) $avoir->versement_mensuel);
    }

    public function test_validates_titles_classe_and_versement_mensuel(): void
    {
        Livewire::test(PatrimoineAvoirs::class)
            ->set('classeTitle', str_repeat('x', 61))->call('saveClasse')
            ->assertHasErrors(['classeTitle'])
            ->set('avoirTitle', '')->set('avoirClasse', '')->set('avoirVersementMensuel', 'abc')->call('saveAvoir')
            ->assertHasErrors(['avoirTitle', 'avoirClasse', 'avoirVersementMensuel']);

        $this->assertSame(0, Classe::count());
    }

    public function test_edit_and_clear_versement_mensuel_stops_tracking(): void
    {
        ['titres' => $titres] = $this->makeHoldings();

        Livewire::test(PatrimoineAvoirs::class)
            ->call('editAvoir', $titres->id)
            ->assertSet('avoirTitle', 'Compte titres')
            ->set('avoirVersementMensuel', '')
            ->call('saveAvoir');

        $this->assertFalse($titres->fresh()->suitLesVersements());
    }

    public function test_move_swaps_positions_within_siblings(): void
    {
        ['liq' => $liq, 'prev' => $prev] = $this->makeHoldings();

        Livewire::test(PatrimoineAvoirs::class)->call('moveClasse', $prev->id, -1);

        $this->assertSame([$prev->id, $liq->id], Classe::orderBy('position')->pluck('id')->all());
    }

    public function test_archive_and_unarchive(): void
    {
        ['titres' => $titres] = $this->makeHoldings();

        Livewire::test(PatrimoineAvoirs::class)
            ->set("archiveOn.{$titres->id}", '2026-10-01')->call('archiveAvoir', $titres->id);
        $this->assertSame('2026-10-01', $titres->fresh()->archived_on->toDateString());

        Livewire::test(PatrimoineAvoirs::class)->call('unarchiveAvoir', $titres->id);
        $this->assertNull($titres->fresh()->archived_on);
    }

    public function test_refuses_to_delete_a_classe_with_avoirs_or_an_avoir_with_balances(): void
    {
        ['liq' => $liq, 'courant' => $courant, 'titres' => $titres] = $this->makeHoldings();
        $this->makeReleve('2026-09-01', [$courant->id => ['1000', null]]);

        Livewire::test(PatrimoineAvoirs::class)
            ->call('deleteClasse', $liq->id)->assertDispatched('notify')
            ->call('deleteAvoir', $courant->id)->assertDispatched('notify')
            ->call('deleteAvoir', $titres->id); // no balances: allowed

        $this->assertNotNull($liq->fresh());
        $this->assertNotNull($courant->fresh());
        $this->assertNull($titres->fresh());
    }
}
```

- [ ] **Step 2: Run the test to check that it fails**

Run: `php artisan test --filter=AvoirsTest`
Expected: FAIL with `Class "App\Livewire\PatrimoineAvoirs" not found`.

- [ ] **Step 3: Write the component**

`app/Livewire/PatrimoineAvoirs.php`:

```php
<?php

namespace App\Livewire;

use App\Models\Avoir;
use App\Models\Classe;
use App\Services\PatrimoineService;
use Illuminate\Support\Collection;
use Livewire\Component;

/** The "Gérer les avoirs" panel: Classes and Avoirs, their order, archiving. */
class PatrimoineAvoirs extends Component
{
    public ?int $classeId = null;   // null = creating a new Classe

    public string $classeTitle = '';

    public string $classeDescription = '';

    public ?int $avoirId = null;    // null = creating a new Avoir

    public string $avoirTitle = '';

    public string $avoirDescription = '';

    public string $avoirClasse = '';

    public string $avoirVersementMensuel = '';

    /** Archive date per Avoir id, "Y-m-d" (defaults to today). */
    public array $archiveOn = [];

    public function saveClasse(): void
    {
        $data = $this->validate([
            'classeTitle' => 'required|string|max:60',
            'classeDescription' => 'nullable|string|max:160',
        ]);
        $fields = ['title' => trim($data['classeTitle']), 'description' => trim((string) $data['classeDescription']) ?: null];

        if ($this->classeId) {
            Classe::findOrFail($this->classeId)->update($fields);
        } else {
            Classe::create($fields + ['position' => (Classe::max('position') ?? -1) + 1]);
        }

        $this->cancel();
        $this->changed();
    }

    public function editClasse(int $id): void
    {
        $classe = Classe::findOrFail($id);
        $this->cancel();
        $this->classeId = $classe->id;
        $this->classeTitle = $classe->title;
        $this->classeDescription = (string) $classe->description;
    }

    public function deleteClasse(int $id): void
    {
        $classe = Classe::findOrFail($id);
        if ($classe->avoirs()->exists()) {
            $this->dispatch('notify', text: 'Cette classe contient des avoirs : déplacez-les ou supprimez-les d\'abord.');

            return;
        }
        $classe->delete();
        $this->changed();
    }

    public function moveClasse(int $id, int $dir): void
    {
        $this->move(Classe::orderBy('position')->orderBy('id')->get(), $id, $dir);
    }

    public function saveAvoir(): void
    {
        $data = $this->validate([
            'avoirTitle' => 'required|string|max:60',
            'avoirDescription' => 'nullable|string|max:160',
            'avoirClasse' => 'required|exists:patrimoine_classes,id',
            'avoirVersementMensuel' => ['nullable', function ($attribute, $value, $fail) {
                if (trim((string) $value) !== '' && PatrimoineService::parseAmount($value) === null) {
                    $fail('Montant invalide.');
                }
            }],
        ]);
        // Empty = this Avoir does not track Versements.
        $mensuel = trim($this->avoirVersementMensuel) === '' ? null : PatrimoineService::parseAmount($this->avoirVersementMensuel);

        $fields = [
            'title' => trim($data['avoirTitle']),
            'description' => trim((string) $data['avoirDescription']) ?: null,
            'classe_id' => (int) $data['avoirClasse'],
            'versement_mensuel' => $mensuel,
        ];
        $endOfClasse = fn () => (Avoir::where('classe_id', $fields['classe_id'])->max('position') ?? -1) + 1;

        if ($this->avoirId) {
            $avoir = Avoir::findOrFail($this->avoirId);
            if ($avoir->classe_id !== $fields['classe_id']) {
                $fields['position'] = $endOfClasse();
            }
            $avoir->update($fields);
        } else {
            Avoir::create($fields + ['position' => $endOfClasse()]);
        }

        $this->cancel();
        $this->changed();
    }

    public function editAvoir(int $id): void
    {
        $avoir = Avoir::findOrFail($id);
        $this->cancel();
        $this->avoirId = $avoir->id;
        $this->avoirTitle = $avoir->title;
        $this->avoirDescription = (string) $avoir->description;
        $this->avoirClasse = (string) $avoir->classe_id;
        $this->avoirVersementMensuel = $avoir->versement_mensuel === null ? '' : (string) $avoir->versement_mensuel;
    }

    public function deleteAvoir(int $id): void
    {
        $avoir = Avoir::findOrFail($id);
        if ($avoir->lignes()->exists()) {
            $this->dispatch('notify', text: 'Cet avoir a des soldes enregistrés : archivez-le plutôt.');

            return;
        }
        $avoir->delete();
        $this->changed();
    }

    public function moveAvoir(int $id, int $dir): void
    {
        $avoir = Avoir::findOrFail($id);
        $this->move(Avoir::where('classe_id', $avoir->classe_id)->orderBy('position')->orderBy('id')->get(), $id, $dir);
    }

    public function archiveAvoir(int $id): void
    {
        $this->validate(["archiveOn.$id" => 'nullable|date_format:Y-m-d']);
        Avoir::findOrFail($id)->update(['archived_on' => $this->archiveOn[$id] ?? now()->toDateString()]);
        unset($this->archiveOn[$id]);
        $this->changed();
    }

    public function unarchiveAvoir(int $id): void
    {
        Avoir::findOrFail($id)->update(['archived_on' => null]);
        $this->changed();
    }

    public function cancel(): void
    {
        $this->resetErrorBag();
        $this->reset('classeId', 'classeTitle', 'classeDescription', 'avoirId', 'avoirTitle', 'avoirDescription', 'avoirClasse', 'avoirVersementMensuel');
    }

    /** Swap $id with its neighbour ($dir = -1 up, +1 down) and renumber the siblings 0..n. */
    protected function move(Collection $siblings, int $id, int $dir): void
    {
        $ids = $siblings->pluck('id')->all();
        $i = array_search($id, $ids, true);
        $j = $i === false ? -1 : $i + $dir;
        if ($j < 0 || $j >= count($ids)) {
            return;
        }
        [$ids[$i], $ids[$j]] = [$ids[$j], $ids[$i]];

        foreach ($ids as $position => $siblingId) {
            $siblings->firstWhere('id', $siblingId)->update(['position' => $position]);
        }
        $this->changed();
    }

    protected function changed(): void
    {
        $this->dispatch('avoirs-changed');
    }

    public function render()
    {
        return view('livewire.patrimoine-avoirs', [
            'classes' => Classe::with('avoirs')->orderBy('position')->orderBy('id')->get(),
        ]);
    }
}
```

- [ ] **Step 4: Write the view**

`resources/views/livewire/patrimoine-avoirs.blade.php`. It reuses the layout's classes (`table`, `btn`, `btn sm ghost`, `muted`, `quiet`, `error`, `chip neutral`, `filters`):

```blade
<div>
    {{-- Classe form --}}
    <form wire:submit="saveClasse" class="filters" style="margin-bottom:8px;">
        <input type="text" maxlength="60" placeholder="Nouvelle classe (ex. Liquidités)" aria-label="Titre de la classe" wire:model="classeTitle">
        <input type="text" maxlength="160" class="grow" placeholder="Note courte (facultatif)" aria-label="Description de la classe" wire:model="classeDescription">
        <button class="btn primary sm" type="submit">{{ $classeId ? 'Enregistrer la classe' : 'Ajouter la classe' }}</button>
        @if ($classeId) <button class="btn sm ghost" type="button" wire:click="cancel">Annuler</button> @endif
    </form>
    @error('classeTitle') <p class="error">{{ $message }}</p> @enderror
    @error('classeDescription') <p class="error">{{ $message }}</p> @enderror

    {{-- Avoir form --}}
    @if ($classes->isNotEmpty())
        <form wire:submit="saveAvoir" class="filters" style="margin:16px 0 8px;">
            <select wire:model="avoirClasse" aria-label="Classe">
                <option value="">Classe…</option>
                @foreach ($classes as $c) <option value="{{ $c->id }}">{{ $c->title }}</option> @endforeach
            </select>
            <input type="text" maxlength="60" placeholder="Nouvel avoir (ex. Compte courant)" aria-label="Titre de l'avoir" wire:model="avoirTitle">
            <input type="text" maxlength="160" class="grow" placeholder="Note courte (facultatif)" aria-label="Description de l'avoir" wire:model="avoirDescription">
            <input type="text" inputmode="decimal" style="width:170px" placeholder="Versement mensuel" aria-label="Versement mensuel (CHF, facultatif)"
                   title="Montant de l'ordre permanent. Le renseigner active le suivi des versements." wire:model="avoirVersementMensuel">
            <button class="btn primary sm" type="submit">{{ $avoirId ? "Enregistrer l'avoir" : "Ajouter l'avoir" }}</button>
            @if ($avoirId) <button class="btn sm ghost" type="button" wire:click="cancel">Annuler</button> @endif
        </form>
        @foreach (['avoirClasse', 'avoirTitle', 'avoirDescription', 'avoirVersementMensuel'] as $field)
            @error($field) <p class="error">{{ $message }}</p> @enderror
        @endforeach
    @endif

    {{-- Classes and their Avoirs --}}
    <table>
        <tbody>
        @foreach ($classes as $c)
            <tr wire:key="classe-{{ $c->id }}">
                <th colspan="3" style="text-align:left">{{ $c->title }} <span class="quiet">{{ $c->description }}</span></th>
                <th style="text-align:right; white-space:nowrap">
                    <button class="btn sm ghost" wire:click="moveClasse({{ $c->id }}, -1)" aria-label="Monter">↑</button>
                    <button class="btn sm ghost" wire:click="moveClasse({{ $c->id }}, 1)" aria-label="Descendre">↓</button>
                    <button class="btn sm ghost" wire:click="editClasse({{ $c->id }})">Modifier</button>
                    <button class="btn sm ghost" wire:click="deleteClasse({{ $c->id }})" wire:confirm="Supprimer la classe « {{ $c->title }} » ?">Supprimer</button>
                </th>
            </tr>
            @forelse ($c->avoirs as $a)
                <tr wire:key="avoir-{{ $a->id }}" @class(['quiet' => $a->archived_on])>
                    <td>{{ $a->title }} <span class="quiet">{{ $a->description }}</span></td>
                    <td class="muted">
                        @if ($a->suitLesVersements()) {{ number_format((float) $a->versement_mensuel, 0, ',', ' ') }} CHF / mois @endif
                    </td>
                    <td>
                        @if ($a->archived_on)
                            <span class="chip neutral">Archivé le {{ $a->archived_on->format('d.m.Y') }}</span>
                        @endif
                    </td>
                    <td style="text-align:right; white-space:nowrap">
                        <button class="btn sm ghost" wire:click="moveAvoir({{ $a->id }}, -1)" aria-label="Monter">↑</button>
                        <button class="btn sm ghost" wire:click="moveAvoir({{ $a->id }}, 1)" aria-label="Descendre">↓</button>
                        <button class="btn sm ghost" wire:click="editAvoir({{ $a->id }})">Modifier</button>
                        @if ($a->archived_on)
                            <button class="btn sm ghost" wire:click="unarchiveAvoir({{ $a->id }})">Désarchiver</button>
                        @else
                            <input type="date" style="width:150px" aria-label="Archiver à partir du" title="Vide = aujourd'hui" wire:model="archiveOn.{{ $a->id }}">
                            <button class="btn sm ghost" wire:click="archiveAvoir({{ $a->id }})">Archiver</button>
                        @endif
                        <button class="btn sm ghost" wire:click="deleteAvoir({{ $a->id }})" wire:confirm="Supprimer l'avoir « {{ $a->title }} » ?">Supprimer</button>
                    </td>
                </tr>
            @empty
                <tr><td colspan="4" class="quiet">Aucun avoir dans cette classe.</td></tr>
            @endforelse
        @endforeach
        </tbody>
    </table>
</div>
```

- [ ] **Step 5: Run the tests to check that they pass**

Run: `php artisan test --filter=AvoirsTest`
Expected: 6 passed.

- [ ] **Step 6: Commit**

```bash
git add app/Livewire/PatrimoineAvoirs.php resources/views/livewire/patrimoine-avoirs.blade.php tests/Feature/Patrimoine/AvoirsTest.php
git commit -m ":sparkles: Patrimoine: manage Classes and Avoirs (order, archive, guarded delete)"
```

---

### Task 5: The Patrimoine page (Relevé form, table, charts, history, route, nav)

**Files:**
- Create: `app/Livewire/Patrimoine.php`
- Create: `resources/views/livewire/patrimoine.blade.php`
- Modify: `routes/web.php` (add the route inside the `budget` group)
- Modify: `resources/views/budget/layout.blade.php:416-418` (nav link after "Importer")
- Modify: `tests/Feature/SecurityTest.php:13` (`BUDGET_URLS`)
- Modify: `README.md` (Usage section, one bullet)
- Test: `tests/Feature/Patrimoine/PageTest.php`

**Interfaces:**
- Consumes:
  - `PatrimoineService::{parseAmount, activeAvoirs, draft, save}` from Task 2
  - `PatrimoineReport::{releves, latest, totalOption, classesOption, versementsOption, hasVersements}` from Task 3
  - the `patrimoine-avoirs` component and its `avoirs-changed` event from Task 4
  - the layout's `echart(option, key)` helper and `charts-updated` window event
- Produces: the route `budget.patrimoine` at `/budget/patrimoine`.

- [ ] **Step 1: Write the failing test**

`tests/Feature/Patrimoine/PageTest.php`:

```php
<?php

namespace Tests\Feature\Patrimoine;

use App\Livewire\Patrimoine;
use App\Models\Releve;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\PatrimoineFixtures;
use Tests\TestCase;

class PageTest extends TestCase
{
    use PatrimoineFixtures, RefreshDatabase;

    public function test_empty_page_invites_to_create_avoirs(): void
    {
        Livewire::test(Patrimoine::class)
            ->assertOk()
            ->assertSee('Patrimoine')
            ->assertSee('Gérer les avoirs')
            ->assertSee('Aucun relevé');
    }

    public function test_new_releve_is_prefilled_and_saved(): void
    {
        ['courant' => $courant, 'titres' => $titres] = $this->makeHoldings();
        $this->makeReleve('2026-09-01', [$courant->id => ['1000', null], $titres->id => ['2000', '300']]);

        Livewire::test(Patrimoine::class)
            ->call('newReleve')
            ->assertSet('formOpen', true)
            ->set('date', '2026-11-20')
            ->assertSet("balances.{$courant->id}", '1000.00')
            ->assertSet("versements.{$titres->id}", '600.00')
            ->set("balances.{$courant->id}", "1'250")
            ->call('saveReleve')
            ->assertHasNoErrors()
            ->assertSet('formOpen', false)
            ->assertDispatched('charts-updated')
            ->assertSee('3 250'); // latest total: 1 250 + 2 000

        $this->assertSame('1250.00', (string) Releve::where('date', '2026-11-20')->first()->lignes->firstWhere('avoir_id', $courant->id)->balance);
    }

    public function test_invalid_or_missing_amounts_block_saving(): void
    {
        ['courant' => $courant, 'titres' => $titres] = $this->makeHoldings();

        Livewire::test(Patrimoine::class)
            ->call('newReleve')
            ->set('date', '2026-09-01')
            ->set("balances.{$courant->id}", '12,283')
            ->set("balances.{$titres->id}", '')
            ->call('saveReleve')
            ->assertHasErrors(["balances.{$courant->id}", "balances.{$titres->id}"]);

        $this->assertSame(0, Releve::count());
    }

    public function test_duplicate_date_is_a_validation_error(): void
    {
        ['courant' => $courant, 'titres' => $titres] = $this->makeHoldings();
        $this->makeReleve('2026-09-01', [$courant->id => ['1000', null], $titres->id => ['2000', '0']]);
        $other = $this->makeReleve('2026-10-01', [$courant->id => ['1100', null], $titres->id => ['2100', '0']]);

        Livewire::test(Patrimoine::class)
            ->call('newReleve')->set('date', '2026-09-01')->call('saveReleve')
            ->assertHasErrors(['date'])
            ->call('editReleve', $other->id)->set('date', '2026-09-01')->call('saveReleve')
            ->assertHasErrors(['date']);

        $this->assertSame(2, Releve::count());
    }

    public function test_edit_and_delete_a_past_releve(): void
    {
        ['courant' => $courant, 'titres' => $titres] = $this->makeHoldings();
        $releve = $this->makeReleve('2026-09-01', [$courant->id => ['1000', null], $titres->id => ['2000', '300']]);

        Livewire::test(Patrimoine::class)
            ->call('editReleve', $releve->id)
            ->assertSet('date', '2026-09-01')
            ->assertSet("versements.{$titres->id}", '300.00')
            ->set("balances.{$titres->id}", '2500')
            ->call('saveReleve')
            ->assertHasNoErrors();

        $this->assertSame('2500.00', (string) $releve->fresh()->lignes->firstWhere('avoir_id', $titres->id)->balance);

        Livewire::test(Patrimoine::class)->call('deleteReleve', $releve->id);
        $this->assertSame(0, Releve::count());
    }

    public function test_route_is_registered_in_the_budget_group(): void
    {
        $this->assertSame(url('/budget/patrimoine'), route('budget.patrimoine'));
    }
}
```

Also add the URL to the guarded list in `tests/Feature/SecurityTest.php`:

```php
    private const BUDGET_URLS = ['/budget', '/budget/transactions', '/budget/import', '/budget/patrimoine'];
```

- [ ] **Step 2: Run the tests to check that they fail**

Run: `php artisan test --filter='PageTest|SecurityTest'`
Expected: `PageTest` fails with `Class "App\Livewire\Patrimoine" not found`. The `SecurityTest` URL loops fail with a 404 on `/budget/patrimoine`.

- [ ] **Step 3: Write the component**

`app/Livewire/Patrimoine.php`:

```php
<?php

namespace App\Livewire;

use App\Models\Avoir;
use App\Models\Releve;
use App\Services\PatrimoineReport;
use App\Services\PatrimoineService;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;

#[Layout('budget.layout')]
class Patrimoine extends Component
{
    public bool $formOpen = false;

    public ?int $releveId = null;   // null = a new Relevé

    public string $date = '';       // Y-m-d

    /** Typed amounts, avoirId => string as typed ("12'283"). */
    public array $balances = [];

    public array $versements = [];

    public function newReleve(): void
    {
        $this->resetErrorBag();
        $this->releveId = null;
        $this->date = now()->toDateString();
        $this->prefill(keepTyped: false);
        $this->formOpen = true;
    }

    public function editReleve(int $id): void
    {
        $releve = Releve::findOrFail($id);
        $this->resetErrorBag();
        $this->releveId = $releve->id;
        $this->date = $releve->date->toDateString();
        $this->prefill(keepTyped: false);
        $this->formOpen = true;
    }

    /** A new date means a new previous Relevé and month count: propose fresh values. */
    public function updatedDate(): void
    {
        if ($this->validDate()) {
            $this->prefill(keepTyped: false);
        }
    }

    /** Avoirs were added, archived or reordered in the panel: keep what was typed, add the rest. */
    #[On('avoirs-changed')]
    public function avoirsChanged(): void
    {
        if ($this->formOpen && $this->validDate()) {
            $this->prefill(keepTyped: true);
        }
    }

    public function saveReleve(PatrimoineService $service): void
    {
        $this->resetErrorBag();
        $this->validate([
            'date' => ['required', 'date_format:Y-m-d', Rule::unique('patrimoine_releves', 'date')->ignore($this->releveId)],
        ], [
            'date.unique' => 'Il y a déjà un relevé à cette date.',
        ]);

        $avoirs = $service->activeAvoirs($this->date);
        if ($avoirs->isEmpty()) {
            $this->addError('date', 'Aucun avoir actif à cette date.');

            return;
        }

        $balances = [];
        $versements = [];
        foreach ($avoirs as $avoir) {
            $balances[$avoir->id] = PatrimoineService::parseAmount($this->balances[$avoir->id] ?? '');
            if ($balances[$avoir->id] === null) {
                $this->addError("balances.{$avoir->id}", 'Montant requis (ex. 12\'283 ou 0).');
            }
            if ($avoir->suitLesVersements()) {
                $versements[$avoir->id] = PatrimoineService::parseAmount($this->versements[$avoir->id] ?? '');
                if ($versements[$avoir->id] === null) {
                    $this->addError("versements.{$avoir->id}", 'Montant requis (0 si aucun).');
                }
            }
        }
        if ($this->getErrorBag()->isNotEmpty()) {
            return;
        }

        $service->save($this->date, $balances, $versements, $this->releveId ? Releve::findOrFail($this->releveId) : null);

        $this->reset('formOpen', 'releveId', 'date', 'balances', 'versements');
        $this->dispatch('notify', text: 'Relevé enregistré.');
    }

    public function deleteReleve(int $id): void
    {
        Releve::findOrFail($id)->delete();
        if ($this->releveId === $id) {
            $this->reset('formOpen', 'releveId', 'date', 'balances', 'versements');
        }
        $this->dispatch('notify', text: 'Relevé supprimé.');
    }

    public function cancel(): void
    {
        $this->resetErrorBag();
        $this->reset('formOpen', 'releveId', 'date', 'balances', 'versements');
    }

    protected function validDate(): bool
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->date) === 1;
    }

    protected function prefill(bool $keepTyped): void
    {
        $releve = $this->releveId ? Releve::find($this->releveId) : null;
        $balances = [];
        $versements = [];

        foreach (app(PatrimoineService::class)->draft($this->date, $releve) as $id => $line) {
            $balances[$id] = $keepTyped && array_key_exists($id, $this->balances) ? $this->balances[$id] : $line['balance'];
            if ($line['versement'] !== null) {
                $versements[$id] = $keepTyped && array_key_exists($id, $this->versements) ? $this->versements[$id] : $line['versement'];
            }
        }

        $this->balances = $balances;
        $this->versements = $versements;
    }

    public function render(PatrimoineService $service, PatrimoineReport $report)
    {
        $releves = $report->releves();
        $charts = [
            'total' => $report->totalOption($releves),
            'classes' => $report->classesOption($releves),
            'versements' => $report->versementsOption($releves),
        ];

        // Push fresh options to the (wire:ignore) ECharts instances.
        $this->dispatch('charts-updated', ...$charts);

        return view('livewire.patrimoine', [
            'latest' => $report->latest($releves),
            'charts' => $charts,
            'hasVersements' => $report->hasVersements($releves),
            'history' => $releves->reverse()->values(),
            'formAvoirs' => $this->formOpen && $this->validDate()
                ? $service->activeAvoirs($this->date)->groupBy('classe_id')
                : collect(),
            'needsSetup' => ! Avoir::exists(),
        ])->title('Patrimoine · Savings Budget');
    }
}
```

- [ ] **Step 4: Write the view**

`resources/views/livewire/patrimoine.blade.php`:

```blade
@php $chf = fn ($v, $d = 0) => number_format((float) $v, $d, ',', ' '); @endphp
<div>
    <div class="head">
        <div>
            <h1>Patrimoine</h1>
            <p class="sub">Vos avoirs, relevé après relevé.</p>
        </div>
        @unless ($needsSetup || $formOpen)
            <button class="btn primary" wire:click="newReleve">Nouveau relevé</button>
        @endunless
    </div>

    {{-- Relevé form --}}
    @if ($formOpen)
        <section class="panel" style="margin-bottom:16px;">
            <h2 class="caps">{{ $releveId ? 'Modifier le relevé' : 'Nouveau relevé' }}
                <span class="hint">pré-rempli depuis le relevé précédent — ne changez que ce qui a bougé</span></h2>
            <form wire:submit="saveReleve">
                <div class="filters">
                    <label>Date <input type="date" wire:model.live="date" aria-label="Date du relevé"></label>
                </div>
                @error('date') <p class="error">{{ $message }}</p> @enderror

                <table>
                    <thead><tr><th>Avoir</th><th style="text-align:right">Solde (CHF)</th><th style="text-align:right">Versement depuis le dernier relevé</th></tr></thead>
                    <tbody>
                    @foreach ($formAvoirs as $avoirs)
                        <tr><th colspan="3" style="text-align:left">{{ $avoirs->first()->classe->title }}</th></tr>
                        @foreach ($avoirs as $a)
                            <tr wire:key="form-{{ $a->id }}">
                                <td>{{ $a->title }} <span class="quiet">{{ $a->description }}</span></td>
                                <td style="text-align:right">
                                    <input type="text" inputmode="decimal" style="width:140px; text-align:right" aria-label="Solde {{ $a->title }}" wire:model="balances.{{ $a->id }}">
                                    @error("balances.{$a->id}") <p class="error">{{ $message }}</p> @enderror
                                </td>
                                <td style="text-align:right">
                                    @if ($a->suitLesVersements())
                                        <input type="text" inputmode="decimal" style="width:120px; text-align:right" aria-label="Versement {{ $a->title }}" wire:model="versements.{{ $a->id }}">
                                        @error("versements.{$a->id}") <p class="error">{{ $message }}</p> @enderror
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    @endforeach
                    </tbody>
                </table>

                <div class="filters" style="margin-top:12px;">
                    <button class="btn primary" type="submit">Enregistrer</button>
                    <button class="btn ghost" type="button" wire:click="cancel">Annuler</button>
                </div>
            </form>
        </section>
    @endif

    {{-- Latest Relevé --}}
    <section class="panel" style="margin-bottom:16px;">
        @if ($latest)
            <h2 class="caps">Relevé du {{ $latest['date']->format('d.m.Y') }}
                @if ($latest['previousDate'])
                    <span class="hint">{{ $latest['change'] >= 0 ? '+' : '−' }}{{ $chf(abs($latest['change'])) }} CHF depuis le {{ $latest['previousDate']->format('d.m.Y') }}</span>
                @endif
            </h2>
            <table>
                <tbody>
                @foreach ($latest['groups'] as $g)
                    <tr><th style="text-align:left">{{ $g['title'] }} <span class="quiet">{{ $g['description'] }}</span></th>
                        <th style="text-align:right" class="num">{{ $chf($g['subtotal']) }}</th><th></th></tr>
                    @foreach ($g['avoirs'] as $a)
                        <tr><td>{{ $a['title'] }} <span class="quiet">{{ $a['description'] }}</span></td>
                            <td style="text-align:right" class="num">{{ $chf($a['balance']) }}</td>
                            <td style="text-align:right" @class(['num', 'pos' => ($a['change'] ?? 0) > 0, 'neg' => ($a['change'] ?? 0) < 0])>
                                @if ($a['change']) {{ $a['change'] > 0 ? '+' : '−' }}{{ $chf(abs($a['change'])) }} @endif
                            </td></tr>
                    @endforeach
                @endforeach
                <tr><th style="text-align:left">Total</th><th style="text-align:right" class="num">{{ $chf($latest['total']) }} CHF</th><th></th></tr>
                </tbody>
            </table>
        @else
            <h2 class="caps">Aucun relevé</h2>
            <p class="muted">{{ $needsSetup ? 'Commencez par créer vos classes et vos avoirs ci-dessous.' : 'Saisissez votre premier relevé avec « Nouveau relevé ». Vous pouvez le dater dans le passé.' }}</p>
        @endif
    </section>

    {{-- Charts --}}
    @if ($latest)
        <div class="grid two-col" style="margin-bottom:16px;">
            <section class="panel">
                <h2 class="caps">Patrimoine total</h2>
                <div class="chart" style="height:300px" wire:ignore x-data="echart(@js($charts['total']), 'total')"></div>
            </section>
            <section class="panel">
                <h2 class="caps">Répartition par classe</h2>
                <div class="chart" style="height:300px" wire:ignore x-data="echart(@js($charts['classes']), 'classes')"></div>
            </section>
        </div>
        @if ($hasVersements)
            <section class="panel" style="margin-bottom:16px;">
                <h2 class="caps">Versements et rendement <span class="hint">avoirs avec versements, cumulés depuis le premier relevé</span></h2>
                <div class="chart" style="height:300px" wire:ignore x-data="echart(@js($charts['versements']), 'versements')"></div>
            </section>
        @endif
    @endif

    {{-- History --}}
    @if ($history->isNotEmpty())
        <section class="panel" style="margin-bottom:16px;">
            <h2 class="caps">Relevés <span class="hint">cliquez pour modifier</span></h2>
            <table>
                <tbody>
                @foreach ($history as $r)
                    <tr wire:key="releve-{{ $r->id }}">
                        <td><button class="btn sm ghost" wire:click="editReleve({{ $r->id }})">{{ $r->date->format('d.m.Y') }}</button></td>
                        <td style="text-align:right" class="num">{{ $chf($r->lignes->sum('balance')) }} CHF</td>
                        <td style="text-align:right">
                            <button class="btn sm ghost" wire:click="deleteReleve({{ $r->id }})" wire:confirm="Supprimer le relevé du {{ $r->date->format('d.m.Y') }} ?">Supprimer</button>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </section>
    @endif

    {{-- Manage Classes and Avoirs (collapsible; Alpine keeps the open state across re-renders) --}}
    <section class="panel" x-data="{ open: @js($needsSetup) }">
        <h2 class="caps">
            <button class="btn sm ghost" type="button" @click="open = !open" :aria-expanded="open">Gérer les avoirs</button>
        </h2>
        <div x-show="open" x-cloak>
            <livewire:patrimoine-avoirs />
        </div>
    </section>
</div>
```

If `num` is not an existing class in the layout, drop it. Right alignment comes from the inline style. Check with `grep -n "\.num" resources/views/budget/layout.blade.php`.

- [ ] **Step 5: Add the route and the nav link**

In `routes/web.php`, add `use App\Livewire\Patrimoine;` with the other imports, and add this line inside the group, after the `/import` route:

```php
    Route::get('/patrimoine', Patrimoine::class)->name('budget.patrimoine');
```

In `resources/views/budget/layout.blade.php`, add this after the "Importer" nav link:

```blade
        <a class="link {{ str_starts_with($r, 'budget/patrimoine') ? 'active' : '' }}" href="/budget/patrimoine" wire:navigate>Patrimoine</a>
```

- [ ] **Step 6: Add the README usage bullet**

In `README.md` under `## Usage`, after the "Browse & classify" bullet:

```md
- **Patrimoine** — on `/budget/patrimoine`, define your Classes and Avoirs, then enter a dated Relevé of their balances whenever you like (pre-filled from the previous one); the page shows the latest totals and their evolution. See `CONTEXT.md` for the vocabulary.
```

- [ ] **Step 7: Run the tests to check that they pass**

Run: `php artisan test --filter='PageTest|SecurityTest'`
Expected: all pass (6 in `PageTest`, 7 in `SecurityTest`).

- [ ] **Step 8: Run the whole suite**

Run: `php artisan test`
Expected: all tests pass (28 existing + 29 new).

- [ ] **Step 9: Check it by hand in the browser**

Run `php artisan migrate`. Start the app the usual way (see README "Setup"), log in and open `/budget/patrimoine`. Use only synthetic data, and run `scripts/pull-production.sh` afterwards if the local DB should mirror prod again. Check the following:
- On an empty page, the "Gérer les avoirs" panel is open. Create 2 Classes and 3 Avoirs, one of them with a Versement mensuel.
- "Nouveau relevé": pick a past date, type `12'000`, save. Then make a second Relevé two months later and check that the Versement is pre-filled ×2.
- The charts render on a time axis. Tooltips show `… CHF`. If they show `NaN CHF` (the tooltip `valueFormatter` receiving the `[date, value]` pair), change `dress()`'s `money` in the layout to `(v) => chf.format(Array.isArray(v) ? v[1] : v) + ' CHF'`.
- The Classe areas are blue shades, and return is green.
- Archive an Avoir: it disappears from a new Relevé, but still shows when editing an older one.
- At 375px width, nothing scrolls horizontally except the tables.

- [ ] **Step 10: Commit**

```bash
git add app/Livewire/Patrimoine.php resources/views/livewire/patrimoine.blade.php routes/web.php resources/views/budget/layout.blade.php tests/Feature/SecurityTest.php tests/Feature/Patrimoine/PageTest.php README.md
git commit -m ":sparkles: Patrimoine page: Relevé form, latest table, charts and history"
```

- [ ] **Step 11: Commit the design docs** (if they were not committed earlier)

```bash
git add CONTEXT.md docs/adr/0002-patrimoine-outside-statamic.md docs/superpowers/specs/2026-10-04-patrimoine-design.md docs/superpowers/plans/2026-10-04-patrimoine.md
git commit -m ":memo: Patrimoine glossary, ADR, spec and plan"
```
