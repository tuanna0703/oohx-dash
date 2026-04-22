<?php

namespace App\Console\Commands;

use App\Services\Oohx\HealthDigestService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

/**
 * Phase 3.A Part 2 — fetch DE health digest JSON via scp.
 *
 * Schedule mỗi 30 phút qua Laravel scheduler. DE cron viết file mỗi 08:00 UTC
 * (daily digest) nên chạy nhiều lần không tốn gì — idempotent scp với
 * same-mtime sẽ skip.
 *
 * Silent-fail khi:
 *   - SSH key không tồn tại (chưa setup infra)
 *   - Remote file chưa có (DE cron chưa chạy)
 *   - Network timeout
 *
 * Luôn log, không throw — cho chạy unattended trong cron.
 */
class OohxFetchHealthDigest extends Command
{
    protected $signature = 'oohx:fetch-health
                            {--date= : YYYYMMDD (default hôm nay UTC)}
                            {--all : Thử fetch cả 7 ngày gần nhất}';
    protected $description = 'SCP health digest JSON từ Data Engine VPS về storage/app/oohx-health.';

    private const CACHE_KEY = 'oohx:health:last_fetch';
    private const STORAGE_DIR = 'oohx-health';

    public function handle(HealthDigestService $service): int
    {
        $cfg = config('oohx.data_engine');

        $remoteHost = $cfg['remote_host'];
        $remoteUser = $cfg['remote_user'];
        $sshKey     = $cfg['ssh_key'];
        $remoteDir  = $cfg['health_digest_remote_dir'] ?? '/home/oohx/logs';

        if (! $sshKey || ! file_exists($sshKey)) {
            $msg = "SSH key not found at {$sshKey}. Configure OOHX_SSH_KEY in .env.";
            $this->error($msg);
            $this->recordFetch('failed', $msg);
            return self::FAILURE;
        }

        // Ensure local dir exists
        $localDir = Storage::disk('local')->path(self::STORAGE_DIR);
        if (! is_dir($localDir)) {
            @mkdir($localDir, 0755, true);
        }

        $dates = $this->targetDates();
        $successCount = 0;
        $lastError = null;

        foreach ($dates as $date) {
            $filename  = "health-digest-{$date}.json";
            $remotePath = "{$remoteDir}/{$filename}";
            $localPath  = "{$localDir}/{$filename}";

            $process = new Process([
                'scp',
                '-i', $sshKey,
                '-o', 'StrictHostKeyChecking=accept-new',
                '-o', 'ConnectTimeout=15',
                '-o', 'BatchMode=yes',
                "{$remoteUser}@{$remoteHost}:{$remotePath}",
                $localPath,
            ]);
            $process->setTimeout(30);
            $process->run();

            if ($process->isSuccessful()) {
                $this->info("✓ Fetched {$filename} (" . filesize($localPath) . ' bytes)');
                $successCount++;
            } else {
                $err = trim($process->getErrorOutput() ?: $process->getOutput()) ?: 'exit ' . $process->getExitCode();
                $lastError = "{$filename}: {$err}";
                $this->warn("✗ Skip {$filename} — {$err}");
            }
        }

        $service->forget(); // bust cache so next Filament read picks up new file

        if ($successCount === 0) {
            $this->recordFetch('failed', $lastError ?? 'No files fetched');
            Log::info('oohx:fetch-health — no files fetched', [
                'dates' => $dates,
                'error' => $lastError,
            ]);
            return self::FAILURE; // non-zero exit so scheduler can alert if needed
        }

        $this->recordFetch('success', "Fetched {$successCount}/" . count($dates) . ' file(s)');
        return self::SUCCESS;
    }

    /**
     * @return list<string>  YYYYMMDD date strings
     */
    private function targetDates(): array
    {
        if ($this->option('date')) {
            return [$this->option('date')];
        }

        if ($this->option('all')) {
            $now = now('UTC');
            return array_map(
                fn ($i) => $now->copy()->subDays($i)->format('Ymd'),
                range(0, 6),
            );
        }

        // Default: try today + yesterday (DE cron may not have run yet today)
        $now = now('UTC');
        return [$now->format('Ymd'), $now->copy()->subDay()->format('Ymd')];
    }

    private function recordFetch(string $status, ?string $message): void
    {
        Cache::put(self::CACHE_KEY, [
            'status'    => $status,
            'message'   => $message,
            'at'        => now()->toIso8601String(),
        ], now()->addDay());
    }
}
