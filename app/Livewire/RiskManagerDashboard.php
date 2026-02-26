<?php

namespace App\Livewire;

use App\Models\Trade;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
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

        [$rrRisk, $rrReward] = $this->parseRiskReward();

        $this->riskAmount = round($this->capital * ($this->riskPercent / 100), 2);
        $this->positionSize = round($this->riskAmount / ($this->stopLossPercent / 100), 8);

        $targetPercent = ($this->stopLossPercent * $rrReward) / $rrRisk;

        $this->takeProfitPrice = round($this->entryPrice * (1 + ($targetPercent / 100)), 8);
        $this->expectedProfit = round($this->riskAmount * ($rrReward / $rrRisk), 2);
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

        // Critical fix: calculate P/L from immutable trade data, never from mutable form state.
        $trade->result = $result;
        $trade->profit_loss = match ($result) {
            'win' => $this->calculateTradeExpectedProfit($trade),
            'loss' => round(-abs((float) $trade->risk_amount), 2),
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
        $stats = $this->tradesBaseQuery()
            ->selectRaw('COUNT(*) as total_trades')
            ->selectRaw("SUM(CASE WHEN result = 'win' THEN 1 ELSE 0 END) as wins")
            ->selectRaw("SUM(CASE WHEN result = 'loss' THEN 1 ELSE 0 END) as losses")
            ->selectRaw('COALESCE(SUM(profit_loss), 0) as total_profit_loss')
            ->selectRaw("COALESCE(AVG(CASE WHEN result = 'win' AND risk_amount > 0 THEN profit_loss / risk_amount END), 0) as average_rr")
            ->first();

        $totalTrades = (int) ($stats->total_trades ?? 0);
        $wins = (int) ($stats->wins ?? 0);
        $losses = (int) ($stats->losses ?? 0);
        $totalProfitLoss = round((float) ($stats->total_profit_loss ?? 0), 2);

        // Critical fix: use oldest trade for baseline capital, fallback to current capital when user has no trades.
        $startingCapital = round((float) ($this->tradesBaseQuery()
            ->orderBy('created_at')
            ->orderBy('id')
            ->value('capital') ?? $this->capital), 2);
        $startingCapital = max($startingCapital, 1);

        $winRate = $totalTrades > 0 ? round(($wins / $totalTrades) * 100, 2) : 0;
        $accountGrowth = round(($totalProfitLoss / $startingCapital) * 100, 2);
        $averageRr = round((float) ($stats->average_rr ?? 0), 2);

        // Critical fix: monthly performance is now aggregated in SQL instead of loading all rows.
        $monthlyPerformance = $this->tradesBaseQuery()
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as month_key")
            ->selectRaw('ROUND(COALESCE(SUM(profit_loss), 0), 2) as monthly_profit_loss')
            ->groupBy('month_key')
            ->orderBy('month_key')
            ->pluck('monthly_profit_loss', 'month_key')
            ->map(fn ($value) => (float) $value)
            ->toArray();

        $lastThreeResults = $this->tradesBaseQuery()
            ->latest('created_at')
            ->latest('id')
            ->limit(3)
            ->pluck('result');
        $threeLossesInRow = $lastThreeResults->count() === 3
            && $lastThreeResults->every(fn (string $res) => $res === 'loss');

        // Critical fix: weekly drawdown computed directly in the database for scalability.
        $weeklyDrawdown = abs((float) $this->tradesBaseQuery()
            ->where('created_at', '>=', now()->startOfWeek())
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
        return $this->tradesBaseQuery()
            ->when($this->search, fn (Builder $query) => $query->where('coin', 'like', "%{$this->search}%"))
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

    private function tradesBaseQuery(): Builder
    {
        return Trade::query()->ownedBy(Auth::id());
    }

    private function parseRiskReward(): array
    {
        [$rrRisk, $rrReward] = array_map('floatval', explode(':', $this->takeProfitRr));

        return [max($rrRisk, 0.0001), max($rrReward, 0.0001)];
    }

    private function calculateTradeExpectedProfit(Trade $trade): float
    {
        $entryPrice = max((float) $trade->entry_price, 0.00000001);
        $stopLossPercent = max((float) $trade->stop_loss_percent, 0.0001);
        $riskAmount = max((float) $trade->risk_amount, 0);

        $targetPercent = ((float) $trade->take_profit_price - $entryPrice) / $entryPrice * 100;
        $rewardToRiskRatio = $targetPercent / $stopLossPercent;

        return round($riskAmount * $rewardToRiskRatio, 2);
    }
}
