<?php

namespace App\Console\Commands;

use App\Models\Transaction;
use Illuminate\Console\Command;
use Carbon\Carbon;

class TestTransactionSyncCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:transaction-sync {--days=1 : Number of days to simulate}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test transaction synchronization with simulated data';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🧪 Testing Transaction Synchronization...');
        
        $days = (int) $this->option('days');
        $startTime = now()->subDays($days)->startOfDay();
        $endTime = now()->endOfDay();

        $this->info("📅 Sync period: {$startTime->format('Y-m-d H:i:s')} to {$endTime->format('Y-m-d H:i:s')}");

        // Simular datos de diferentes endpoints
        $simulatedData = $this->generateSimulatedData($startTime, $endTime);
        
        $totalSynced = 0;
        
        foreach ($simulatedData as $type => $transactions) {
            $this->info("📊 Processing {$type}: " . count($transactions) . " transactions");
            
            foreach ($transactions as $txData) {
                try {
                    $transaction = Transaction::updateOrCreate(
                        [
                            'order_number' => $txData['order_number'],
                            'transaction_type' => $txData['transaction_type']
                        ],
                        array_merge($txData, [
                            'last_synced_at' => now(),
                            'source_endpoint' => $txData['source_endpoint'] ?? 'simulated'
                        ])
                    );
                    
                    if ($transaction->wasRecentlyCreated) {
                        $totalSynced++;
                        $this->line("  ✅ Created: {$txData['order_number']} ({$txData['asset_type']})");
                    } else {
                        $this->line("  🔄 Updated: {$txData['order_number']} ({$txData['asset_type']})");
                    }
                } catch (\Exception $e) {
                    $this->error("  ❌ Error with {$txData['order_number']}: " . $e->getMessage());
                }
            }
        }

        // Mostrar estadísticas
        $this->newLine();
        $this->info('📈 Sync Results:');
        $this->line("  Total transactions synced: {$totalSynced}");
        $this->line("  Total transactions in DB: " . Transaction::count());
        $this->line("  Completed transactions: " . Transaction::completed()->count());
        $this->line("  Transactions today: " . Transaction::whereDate('binance_create_time', now()->startOfDay())->count());

        // Mostrar breakdown por tipo
        $this->newLine();
        $this->info('📊 Breakdown by type:');
        Transaction::selectRaw('transaction_type, COUNT(*) as count')
            ->groupBy('transaction_type')
            ->get()
            ->each(function($item) {
                $this->line("  - {$item->transaction_type}: {$item->count}");
            });

        $this->newLine();
        $this->info('✅ Test synchronization completed successfully!');
        
        return 0;
    }

    private function generateSimulatedData(Carbon $startTime, Carbon $endTime): array
    {
        $data = [];
        
        // Simular trades spot
        $data['spot_trades'] = [
            [
                'order_number' => 'SPOT_' . time() . '_1',
                'transaction_type' => 'spot_trade',
                'asset_type' => 'BTC',
                'fiat_type' => 'USDT',
                'order_type' => 'BUY',
                'quantity' => '0.001',
                'price' => '45000.00',
                'total_price' => '45.00',
                'commission' => '0.045',
                'status' => 'completed',
                'binance_create_time' => $startTime->copy()->addHours(2),
                'source_endpoint' => '/api/v3/myTrades'
            ],
            [
                'order_number' => 'SPOT_' . time() . '_2',
                'transaction_type' => 'spot_trade',
                'asset_type' => 'ETH',
                'fiat_type' => 'USDT',
                'order_type' => 'SELL',
                'quantity' => '0.1',
                'price' => '3000.00',
                'total_price' => '300.00',
                'commission' => '0.3',
                'status' => 'completed',
                'binance_create_time' => $startTime->copy()->addHours(4),
                'source_endpoint' => '/api/v3/myTrades'
            ]
        ];

        // Simular órdenes P2P
        $data['p2p_orders'] = [
            [
                'order_number' => 'P2P_' . time() . '_1',
                'transaction_type' => 'p2p_order',
                'asset_type' => 'BTC',
                'fiat_type' => 'COP',
                'order_type' => 'BUY',
                'quantity' => '0.001',
                'price' => '180000000.00',
                'total_price' => '180000.00',
                'commission' => '0',
                'payment_method' => 'Bancolombia',
                'counter_party' => 'Trader123',
                'status' => 'completed',
                'binance_create_time' => $startTime->copy()->addHours(6),
                'source_endpoint' => '/sapi/v1/c2c/orderMatch/listUserOrderHistory'
            ]
        ];

        // Simular depósitos
        $data['deposits'] = [
            [
                'order_number' => 'DEP_' . time() . '_1',
                'transaction_type' => 'deposit',
                'asset_type' => 'USDT',
                'quantity' => '100.00',
                'status' => 'completed',
                'binance_create_time' => $startTime->copy()->addHours(1),
                'source_endpoint' => '/sapi/v1/capital/deposit/hisrec'
            ],
            [
                'order_number' => 'DEP_' . time() . '_2',
                'transaction_type' => 'deposit',
                'asset_type' => 'ETH',
                'quantity' => '0.5',
                'status' => 'completed',
                'binance_create_time' => $startTime->copy()->addHours(3),
                'source_endpoint' => '/sapi/v1/capital/deposit/hisrec'
            ]
        ];

        // Simular retiros
        $data['withdrawals'] = [
            [
                'order_number' => 'WTH_' . time() . '_1',
                'transaction_type' => 'withdrawal',
                'asset_type' => 'USDT',
                'quantity' => '50.00',
                'status' => 'completed',
                'binance_create_time' => $startTime->copy()->addHours(8),
                'source_endpoint' => '/sapi/v1/capital/withdraw/history'
            ]
        ];

        return $data;
    }
}










