<div class="space-y-6">
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-6 gap-4">
        <div class="bg-slate-900 border border-slate-800 rounded-xl p-4"><p class="text-xs text-slate-400">Total Capital</p><p class="text-2xl font-semibold">${{ number_format($capital, 2) }}</p></div>
        <div class="bg-slate-900 border border-slate-800 rounded-xl p-4"><p class="text-xs text-slate-400">Current Risk %</p><p class="text-2xl font-semibold">{{ $riskPercent }}%</p></div>
        <div class="bg-slate-900 border border-slate-800 rounded-xl p-4"><p class="text-xs text-slate-400">Total Trades</p><p class="text-2xl font-semibold">{{ $stats['totalTrades'] }}</p></div>
        <div class="bg-slate-900 border border-slate-800 rounded-xl p-4"><p class="text-xs text-slate-400">Total Profit/Loss</p><p class="text-2xl font-semibold {{ $stats['totalProfitLoss'] >= 0 ? 'text-emerald-400' : 'text-red-400' }}">${{ number_format($stats['totalProfitLoss'], 2) }}</p></div>
        <div class="bg-slate-900 border border-slate-800 rounded-xl p-4"><p class="text-xs text-slate-400">Win Rate %</p><p class="text-2xl font-semibold">{{ $stats['winRate'] }}%</p></div>
        <div class="bg-slate-900 border border-slate-800 rounded-xl p-4"><p class="text-xs text-slate-400">Account Growth %</p><p class="text-2xl font-semibold {{ $stats['accountGrowth'] >= 0 ? 'text-emerald-400' : 'text-red-400' }}">{{ $stats['accountGrowth'] }}%</p></div>
    </div>

    @if($stats['threeLossesInRow'])
        <div class="bg-amber-500/10 text-amber-300 border border-amber-500/30 rounded-lg px-4 py-3">Risk Control Alert: Consider pausing trading.</div>
    @endif

    @if($stats['weeklyDrawdownPercent'] > 5)
        <div class="bg-red-500/10 text-red-300 border border-red-500/30 rounded-lg px-4 py-3">Weekly drawdown is {{ $stats['weeklyDrawdownPercent'] }}% (over 5%).</div>
    @endif

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
        <div class="xl:col-span-2 bg-slate-900 border border-slate-800 rounded-xl p-5">
            <h3 class="font-semibold text-lg mb-4">Risk Calculator</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div><label class="text-xs text-slate-400">Coin</label><input wire:model.defer="coin" type="text" class="w-full mt-1 px-3 py-2 rounded bg-slate-800 border border-slate-700"></div>
                <div><label class="text-xs text-slate-400">Capital</label><input wire:model.defer="capital" type="number" step="0.01" class="w-full mt-1 px-3 py-2 rounded bg-slate-800 border border-slate-700"></div>
                <div><label class="text-xs text-slate-400">Risk %</label><input wire:model.defer="riskPercent" type="number" step="0.01" class="w-full mt-1 px-3 py-2 rounded bg-slate-800 border border-slate-700"></div>
                <div><label class="text-xs text-slate-400">Stop Loss %</label><input wire:model.defer="stopLossPercent" type="number" step="0.01" class="w-full mt-1 px-3 py-2 rounded bg-slate-800 border border-slate-700"></div>
                <div><label class="text-xs text-slate-400">Entry Price</label><input wire:model.defer="entryPrice" type="number" step="0.00000001" class="w-full mt-1 px-3 py-2 rounded bg-slate-800 border border-slate-700"></div>
                <div><label class="text-xs text-slate-400">Take Profit RR</label><input wire:model.defer="takeProfitRr" type="text" placeholder="1:2" class="w-full mt-1 px-3 py-2 rounded bg-slate-800 border border-slate-700"></div>
            </div>
            <div class="mt-4 flex gap-3">
                <button wire:click="calculate" class="px-4 py-2 rounded bg-indigo-600 hover:bg-indigo-500">Calculate</button>
                <button wire:click="saveTrade" class="px-4 py-2 rounded bg-emerald-600 hover:bg-emerald-500">Save Trade</button>
            </div>
            @if($showSaveSuccess)
                <p class="text-emerald-400 text-sm mt-3">Trade saved successfully.</p>
            @endif
        </div>

        <div class="bg-slate-900 border border-slate-800 rounded-xl p-5">
            <h3 class="font-semibold text-lg mb-4">Calculation Result</h3>
            <div class="space-y-3 text-sm">
                <div class="flex justify-between"><span class="text-slate-400">Risk Amount</span><span>${{ number_format($riskAmount, 2) }}</span></div>
                <div class="flex justify-between"><span class="text-slate-400">Position Size</span><span>{{ number_format($positionSize, 8) }}</span></div>
                <div class="flex justify-between"><span class="text-slate-400">Take Profit Price</span><span>{{ number_format($takeProfitPrice, 8) }}</span></div>
                <div class="flex justify-between"><span class="text-slate-400">Expected Profit</span><span class="text-emerald-400">${{ number_format($expectedProfit, 2) }}</span></div>
                <div class="flex justify-between"><span class="text-slate-400">Average RR</span><span>{{ $stats['averageRr'] }}</span></div>
            </div>
        </div>
    </div>

    <div class="bg-slate-900 border border-slate-800 rounded-xl p-5">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3 mb-4">
            <h3 class="font-semibold text-lg">Trades</h3>
            <input wire:model.live.debounce.300ms="search" type="text" placeholder="Search by coin..." class="px-3 py-2 rounded bg-slate-800 border border-slate-700 md:w-64">
        </div>

        <div class="overflow-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-slate-400 border-b border-slate-800">
                        <th class="text-left py-2">Date</th><th class="text-left py-2">Coin</th><th class="text-left py-2">Capital</th><th class="text-left py-2">Risk $</th><th class="text-left py-2">SL %</th><th class="text-left py-2">Position Size</th><th class="text-left py-2">TP</th><th class="text-left py-2">Result</th><th class="text-left py-2">P/L</th><th class="text-left py-2">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($trades as $trade)
                        <tr class="border-b border-slate-800/80">
                            <td class="py-2">{{ $trade->created_at->format('Y-m-d') }}</td>
                            <td class="py-2">{{ $trade->coin }}</td>
                            <td class="py-2">${{ number_format($trade->capital, 2) }}</td>
                            <td class="py-2">${{ number_format($trade->risk_amount, 2) }}</td>
                            <td class="py-2">{{ $trade->stop_loss_percent }}%</td>
                            <td class="py-2">{{ number_format($trade->position_size, 8) }}</td>
                            <td class="py-2">{{ number_format($trade->take_profit_price, 8) }}</td>
                            <td class="py-2">
                                <select wire:change="updateResult({{ $trade->id }}, $event.target.value)" class="bg-slate-800 border border-slate-700 rounded px-2 py-1">
                                    <option value="pending" @selected($trade->result === 'pending')>Pending</option>
                                    <option value="win" @selected($trade->result === 'win')>Win</option>
                                    <option value="loss" @selected($trade->result === 'loss')>Loss</option>
                                </select>
                            </td>
                            <td class="py-2 {{ $trade->profit_loss >= 0 ? 'text-emerald-400' : 'text-red-400' }}">${{ number_format($trade->profit_loss, 2) }}</td>
                            <td class="py-2"><button wire:click="deleteTrade({{ $trade->id }})" class="text-red-400">Delete</button></td>
                        </tr>
                    @empty
                        <tr><td colspan="10" class="text-center py-6 text-slate-500">No trades found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $trades->links() }}</div>
    </div>

    <div class="bg-slate-900 border border-slate-800 rounded-xl p-5">
        <h3 class="font-semibold text-lg mb-4">Monthly Performance</h3>
        <canvas id="monthlyPerformance"></canvas>
    </div>

    @script
    <script>
        const monthlyData = @json($stats['monthlyPerformance']);
        const ctx = document.getElementById('monthlyPerformance');
        if (ctx && window.Chart) {
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: Object.keys(monthlyData),
                    datasets: [{
                        label: 'P/L',
                        data: Object.values(monthlyData),
                        backgroundColor: '#6366f1'
                    }]
                }
            });
        }
    </script>
    @endscript
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</div>
