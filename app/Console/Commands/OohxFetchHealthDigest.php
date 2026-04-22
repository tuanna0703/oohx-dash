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
 * Primary target: `health-digest-latest.json` (symlink DE refresh hourly +5min).
 * Secondary: date-pattern files `health-digest-YYYYMMDD.json` (audit history).
 *
 * Schedule mỗi 10 phút — match DE refresh cycle (hourly). Handoff §4.2.
 *
 * Silent-fail khi:
 *   - SSH key không tồn tại (chưa setup infra)
 *   - Remote file chưa có (first deploy / scp error)
 *   - Network timeout
 *
 * Luôn log, không throw — cho chạy unattended trong cron.
 */
class OohxFetchHealthDigest extends Command
{
    protected $signature = 'oohx:fetch-health
                            {--date= : YYYYMMDD (fetch 1 file theo ngày)}
                            {--all : Fetch cả 7 ngày gần nhất + latest}
                            {--latest-only : Chỉ fetch latest.json, skip date files}';
    protected $description = 'SCP health digest JSON từ Data Engine VPS về storage/app/oohx-health.';

    private const CACHE_KEY = 'oohx:health:last_fetch';
    private const STORAGE_DIR = 'oohx-health';
    private const LATEST_FILENAME = 'health-digest-latest.json';

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

        $localDir = Storage::disk('local')->path(self::STORAGE_DIR);
        if (! is_dir($localDir)) {
            @mkdir($localDir, 0775, true);
        }

        // Pre-flight writability check — báo sớm nếu user khác đã tạo dir
        // (typical aaPanel bug: manual run qua root tạo dir 0755 root:root,
        // sau đó scheduler chạy dưới www bị denied).
        if (! is_writable($localDir)) {
            $owner = function_exists('posix_getpwuid') && function_exists('fileowner')
                ? (posix_getpwuid(fileowner($localDir))['name'] ?? fileowner($localDir))
                : fileowner($localDir);
            $current = function_exists('posix_getpwuid') && function_exists('posix_geteuid')
                ? (posix_getpwuid(posix_geteuid())['name'] ?? 'unknown')
                : 'unknown';
            $msg = "Local dir {$localDir} not writable by current user ({$current}); owner={$owner}. "
                 . 'Fix: chown -R www:www storage/app/private/oohx-health && chmod -R 775 storage/app/private/oohx-health';
            $this->error($msg);
            $this->recordFetch('failed', $msg);
            return self::FAILURE;
        }

        $targets = $this->buildTargets();
        $successCount = 0;
        $totalAttempts = count($targets);
        $lastError = null;

        foreach ($targets as $filename) {
            $remotePath = "{$remoteDir}/{$filename}";
            $localPath  = "{$localDir}/{$filename}";

            $process = new Process([
                'scp',
                '-i', $sshKey,
                '-o', 'StrictHostKeyChecking=accept-new',
                '-o', 'ConnectTimeout=15',
                '-o', 'BatchMode=yes',
                '-q',
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

        $service->forget(); // bust cache so next read picks up fresh file

        if ($successCount === 0) {
            $this->recordFetch('failed', $lastError ?? 'No files fetched');
            Log::info('oohx:fetch-health — no files fetched', [
                'targets' => $targets,
                'error'   => $lastError,
            ]);
            return self::FAILURE;
        }

        $this->recordFetch('success', "Fetched {$successCount}/{$totalAttempts} file(s)");
        return self::SUCCESS;
    }

    /**
     * Build danh sách remote filenames cần scp.
     *
     * Default (scheduled run): chỉ `latest.json` — DE cron refresh hourly.
     * `--all`: latest + 7 ngày audit history.
     * `--date=YYYYMMDD`: chỉ file ngày đó.
     * `--latest-only`: chỉ latest (explicit, same as default).
     *
     * @return list<string>
     */
    private function buildTargets(): array
    {
        if ($this->option('date')) {
            return ["health-digest-{$this->option('date')}.json"];
        }

        if ($this->option('all')) {
            $now = now('UTC');
            $dates = array_map(
                fn ($i) => 'health-digest-' . $now->copy()->subDays($i)->format('Ymd') . '.json',
                range(0, 6),
            );
            return array_merge([self::LATEST_FILENAME], $dates);
        }

        // Default + --latest-only: only latest.json (per handoff §4.2)
        return [self::LATEST_FILENAME];
    }

    private function recordFetch(string $status, ?string $message): void
    {
        Cache::put(self::CACHE_KEY, [
            'status'  => $status,
            'message' => $message,
            'at'      => now()->toIso8601String(),
        ], now()->addDay());
    }
}
