<?php

namespace App\Filament\Pages;

use App\Services\OohxDataEngine as OohxDataEngineService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

/**
 * Control panel cho OOHX Data Engine integration.
 *
 * Read status của SSH tunnel + DB connection, trigger sync pipeline,
 * lookup estimate nhanh bằng uuid. Tất cả operations (sync, test conn,
 * export-only) được bọc trong Filament Action với requiresConfirmation.
 *
 * State last-sync lưu trong Cache key `oohx:last_sync` (không cần migration).
 */
class OohxDataEngine extends Page
{
    protected static ?string $navigationIcon  = 'heroicon-o-cpu-chip';
    protected static ?string $navigationGroup = 'System Settings';
    protected static ?string $navigationLabel = 'Data Engine';
    protected static ?int    $navigationSort  = 60;
    protected static ?string $title           = 'OOHX Data Engine';
    protected static ?string $slug            = 'oohx-data-engine';

    protected static string $view = 'filament.pages.oohx-data-engine';

    // ── Header actions ──────────────────────────────────────────────────

    protected function getHeaderActions(): array
    {
        return [
            $this->syncNowAction(),
            $this->exportOnlyAction(),
            $this->testConnectionAction(),
            $this->lookupAction(),
        ];
    }

    public function syncNowAction(): Action
    {
        return Action::make('syncNow')
            ->label('Sync now')
            ->icon('heroicon-o-cloud-arrow-up')
            ->color('primary')
            ->requiresConfirmation()
            ->modalHeading('Chạy full sync pipeline?')
            ->modalDescription('Sẽ export screens → rsync lên Data Engine VPS → trigger ingest. Có thể mất 30-60 giây tuỳ số screens.')
            ->modalSubmitActionLabel('Chạy ngay')
            ->action(function () {
                $this->runCommand('oohx:sync-to-engine', ['--triggered-by' => 'manual'], 'Sync hoàn tất');
            });
    }

    public function exportOnlyAction(): Action
    {
        return Action::make('exportOnly')
            ->label('Export only')
            ->icon('heroicon-o-arrow-down-tray')
            ->color('gray')
            ->requiresConfirmation()
            ->modalDescription('Chỉ generate file JSON, không upload hay trigger ingest. File lưu ở storage/app/oohx/screens.json.')
            ->action(function () {
                $this->runCommand('oohx:export-screens', [], 'Export xong');
            });
    }

    public function testConnectionAction(): Action
    {
        return Action::make('testConnection')
            ->label('Test connections')
            ->icon('heroicon-o-signal')
            ->color('info')
            ->action(function () {
                $result = $this->testAllConnections();

                Notification::make()
                    ->title('Connection test')
                    ->body(implode("\n", $result['messages']))
                    ->{$result['ok'] ? 'success' : 'danger'}()
                    ->persistent()
                    ->send();
            });
    }

    public function lookupAction(): Action
    {
        return Action::make('lookup')
            ->label('Lookup estimate')
            ->icon('heroicon-o-magnifying-glass')
            ->color('gray')
            ->form([
                \Filament\Forms\Components\TextInput::make('external_id')
                    ->label('External ID (Laravel screen UUID)')
                    ->required()
                    ->placeholder('vd: 1f5cbf19-c9c3-4637-ad1a-b5cc37381bd9'),
            ])
            ->action(function (array $data) {
                $e = app(OohxDataEngineService::class)
                    ->getEstimateByExternalId($data['external_id']);

                if (! $e) {
                    Notification::make()
                        ->title('Không tìm thấy estimate')
                        ->body("UUID: {$data['external_id']}\nCó thể chưa sync, chưa recompute, hoặc UUID sai.")
                        ->warning()
                        ->persistent()
                        ->send();
                    return;
                }

                $body = sprintf(
                    "%s (%s)\nDaily: %s · Monthly: %s\nConfidence: %s · Method: %s\nLast calc: %s",
                    $e->name ?? '—',
                    $e->indoor_outdoor ?? '—',
                    number_format((float) ($e->estimated_daily_impressions ?? 0)),
                    number_format((float) ($e->estimated_monthly_impressions ?? 0)),
                    $e->confidence_score ?? '—',
                    $e->estimation_method ?? '—',
                    $e->last_calculated_at ?? '—',
                );

                Notification::make()
                    ->title('Estimate found')
                    ->body($body)
                    ->success()
                    ->persistent()
                    ->send();
            });
    }

    // ── View data ───────────────────────────────────────────────────────

    public function getViewData(): array
    {
        $lastSync = Cache::get('oohx:last_sync');
        $stats = $this->computeStats();

        return [
            'lastSync'    => $lastSync,
            'stats'       => $stats,
            'configCheck' => $this->configCheck(),
        ];
    }

    // ── Helpers ─────────────────────────────────────────────────────────

