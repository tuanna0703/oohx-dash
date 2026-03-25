<?php

namespace App\Jobs;

use App\Models\WebhookSubscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SendWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $timeout = 15;

    public function __construct(
        private readonly WebhookSubscription $subscription,
        private readonly string $event,
        private readonly array $data,
    ) {
        $this->onQueue('webhooks');
    }

    public function handle(): void
    {
        $payload = [
            'event'     => $this->event,
            'timestamp' => now()->toIso8601String(),
            'data'      => $this->data,
        ];

        $body      = json_encode($payload);
        $signature = 'sha256=' . hash_hmac('sha256', $body, $this->subscription->secret);

        $response = Http::timeout(10)
            ->withHeaders([
                'Content-Type'       => 'application/json',
                'X-TapOn-Signature'  => $signature,
                'X-TapOn-Event'      => $this->event,
            ])
            ->send('POST', $this->subscription->url, ['body' => $body]);

        if (! $response->successful()) {
            Log::warning('Webhook delivery failed', [
                'webhook_id' => $this->subscription->webhook_id,
                'url'        => $this->subscription->url,
                'event'      => $this->event,
                'status'     => $response->status(),
            ]);

            // Sau 3 lần fail, mark subscription inactive
            if ($this->attempts() >= $this->tries) {
                $this->subscription->update(['status' => 'inactive']);
                Log::error('Webhook subscription deactivated after 3 failures', [
                    'webhook_id' => $this->subscription->webhook_id,
                ]);
                return;
            }

            $this->fail("Webhook returned HTTP {$response->status()}");
        }
    }

    public function backoff(): array
    {
        return [30, 300, 1800]; // 30s, 5m, 30m
    }
}
