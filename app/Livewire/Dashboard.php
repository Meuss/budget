<?php

namespace App\Livewire;

use App\Services\BudgetReport;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('budget.layout')]
class Dashboard extends Component
{
    #[Url]
    public string $period = 'all';

    public function render(BudgetReport $report)
    {
        $data = $report->forPeriod($this->period);
        [$from] = $report->bounds($this->period);

        // Push fresh chart options to the (wire:ignore) ECharts instances.
        $this->dispatch('charts-updated',
            spending: $data['spending'],
            bar: $data['bar'],
            sankey: $data['sankey'],
        );

        return view('livewire.dashboard', [
            'kpis' => $data['kpis'],
            'spending' => $data['spending'],
            'bar' => $data['bar'],
            'sankey' => $data['sankey'],
            'years' => $report->availableYears(),
            // Each headline figure opens the transactions it is made of.
            'links' => [
                'income' => $report->transactionsUrl($from, ['direction' => 'credit']),
                'spending' => $report->transactionsUrl($from, ['direction' => 'debit']),
                'savings' => $report->transactionsUrl($from, ['category' => 'savings']),
                'rate' => $report->transactionsUrl($from, ['category' => 'savings']),
                'net' => $report->transactionsUrl($from, []),
            ],
        ])->title('Dashboard · Savings Budget');
    }
}