    /**
     * Run an artisan command + show result as Filament notification.
     * Output dài được cache vào `oohx:last_sync` bởi command; ở đây chỉ show status.
     */
    private function runCommand(string $command, array $args, string $successTitle): void
    {
        $exitCode = Artisan::call($command, $args);
        $output = Artisan::output();

        if ($exitCode === 0) {
            Notification::make()
                ->title($successTitle)
                ->body(mb_strimwidth($output, 0, 500, '…'))
                ->success()
                ->persistent()
                ->send();
        } else {
            Notification::make()
                ->title("Command failed: {$command}")
                ->body(mb_strimwidth($output, 0, 1000, '…'))
                ->danger()
                ->persistent()
                ->send();
        }
    }

    /**
     * Test 3 connections:
     *   - PostgreSQL via tunnel (inbound)
     *   - SSH to Data Engine VPS (outbound)
     *   - Data Engine repo path exists
     */
    private function testAllConnections(): array
    {
        $messages = [];
        $allOk = true;

        // 1. DB connection via tunnel
        try {
            $r = DB::connection('oohx')->selectOne('SELECT 1 as ok');
            $messages[] = '✓ PostgreSQL tunnel (127.0.0.1:5433) — OK';
        } catch (\Throwable $e) {
            $allOk = false;
            $messages[] = '✗ PostgreSQL tunnel — ' . mb_strimwidth($e->getMessage(), 0, 150, '…');
        }

        // 2. SSH connection to Data Engine
        $cfg = config('oohx.data_engine');
        try {
            $p = new Process([
                'ssh', '-i', $cfg['ssh_key'],
                '-o', 'StrictHostKeyChecking=accept-new',
                '-o', 'ConnectTimeout=10',
                '-o', 'BatchMode=yes',
                "{$cfg['remote_user']}@{$cfg['remote_host']}",
                'echo ok',
            ], null, null, null, 15);
            $p->run();
            if ($p->isSuccessful() && str_contains($p->getOutput(), 'ok')) {
                $messages[] = '✓ SSH to Data Engine VPS — OK';
            } else {
                $allOk = false;
                $messages[] = '✗ SSH — ' . mb_strimwidth($p->getErrorOutput() ?: 'unknown error', 0, 150, '…');
            }
        } catch (\Throwable $e) {
            $allOk = false;
            $messages[] = '✗ SSH — ' . mb_strimwidth($e->getMessage(), 0, 150, '…');
        }

        return ['ok' => $allOk, 'messages' => $messages];
    }

    /**
     * Aggregate stats từ Data Engine DB để show trên dashboard.
     */
    private function computeStats(): array
    {
        try {
            $a = DB::connection('oohx')->selectOne(
                'SELECT COUNT(*) as screens_total FROM core.screens'
            );
            $b = DB::connection('oohx')->selectOne(
                'SELECT COUNT(*) as est_total, MAX(last_calculated_at) as last_calc
                 FROM output.screen_traffic_estimates'
            );
            $c = DB::connection('oohx')->selectOne(
                "SELECT COUNT(*) as recent_synced FROM core.screens
                 WHERE synced_at > NOW() - INTERVAL '1 hour'"
            );

            return [
                'connected'        => true,
                'screens_total'    => (int) $a->screens_total,
                'estimates_total'  => (int) $b->est_total,
                'last_calculated'  => $b->last_calc,
                'recent_synced_1h' => (int) $c->recent_synced,
            ];
        } catch (\Throwable $e) {
            return [
                'connected' => false,
                'error'     => $e->getMessage(),
            ];
        }
    }

    /**
     * Quick config sanity checks (để show warning khi setup thiếu).
     *
     * Dùng `@file_exists()` để tránh open_basedir warning làm crash page khi
     * SSH key đặt ngoài project path (PHP-FPM thường sandbox /root/).
     */
    private function configCheck(): array
    {
        $cfg = config('oohx.data_engine');
        $checks = [];

        $checks[] = [
            'ok'    => ! empty(config('database.connections.oohx.password')),
            'label' => 'DB password configured',
            'hint'  => 'Set DB_OOHX_PASSWORD in .env',
        ];

        $sshKeyOk = false;
        $sshHint  = 'Generate via ssh-keygen, set OOHX_SSH_KEY in .env';
        try {
            $sshKeyOk = @is_readable($cfg['ssh_key']);
            if (! $sshKeyOk) {
                $sshHint .= '. Nếu key nằm ngoài project (vd /root/.ssh/) PHP-FPM không đọc được (open_basedir). Move vào storage/app/oohx-ssh/ và chown cho www user.';
            }
        } catch (\Throwable $e) {
            $sshHint = 'Open_basedir chặn: ' . $e->getMessage() . '. Move key vào ' . base_path('storage/app/oohx-ssh/') . '.';
        }
        $checks[] = [
            'ok'    => $sshKeyOk,
            'label' => "SSH key readable: {$cfg['ssh_key']}",
            'hint'  => $sshHint,
        ];

        $checks[] = [
            'ok'    => ! empty($cfg['ingest_cmd']),
            'label' => 'Ingest command configured',
            'hint'  => 'Set OOHX_INGEST_CMD in .env',
        ];

        return $checks;
    }
}
