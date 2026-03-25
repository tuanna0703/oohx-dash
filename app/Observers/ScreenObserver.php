<?php

namespace App\Observers;

use App\Jobs\SendWebhookJob;
use App\Models\Screen;
use App\Models\WebhookSubscription;

class ScreenObserver
{
    public function created(Screen $screen): void
    {
        $this->dispatch('screen.created', $screen, [
            'screen_id' => $screen->external_id ?? $screen->uuid,
        ]);
    }

    public function updated(Screen $screen): void
    {
        $dirty = array_keys($screen->getDirty());

        // Nếu chỉ active chuyển về false → phát event deactivated thay vì updated
        if ($dirty === ['active'] && ! $screen->active) {
            $this->dispatch('screen.deactivated', $screen, [
                'screen_id' => $screen->external_id ?? $screen->uuid,
            ]);
            return;
        }

        $this->dispatch('screen.updated', $screen, [
            'screen_id'      => $screen->external_id ?? $screen->uuid,
            'changed_fields' => $dirty,
        ]);
    }

    public function deleted(Screen $screen): void
    {
        $this->dispatch('screen.deactivated', $screen, [
            'screen_id' => $screen->external_id ?? $screen->uuid,
        ]);
    }

    private function dispatch(string $event, Screen $screen, array $data): void
    {
        WebhookSubscription::active()
            ->forEvent($event)
            ->get()
            ->each(fn($sub) => SendWebhookJob::dispatch($sub, $event, $data));
    }
}
