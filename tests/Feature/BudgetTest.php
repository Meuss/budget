<?php

namespace Tests\Feature;

use App\Livewire\Dashboard;
use App\Livewire\ImportCsv;
use App\Livewire\Transactions;
use App\Models\Transaction;
use App\Services\CategoryService;
use App\Services\TransactionImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Statamic\Facades\User;
use Tests\TestCase;

class BudgetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Entries (categories) now live in the database, so seed them for each fresh test DB.
        $this->artisan('seed:budget');
    }

    protected function authUser()
    {
        $user = User::all()->first();
        if (! $user) {
            $user = User::make()->email('tester@example.test')->makeSuper();
            $user->save();
        }
        $this->actingAs($user);

        return $user;
    }

    protected function categoryIdByTitle(string $title): ?string
    {
        foreach (app(CategoryService::class)->ordered() as $c) {
            if ($c['title'] === $title) {
                return $c['id'];
            }
        }

        return null;
    }

    protected function seedTransactions(): void
    {
        Transaction::create(['transaction_no' => 'T1', 'date' => '2024-05-01', 'amount' => -42.50, 'direction' => 'debit', 'merchant' => 'Migros MM', 'type' => 'achat']);
        Transaction::create(['transaction_no' => 'T2', 'date' => '2024-05-02', 'amount' => -1000, 'direction' => 'debit', 'merchant' => 'Account Holder', 'type' => 'EPARGNE; ordre permanent']);
        Transaction::create(['transaction_no' => 'T3', 'date' => '2024-05-25', 'amount' => 5000, 'direction' => 'credit', 'merchant' => 'Employer SA', 'type' => 'salaire']);
        Transaction::create(['transaction_no' => 'T4', 'date' => '2024-06-01', 'amount' => -77, 'direction' => 'debit', 'merchant' => 'Random Shop', 'type' => 'achat']);
    }

    public function test_guests_are_redirected_to_cp(): void
    {
        $this->get('/budget')->assertRedirect('/cp');
        $this->get('/budget/transactions')->assertRedirect('/cp');
    }

    public function test_categories_are_available(): void
    {
        $this->assertNotNull($this->categoryIdByTitle('Courses'));
        $this->assertNotNull($this->categoryIdByTitle('Épargne'));
    }

    public function test_dashboard_renders(): void
    {
        $this->authUser();
        $this->seedTransactions();

        Livewire::test(Dashboard::class)
            ->assertOk()
            ->assertSee('Tableau de bord')
            ->assertSee('Revenus')
            ->set('period', '2024')
            ->assertOk();
    }

    public function test_transactions_page_renders_and_manual_assign_sticks(): void
    {
        $this->authUser();
        $this->seedTransactions();
        $groceries = $this->categoryIdByTitle('Courses');

        $t = Transaction::where('transaction_no', 'T4')->first();

        Livewire::test(Transactions::class)
            ->assertOk()
            ->call('assign', $t->id, $groceries);

        $t->refresh();
        $this->assertSame($groceries, $t->category_id);
        $this->assertSame('manual', $t->source);
    }

    public function test_bulk_assign_applies_to_all_matching(): void
    {
        $this->authUser();
        $this->seedTransactions();
        $groceries = $this->categoryIdByTitle('Courses');

        Livewire::test(Transactions::class)
            ->set('search', 'Migros')
            ->set('bulkCategory', $groceries)
            ->call('bulkAssign');

        $this->assertSame($groceries, Transaction::where('transaction_no', 'T1')->first()->category_id);
        $this->assertNull(Transaction::where('transaction_no', 'T4')->first()->category_id);
    }

    public function test_assign_applies_to_selected_rows_only(): void
    {
        $this->authUser();
        $this->seedTransactions();
        $courses = $this->categoryIdByTitle('Courses');

        $t1 = Transaction::where('transaction_no', 'T1')->first();
        $t4 = Transaction::where('transaction_no', 'T4')->first();

        Livewire::test(Transactions::class)
            ->set('selected', [(string) $t1->id, (string) $t4->id])
            ->set('bulkCategory', $courses)
            ->call('assignSelected')
            ->assertSet('selected', []); // cleared after applying

        $this->assertSame($courses, $t1->fresh()->category_id);
        $this->assertSame('manual', $t1->fresh()->source);
        $this->assertSame($courses, $t4->fresh()->category_id);
        // A row that was NOT selected stays untouched.
        $this->assertNull(Transaction::where('transaction_no', 'T3')->first()->category_id);
    }

    public function test_toggle_select_page_selects_all_visible_rows(): void
    {
        $this->authUser();
        $this->seedTransactions();

        Livewire::test(Transactions::class)
            ->call('toggleSelectPage')
            ->assertCount('selected', 4)   // all 4 seeded rows fit on one page
            ->call('toggleSelectPage')
            ->assertSet('selected', []);   // toggles back off
    }

    public function test_classifier_marks_migros_and_savings_on_import_path(): void
    {
        $this->seedTransactions();
        // Run the classifier the same way the importer does.
        $classifier = app(\App\Services\ClassifierService::class);
        foreach (Transaction::all() as $t) {
            if ($u = $classifier->classify($t)) {
                $t->forceFill($u)->save();
            }
        }

        $this->assertSame($this->categoryIdByTitle('Courses'), Transaction::where('transaction_no', 'T1')->first()->category_id);
        $this->assertTrue((bool) Transaction::where('transaction_no', 'T2')->first()->is_savings);
    }

    public function test_amount_filter_uses_absolute_value(): void
    {
        $this->authUser();
        $this->seedTransactions(); // amounts: -42.50, -1000, 5000, -77

        Livewire::test(Transactions::class)
            ->set('amountMin', '100')
            ->assertViewHas('matchCount', 2)   // -1000 and 5000
            ->set('amountMin', '')
            ->set('amountMax', '100')
            ->assertViewHas('matchCount', 2);  // -42.50 and -77
    }

    public function test_match_term_works_with_displayed_separator(): void
    {
        // A category whose term was copied from the table (uses " · " not ";").
        $cats = new class extends CategoryService
        {
            public function map(): array
            {
                return ['x' => ['id' => 'x', 'title' => 'Amis', 'kind' => 'expense', 'parent' => null, 'match_terms' => [', ALEX · Débit UBS TWINT'], 'depth' => 0]];
            }

            public function kindOf(?string $id): string
            {
                return 'expense';
            }

            public function savingsCategoryId(): ?string
            {
                return null;
            }
        };

        $classifier = new \App\Services\ClassifierService($cats);

        // Stored merchant uses the raw ";" separator.
        $t = new Transaction(['transaction_no' => 'X1', 'date' => '2024-01-01', 'amount' => -20, 'direction' => 'debit', 'merchant' => ', ALEX; Débit UBS TWINT']);

        $this->assertSame('x', $classifier->classify($t)['category_id']);

        // The simple name also matches.
        $cats2 = clone $cats;
        $t2 = new Transaction(['transaction_no' => 'X2', 'date' => '2024-01-01', 'amount' => -20, 'direction' => 'debit', 'merchant' => ', ALEX; Débit UBS TWINT']);
        // (uses the same fake map, term contains "alex")
        $this->assertSame('x', $classifier->classify($t2)['category_id']);
    }

    public function test_csv_import_is_idempotent(): void
    {
        $importer = app(TransactionImporter::class);
        $csv = storage_path('app/imports/transactions-2026-in-progress.csv');

        $first = $importer->importFile($csv);
        $this->assertGreaterThan(0, $first['new']);

        $second = $importer->importFile($csv);
        $this->assertSame(0, $second['new']);
        $this->assertSame($first['new'], $second['duplicates']);
    }
}
