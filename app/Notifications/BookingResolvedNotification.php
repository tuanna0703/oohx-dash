<?php

namespace App\Notifications;

use App\Models\Campaign;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BookingResolvedNotification extends Notification
{
    use Queueable;

    public function __construct(
        private Campaign $campaign,
        private string $action, // 'approved' or 'rejected'
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $fpDomain = config('domains.frontpage', 'oohx.net');
        $isApproved = $this->action === 'approved';

        $mail = (new MailMessage)
            ->subject(($isApproved ? 'Booking được duyệt' : 'Booking bị từ chối') . ": {$this->campaign->code}")
            ->greeting("Xin chào {$notifiable->name},");

        if ($isApproved) {
            $approved = $this->campaign->bookingLines()->where('status', 'approved')->count();
            $total = $this->campaign->bookingLines()->count();

            $mail->line("Campaign **{$this->campaign->name}** đã được duyệt!")
                ->line("**Màn hình được duyệt:** $approved / $total")
                ->action('Xem Campaign', "https://{$fpDomain}/my/campaigns/{$this->campaign->id}");
        } else {
            $mail->line("Campaign **{$this->campaign->name}** đã bị từ chối.")
                ->line("**Lý do:** " . ($this->campaign->rejection_reason ?? 'Không có lý do'))
                ->action('Xem Chi tiết', "https://{$fpDomain}/my/campaigns/{$this->campaign->id}");
        }

        return $mail;
    }
}
