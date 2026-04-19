<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
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
 */
class OohxSyncToEngine extends Command
{
    protected $signature   = 'oohx:sync-to-engine
                              {--skip-export : Dùng file JSON hiện có, không regenerate}
                              {--skip-ingest : Chỉ upload file, không trigger ingest}';
    protected $description = 'Export screens + rsync + trigger ingest trên Data Engine VPS.';

    public function handle(): int
    {
        $cfg = config('oohx.data_engine');

        $remoteHost  = $cfg['remote_host'];
        $remoteUser  = $cfg['remote_user'];
        $sshKey      = $cfg['ssh_key'];
        $remoteInbox = $cfg['remote_inbox'];

        $localFile = base_path('storage/app/oohx/screens.json');

        // 1. Export
        if (! $this->option('skip-export')) {
            $exit = $this->call('oohx:export-screens', ['--out' => 'storage/app/oohx/screens.json']);
            if ($exit !== self::SUCCESS) {
                $this->error('Export failed.');
                return self::FAILURE;
            }
        }
        if (! file_exists($localFile)) {
            $this->error("Export file not found: {$localFile}");
            return self::FAILURE;
        }

        // 2. Rsync
        $this->info("☁ Rsync to {$remoteUser}@{$remoteHost}:{$remoteInbox}/screens.json");
        $this->runCmd([
            'rsync', '-avz', '--chmod=644',
            '-e', "ssh -i {$sshKey} -o StrictHostKeyChecking=accept-new -o ConnectTimeout=15",
            $localFile,
            "{$remoteUser}@{$remoteHost}:{$remoteInbox}/screens.json",
        ]);

        // 3. Trigger ingest
        if (! $this->option('skip-ingest')) {
            $this->info('⚙ Triggering ingest-screens on Data Engine');
            $this->runCmd([
                'ssh', '-i', $sshKey,
                '-o', 'StrictHostKeyChecking=accept-new',
                '-o', 'ConnectTimeout=15',
                "{$remoteUser}@{$remoteHost}",
                'cd ~/python-data-engine && .venv/bin/python -m app.cli ingest-screens --file ~/inbox/screens.json',
            ]);
        }

        $this->newLine();
        $this->info('✔ Sync complete. Estimates sẽ được recompute trong vòng 10 phút (cron on Data Engine).');
        return self::SUCCESS;
    }

    /**
     * Run shell command, streaming output live. Timeout 300s.
     */
    private function runCmd(array $cmd): void
    {
        $p = new Process($cmd, null, null, null, 300);
        $p->mustRun(fn ($type, $buffer) => $this->line(rtrim($buffer)));
    }
}
