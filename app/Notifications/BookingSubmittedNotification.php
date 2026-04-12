<?php

namespace App\Notifications;

use App\Models\Campaign;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BookingSubmittedNotification extends Notification
{
    use Queueable;

    public function __construct(
        private Campaign $campaign,
        private int $lineCount,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $dashUrl = config('domains.dash', 'dash.oohx.net');

        return (new MailMessage)
            ->subject("Booking mới: {$this->campaign->code} — {$this->campaign->name}")
            ->greeting("Xin chào {$notifiable->name},")
            ->line("Bạn có booking mới cần duyệt từ **{$this->campaign->organization->name}**.")
            ->line("**Campaign:** {$this->campaign->name}")
            ->line("**Số màn hình:** {$this->lineCount}")
            ->line("**Thời gian:** {$this->campaign->start_date->format('d/m/Y')} → {$this->campaign->end_date->format('d/m/Y')}")
            ->action('Xem & Duyệt', "https://{$dashUrl}/publisher/booking-inboxes/{$this->campaign->id}")
            ->line('Vui lòng phản hồi trong vòng 48h.');
    }
}
