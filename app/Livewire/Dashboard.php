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

        // Push fresh chart options to the (wire:ignore) ECharts instances.
        $this->dispatch('charts-updated',
            pie: $data['pie'],
            bar: $data['bar'],
            sankey: $data['sankey'],
        );

        return view('livewire.dashboard', [
            'kpis' => $data['kpis'],
            'pie' => $data['pie'],
            'bar' => $data['bar'],
            'sankey' => $data['sankey'],
            'years' => $report->availableYears(),
        ])->title('Dashboard · Savings Budget');
    }
}
