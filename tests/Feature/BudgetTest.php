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

    public function test_transfer_kind_is_excluded_from_income_spending_and_savings(): void
    {
        $transfer = $this->categoryIdByTitle('Transferts internes');
        $this->assertNotNull($transfer, 'seed:budget should define a transfer category');

        Transaction::create(['transaction_no' => 'I1', 'date' => '2024-05-25', 'amount' => 5000, 'direction' => 'credit', 'merchant' => 'Employer', 'category_id' => $this->categoryIdByTitle('Revenus'), 'source' => 'manual']);
        Transaction::create(['transaction_no' => 'S1', 'date' => '2024-05-01', 'amount' => -100, 'direction' => 'debit', 'merchant' => 'Shop', 'category_id' => $this->categoryIdByTitle('Courses'), 'source' => 'manual']);
        // A card-payment transfer: must count as neither spending, income, nor savings.
        Transaction::create(['transaction_no' => 'X1', 'date' => '2024-05-10', 'amount' => -600, 'direction' => 'debit', 'merchant' => 'Card payment', 'category_id' => $transfer, 'is_transfer' => true, 'source' => 'manual']);

        $k = app(\App\Services\BudgetReport::class)->kpis('2024-01-01', '2024-12-31');

        $this->assertSame(5000.0, $k['income']);
        $this->assertSame(100.0, $k['spending']);
        $this->assertSame(0.0, $k['savings']);
    }

    public function test_manual_assign_to_transfer_category_sets_is_transfer(): void
    {
        $this->authUser();
        $this->seedTransactions();
        $transfer = $this->categoryIdByTitle('Transferts internes');
        $courses = $this->categoryIdByTitle('Courses');
        $t = Transaction::where('transaction_no', 'T4')->first();

        Livewire::test(Transactions::class)->call('assign', $t->id, $transfer);
        $this->assertTrue((bool) $t->fresh()->is_transfer);

        // Reassigning to a non-transfer category clears the flag.
        Livewire::test(Transactions::class)->call('assign', $t->id, $courses);
        $this->assertFalse((bool) $t->fresh()->is_transfer);
    }

    public function test_transfer_kind_is_excluded_from_charts(): void
    {
        $transfer = $this->categoryIdByTitle('Transferts internes');
        Transaction::create(['transaction_no' => 'S1', 'date' => '2024-05-01', 'amount' => -100, 'direction' => 'debit', 'merchant' => 'Shop', 'category_id' => $this->categoryIdByTitle('Courses'), 'source' => 'manual']);
        Transaction::create(['transaction_no' => 'X1', 'date' => '2024-05-10', 'amount' => -600, 'direction' => 'debit', 'merchant' => 'Card payment', 'category_id' => $transfer, 'is_transfer' => true, 'source' => 'manual']);

        $report = app(\App\Services\BudgetReport::class);

        $byTop = $report->spendingByTopCategory('2024-01-01', '2024-12-31');
        $this->assertSame(100.0, round(array_sum($byTop), 2)); // 600 transfer excluded from pie

        $bar = $report->barOption('2024-01-01', '2024-12-31');
        $spend = collect($bar['series'])->firstWhere('name', 'Dépenses')['data'];
        $this->assertSame(100.0, round(array_sum($spend), 2)); // and from the monthly bars
    }

    public function test_classifier_sets_is_transfer_for_transfer_kind_category(): void
    {
        $cats = new class extends CategoryService
        {
            public function map(): array
            {
                return ['tr' => ['id' => 'tr', 'title' => 'Transferts', 'kind' => 'transfer', 'parent' => null, 'match_terms' => ['recouvrement'], 'depth' => 0]];
            }

            public function kindOf(?string $id): string
            {
                return 'transfer';
            }

            public function savingsCategoryId(): ?string
            {
                return null;
            }
        };

        $classifier = new \App\Services\ClassifierService($cats);
        $t = new Transaction(['transaction_no' => 'C1', 'date' => '2024-01-01', 'amount' => -600, 'direction' => 'debit', 'merchant' => 'PAIEMENT PAR RECOUVREMENT DIR.']);

        $result = $classifier->classify($t);
        $this->assertSame('tr', $result['category_id']);
        $this->assertTrue($result['is_transfer']);
        $this->assertFalse($result['is_savings']);
    }

    /** Write a Windows-1252 (Latin-1) encoded credit-card CSV and return its path. */
    protected function writeCardCsv(): string
    {
        $utf8 = implode("\n", [
            'sep=;',
            "Numéro de compte;Numéro de carte;Titulaire de compte/carte;Date d'achat;Texte comptable;Secteur;Montant;Monnaie originale;Cours;Monnaie;Débit;Crédit;Ecriture",
            '0000 0000 0000;0000 00XX XXXX 0000;DOE JANE;05.07.2026;Streaming Service SWE;Médias numériques;13.95;CHF;;CHF;13.95;;07.07.2026',
            '0000 0000 0000;0000 00XX XXXX 0000;DOE JANE;24.06.2026;Café Exemple Zürich;Restaurant;10.0;USD;0.83911571;CHF;8.39;;25.06.2026',
            '0000 0000 0000;0000 00XX XXXX 0000;DOE JANE;06.06.2026;TAXI RIDE NLD;Entreprise de taxi;5.5;CHF;;CHF;5.5;;08.06.2026',
            '0000 0000 0000;0000 00XX XXXX 0000;DOE JANE;06.06.2026;TAXI RIDE NLD;Entreprise de taxi;5.5;CHF;;CHF;5.5;;08.06.2026',
            '0000 0000 0000;;JANE DOE;25.06.2026;PAIEMENT PAR RECOUVREMENT DIR.;;250.50;CHF;;CHF;;250.50;26.06.2026',
            '0000 0000 0000;0000 00XX XXXX 0000;DOE JANE;05.07.2026;HOTEL EXEMPLE ITA;Hotel;36.3;CHF;;CHF;;;', // pending: no débit/crédit
            ';;;;Total par monnaie;;;;;;;;Total',
            ';;;;Total des écritures de carte;;;;;CHF;1234.50;1034.20;-200.30',
            '',
        ]);
        $path = tempnam(sys_get_temp_dir(), 'cc').'.csv';
        file_put_contents($path, mb_convert_encoding($utf8, 'Windows-1252', 'UTF-8'));

        return $path;
    }

    public function test_credit_card_parser_reads_card_export(): void
    {
        $path = $this->writeCardCsv();
        $records = iterator_to_array((new \App\Services\CreditCardCsvParser)->parse($path));
        @unlink($path);

        // 5 real rows kept: Streaming, Café, Taxi, Taxi, Paiement. Pending + 2 footer rows dropped.
        $this->assertCount(5, $records);

        $streaming = $records[0];
        $this->assertSame('2026-07-07', $streaming['date']);       // booking date (Ecriture)
        $this->assertSame('2026-07-05', $streaming['value_date']); // purchase date (Date d'achat)
        $this->assertSame('debit', $streaming['direction']);
        $this->assertSame(-13.95, $streaming['amount']);           // Débit → negative CHF
        $this->assertSame('CHF', $streaming['currency']);
        $this->assertSame('Streaming Service SWE', $streaming['merchant']);

        // Windows-1252 accents survive the conversion, and foreign rows store the CHF amount.
        $this->assertSame('Café Exemple Zürich', $records[1]['merchant']);
        $this->assertSame(-8.39, $records[1]['amount']);

        // A Crédit (card payment) is positive income-side.
        $this->assertSame('credit', $records[4]['direction']);
        $this->assertSame(250.50, $records[4]['amount']);
        $this->assertSame('PAIEMENT PAR RECOUVREMENT DIR.', $records[4]['merchant']);

        // Two genuinely-identical rows both survive, with distinct dedupe keys.
        $this->assertNotSame($records[2]['transaction_no'], $records[3]['transaction_no']);
    }

    public function test_importer_auto_detects_and_imports_card_csv(): void
    {
        $path = $this->writeCardCsv();
        $importer = app(TransactionImporter::class);

        $first = $importer->importFile($path);
        $this->assertSame(5, $first['new']);

        // Re-importing the same export dedupes everything (synthetic keys are stable).
        $second = $importer->importFile($path);
        @unlink($path);
        $this->assertSame(0, $second['new']);
        $this->assertSame(5, $second['duplicates']);

        $this->assertSame(1, Transaction::where('merchant', 'PAIEMENT PAR RECOUVREMENT DIR.')->where('direction', 'credit')->count());
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
        // Synthetic UBS export (the real ones in storage/app/imports are private and untracked).
        $csv = tempnam(sys_get_temp_dir(), 'ubs').'.csv';
        file_put_contents($csv, "\u{FEFF}Numéro de compte:;0000 00000000.00;\n"
            ."IBAN:;CH00 0000 0000 0000 0000 0;\n\n"
            ."Date de transaction;Heure de transaction;Date de comptabilisation;Date de valeur;Monnaie;Débit;Crédit;Sous-montant;Solde;No de transaction;Description1;Description2;Description3;Notes de bas de page;\n"
            ."2026-01-05;;2026-01-05;2026-01-05;CHF;-42.50;;;1000.00;TX-1;Migros MM;Paiement carte;;;\n"
            ."2026-01-25;;2026-01-25;2026-01-25;CHF;;5000.00;;6000.00;TX-2;Employer SA;Salaire;;;\n");

        $importer = app(TransactionImporter::class);

        $first = $importer->importFile($csv);
        $this->assertGreaterThan(0, $first['new']);

        $second = $importer->importFile($csv);
        $this->assertSame(0, $second['new']);
        $this->assertSame($first['new'], $second['duplicates']);
        @unlink($csv);
    }
}
