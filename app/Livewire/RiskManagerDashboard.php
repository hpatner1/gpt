<?php

namespace App\Livewire;

use App\Models\Trade;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class RiskManagerDashboard extends Component
{
    use WithPagination;

    public string $coin = 'BTCUSDT';
    public float $capital = 1000;
    public float $riskPercent = 1;
    public float $stopLossPercent = 2;
    public float $entryPrice = 100;
    public string $takeProfitRr = '1:2';

    public float $riskAmount = 0;
    public float $positionSize = 0;
    public float $takeProfitPrice = 0;
    public float $expectedProfit = 0;

    public string $search = '';

    public bool $showSaveSuccess = false;

    protected $queryString = ['search'];

    protected array $rules = [
        'coin' => 'required|string|max:20',
        'capital' => 'required|numeric|min:1',
        'riskPercent' => 'required|numeric|min:0.1|max:10',
        'stopLossPercent' => 'required|numeric|min:0.01|max:100',
        'entryPrice' => 'required|numeric|min:0.00000001',
        'takeProfitRr' => 'required|regex:/^\d+(\.\d+)?:\d+(\.\d+)?$/',
    ];

    public function mount(): void
    {
        $this->calculate();
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function calculate(): void
    {
        $this->validateOnly('capital');
        $this->validateOnly('riskPercent');
        $this->validateOnly('stopLossPercent');
        $this->validateOnly('entryPrice');
        $this->validateOnly('takeProfitRr');

        $this->riskAmount = round($this->capital * ($this->riskPercent / 100), 2);
        $this->positionSize = round($this->riskAmount / ($this->stopLossPercent / 100), 8);

        [$rrRisk, $rrReward] = array_map('floatval', explode(':', $this->takeProfitRr));
        $targetPercent = ($this->stopLossPercent * $rrReward) / max($rrRisk, 0.0001);

        $this->takeProfitPrice = round($this->entryPrice * (1 + ($targetPercent / 100)), 8);
        $this->expectedProfit = round($this->riskAmount * ($rrReward / max($rrRisk, 0.0001)), 2);
    }

    public function saveTrade(): void
    {
        $this->validate();
        $this->calculate();

        Trade::query()->create([
            'user_id' => Auth::id(),
            'coin' => strtoupper(trim($this->coin)),
            'capital' => $this->capital,
            'risk_percent' => $this->riskPercent,
            'risk_amount' => $this->riskAmount,
            'stop_loss_percent' => $this->stopLossPercent,
            'position_size' => $this->positionSize,
            'entry_price' => $this->entryPrice,
            'take_profit_price' => $this->takeProfitPrice,
            'result' => 'pending',
            'profit_loss' => 0,
        ]);

        $this->showSaveSuccess = true;
    }

    public function updateResult(int $tradeId, string $result): void
    {
        abort_unless(in_array($result, ['win', 'loss', 'pending'], true), 422);

        $trade = Trade::query()->ownedBy(Auth::id())->findOrFail($tradeId);
        $trade->result = $result;
        $trade->profit_loss = match ($result) {
            'win' => $this->expectedProfit,
            'loss' => -$this->riskAmount,
            default => 0,
        };
        $trade->save();
    }

    public function deleteTrade(int $tradeId): void
    {
        Trade::query()->ownedBy(Auth::id())->findOrFail($tradeId)->delete();
    }

    public function getStatsProperty(): array
    {
        $trades = Trade::query()->ownedBy(Auth::id())->get();

        $totalTrades = $trades->count();
        $wins = $trades->where('result', 'win')->count();
        $losses = $trades->where('result', 'loss')->count();
        $startingCapital = max((float) ($trades->first()?->capital ?? $this->capital), 1);
        $totalProfitLoss = (float) $trades->sum('profit_loss');
        $winRate = $totalTrades > 0 ? round(($wins / $totalTrades) * 100, 2) : 0;
        $accountGrowth = round(($totalProfitLoss / $startingCapital) * 100, 2);

        $averageRr = round($trades
            ->filter(fn (Trade $t) => $t->risk_amount > 0 && $t->profit_loss > 0)
            ->avg(fn (Trade $t) => $t->profit_loss / $t->risk_amount) ?? 0, 2);

        $monthlyPerformance = $trades
            ->groupBy(fn (Trade $trade) => $trade->created_at->format('Y-m'))
            ->map(fn ($group) => round($group->sum('profit_loss'), 2))
            ->toArray();

        $lastThreeResults = $trades->sortByDesc('created_at')->take(3)->pluck('result')->values();
        $threeLossesInRow = $lastThreeResults->count() === 3 && $lastThreeResults->every(fn (string $res) => $res === 'loss');

        $startOfWeek = now()->startOfWeek();
        $weeklyDrawdown = abs((float) $trades
            ->where('created_at', '>=', $startOfWeek)
            ->where('profit_loss', '<', 0)
            ->sum('profit_loss'));

        $weeklyDrawdownPercent = round(($weeklyDrawdown / $startingCapital) * 100, 2);

        return compact(
            'totalTrades',
            'wins',
            'losses',
            'totalProfitLoss',
            'winRate',
            'accountGrowth',
            'averageRr',
            'monthlyPerformance',
            'threeLossesInRow',
            'weeklyDrawdownPercent'
        );
    }

    public function getTradesProperty()
    {
        return Trade::query()
            ->ownedBy(Auth::id())
            ->when($this->search, fn ($query) => $query->where('coin', 'like', "%{$this->search}%"))
            ->latest()
            ->paginate(10);
    }

    public function render(): View
    {
        return view('livewire.risk-manager-dashboard', [
            'trades' => $this->trades,
            'stats' => $this->stats,
        ])->layout('components.layouts.app');
    }
}
