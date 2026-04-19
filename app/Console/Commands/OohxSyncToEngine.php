<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\Process\Process;

/**
 * Full outbound pipeline:
 *   1. Export screens → JSON
 *   2. rsync lên Data Engine VPS
 *   3. SSH trigger `ingest-screens`
 *
 * Data Engine cron recompute-pending-jobs chạy 10 phút một lần → không cần
 * đợi/ping sau khi ingest. Lần đầu hoặc cần chạy ngay, ops có thể gọi
 * `recompute-city --city Hanoi` thủ công.
 *
 * Cache `oohx:last_sync` lưu kết quả lần chạy gần nhất để Filament UI hiển thị.
 */
class OohxSyncToEngine extends Command
{
    protected $signature   = 'oohx:sync-to-engine
                              {--skip-export : Dùng file JSON hiện có, không regenerate}
                              {--skip-ingest : Chỉ upload file, không trigger ingest}
                              {--triggered-by=scheduled : Source label lưu vào cache (scheduled|manual|email)}';
    protected $description = 'Export screens + rsync + trigger ingest trên Data Engine VPS.';

    private const CACHE_KEY = 'oohx:last_sync';
    private const CACHE_TTL_DAYS = 7;

    private string $outputBuffer = '';

    public function handle(): int
    {
        $startedAt = now();
        $cfg = config('oohx.data_engine');

        $remoteHost  = $cfg['remote_host'];
        $remoteUser  = $cfg['remote_user'];
        $sshKey      = $cfg['ssh_key'];
        $remoteInbox = $cfg['remote_inbox'];

        $localFile = base_path('storage/app/oohx/screens.json');

        try {
            // 1. Export
            if (! $this->option('skip-export')) {
                $exit = $this->call('oohx:export-screens', ['--out' => 'storage/app/oohx/screens.json']);
                if ($exit !== self::SUCCESS) {
                    throw new \RuntimeException('Export command failed');
                }
            }
            if (! file_exists($localFile)) {
                throw new \RuntimeException("Export file not found: {$localFile}");
            }

            // 2. Ensure remote inbox exists (idempotent)
            $this->info("🔧 Ensuring remote inbox exists: {$remoteInbox}");
            $this->runCmd([
                'ssh', '-i', $sshKey,
                '-o', 'StrictHostKeyChecking=accept-new',
                '-o', 'ConnectTimeout=15',
                "{$remoteUser}@{$remoteHost}",
                "mkdir -p {$remoteInbox}",
            ]);

            // 3. Rsync
            $this->info("☁ Rsync to {$remoteUser}@{$remoteHost}:{$remoteInbox}/screens.json");
            $this->runCmd([
                'rsync', '-avz', '--chmod=644',
                '-e', "ssh -i {$sshKey} -o StrictHostKeyChecking=accept-new -o ConnectTimeout=15",
                $localFile,
                "{$remoteUser}@{$remoteHost}:{$remoteInbox}/screens.json",
            ]);

            // 4. Trigger ingest
            $ingestStats = ['ok' => null, 'fail' => null, 'total' => null];
            if (! $this->option('skip-ingest')) {
                $this->info('⚙ Triggering ingest-screens on Data Engine');
                $ingestOutput = $this->runCmd([
                    'ssh', '-i', $sshKey,
                    '-o', 'StrictHostKeyChecking=accept-new',
                    '-o', 'ConnectTimeout=15',
                    "{$remoteUser}@{$remoteHost}",
                    $cfg['ingest_cmd'],
                ]);
                $ingestStats = $this->parseIngestOutput($ingestOutput);
            }

            $this->newLine();
            $this->info('✔ Sync complete. Estimates sẽ được recompute trong vòng 10 phút (cron on Data Engine).');

            // Cache success result
            Cache::put(self::CACHE_KEY, [
                'status'        => 'success',
                'started_at'    => $startedAt->toIso8601String(),
                'finished_at'   => now()->toIso8601String(),
                'duration_sec'  => $startedAt->diffInSeconds(now()),
                'triggered_by'  => $this->option('triggered-by'),
                'ingest_ok'     => $ingestStats['ok'],
                'ingest_fail'   => $ingestStats['fail'],
                'ingest_total'  => $ingestStats['total'],
                'file_size_kb'  => file_exists($localFile) ? round(filesize($localFile) / 1024, 1) : null,
                'output'        => mb_substr($this->outputBuffer, -3000), // last 3KB
                'error'         => null,
            ], now()->addDays(self::CACHE_TTL_DAYS));

            return self::SUCCESS;
        } catch (\Throwable $e) {
            Cache::put(self::CACHE_KEY, [
                'status'       => 'failed',
                'started_at'   => $startedAt->toIso8601String(),
                'finished_at'  => now()->toIso8601String(),
                'duration_sec' => $startedAt->diffInSeconds(now()),
                'triggered_by' => $this->option('triggered-by'),
                'output'       => mb_substr($this->outputBuffer, -3000),
                'error'        => $e->getMessage(),
            ], now()->addDays(self::CACHE_TTL_DAYS));

            $this->error('✗ Sync failed: ' . $e->getMessage());
            return self::FAILURE;
        }
    }

    /**
     * Parse ingest JSON output: {"file": ..., "ok": N, "fail": M, "total": T}
     */
    private function parseIngestOutput(string $out): array
    {
        if (preg_match('/\{[^}]*"ok"\s*:\s*(\d+)[^}]*"fail"\s*:\s*(\d+)[^}]*"total"\s*:\s*(\d+)/', $out, $m)) {
            return ['ok' => (int) $m[1], 'fail' => (int) $m[2], 'total' => (int) $m[3]];
        }
        return ['ok' => null, 'fail' => null, 'total' => null];
    }

    /**
     * Run shell command, stream output live, also buffer for caching.
     * Returns the captured stdout.
     */
    private function runCmd(array $cmd): string
    {
        $captured = '';
        $p = new Process($cmd, null, null, null, 300);
        $p->mustRun(function ($type, $buffer) use (&$captured) {
            $line = rtrim($buffer);
            $this->line($line);
            $captured .= $line . "\n";
            $this->outputBuffer .= $line . "\n";
        });
        return $captured;
    }
}
