<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Services\EqrisService;
use App\Services\OrderFulfillment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class PollEqrisPendingOrders extends Command
{
    protected $signature = 'eqris:poll-pending {--minutes=60 : Window menit utk cek pending order}';

    protected $description = 'Polling Eqris untuk konfirmasi pembayaran order pending (Orkut + Gomerch). Jalankan tiap menit via scheduler.';

    public function handle(EqrisService $eqris, OrderFulfillment $fulfillment): int
    {
        $minutes = (int) $this->option('minutes');
        $threshold = now()->subMinutes($minutes);

        $pendings = Order::where('status', Order::STATUS_PENDING)
            ->where('gateway', Order::GATEWAY_EQRIS)
            ->where('created_at', '>=', $threshold)
            ->limit(100)
            ->get();

        $paid = 0;
        foreach ($pendings as $order) {
            try {
                if ($eqris->isOrderPaid($order)) {
                    $fulfillment->markPaidAndAssignStock($order, [
                        'amount' => (int) $order->total_payment,
                        'payment_method' => Order::GATEWAY_EQRIS,
                        'source' => 'eqris_poll',
                    ]);
                    $paid++;
                }
            } catch (\Throwable $e) {
                Log::warning('Eqris polling error', [
                    'order' => $order->order_code,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info("Polled {$pendings->count()} pending; {$paid} marked paid.");

        return self::SUCCESS;
    }
}
