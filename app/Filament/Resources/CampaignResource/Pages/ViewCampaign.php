<?php

namespace App\Filament\Resources\CampaignResource\Pages;

use App\Filament\Resources\CampaignResource;
use App\Models\Campaign;
use App\Services\PaymentService;
use Filament\Actions;
use Filament\Forms;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewCampaign extends ViewRecord
{
    protected static string $resource = CampaignResource::class;

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make('Thông tin Campaign')
                ->schema([
                    Infolists\Components\Grid::make(3)->schema([
                        Infolists\Components\TextEntry::make('code')->label('Mã'),
                        Infolists\Components\TextEntry::make('name')->label('Tên'),
                        Infolists\Components\TextEntry::make('status')
                            ->label('Trạng thái')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'draft' => 'gray', 'pending_approval' => 'warning',
                                'approved' => 'info', 'active' => 'success',
                                'rejected' => 'danger', default => 'gray',
                            }),
                    ]),
                    Infolists\Components\Grid::make(3)->schema([
                        Infolists\Components\TextEntry::make('organization.name')->label('Tổ chức'),
                        Infolists\Components\TextEntry::make('brand_name')->label('Brand')->default('—'),
                        Infolists\Components\TextEntry::make('category')->label('Ngành hàng')->default('—'),
                    ]),
                    Infolists\Components\Grid::make(4)->schema([
                        Infolists\Components\TextEntry::make('start_date')->label('Bắt đầu')->date('d/m/Y'),
                        Infolists\Components\TextEntry::make('end_date')->label('Kết thúc')->date('d/m/Y'),
                        Infolists\Components\TextEntry::make('total_screens')->label('Màn hình'),
                        Infolists\Components\TextEntry::make('total_budget')->label('Budget')->money('VND')->default('—'),
                    ]),
                    Infolists\Components\TextEntry::make('notes')->label('Ghi chú')->default('—')->columnSpanFull(),
                ]),

            Infolists\Components\Section::make('Booking Lines')
                ->schema([
                    Infolists\Components\RepeatableEntry::make('bookingLines')
                        ->label('')
                        ->schema([
                            Infolists\Components\Grid::make(6)->schema([
                                Infolists\Components\TextEntry::make('screen.name')->label('Màn hình'),
                                Infolists\Components\TextEntry::make('owner.name')->label('Owner'),
                                Infolists\Components\TextEntry::make('start_date')->label('Từ')->date('d/m'),
                                Infolists\Components\TextEntry::make('end_date')->label('Đến')->date('d/m'),
                                Infolists\Components\TextEntry::make('estimated_cost')->label('Chi phí')->money('VND'),
                                Infolists\Components\TextEntry::make('status')->label('Trạng thái')->badge()
                                    ->color(fn (string $state): string => match ($state) {
                                        'pending' => 'warning', 'approved' => 'info',
                                        'active' => 'success', 'rejected' => 'danger',
                                        default => 'gray',
                                    }),
                            ]),
                        ]),
                ])
                ->collapsible(),

            Infolists\Components\Section::make('Payments')
                ->schema([
                    Infolists\Components\RepeatableEntry::make('payments')
                        ->label('')
                        ->schema([
                            Infolists\Components\Grid::make(5)->schema([
                                Infolists\Components\TextEntry::make('invoice_number')->label('Invoice'),
                                Infolists\Components\TextEntry::make('amount')->label('Số tiền')->money('VND'),
                                Infolists\Components\TextEntry::make('method')->label('Phương thức')
                                    ->formatStateUsing(fn (string $state): string => match ($state) {
                                        'bank_transfer' => 'Chuyển khoản',
                                        'vnpay' => 'VNPay',
                                        'momo' => 'MoMo',
                                        default => $state,
                                    }),
                                Infolists\Components\TextEntry::make('status')->label('Trạng thái')->badge()
                                    ->color(fn (string $state): string => match ($state) {
                                        'pending' => 'warning', 'completed' => 'success',
                                        'failed' => 'danger', default => 'gray',
                                    }),
                                Infolists\Components\TextEntry::make('paid_at')->label('Thanh toán lúc')->dateTime('d/m/Y H:i')->default('—'),
                            ]),
                        ]),
                ])
                ->collapsible(),

            Infolists\Components\Section::make('Lịch sử hoạt động')
                ->schema([
                    Infolists\Components\RepeatableEntry::make('activities')
                        ->label('')
                        ->schema([
                            Infolists\Components\Grid::make(3)->schema([
                                Infolists\Components\TextEntry::make('description')->label('Hoạt động'),
                                Infolists\Components\TextEntry::make('user.name')->label('Người thực hiện')->default('Hệ thống'),
                                Infolists\Components\TextEntry::make('created_at')->label('Thời gian')->dateTime('d/m/Y H:i'),
                            ]),
                        ]),
                ])
                ->collapsible()
                ->collapsed(),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('confirmPayment')
                ->label('Xác nhận thanh toán')
                ->icon('heroicon-o-banknotes')
                ->color('success')
                ->visible(fn () => $this->record->payments()->where('status', 'pending')->exists())
                ->form([
                    Forms\Components\TextInput::make('gateway_ref')
                        ->label('Mã giao dịch ngân hàng')
                        ->placeholder('VD: VCB-123456789'),
                ])
                ->requiresConfirmation()
                ->modalHeading('Xác nhận đã nhận thanh toán?')
                ->action(function (array $data) {
                    $payment = $this->record->payments()->where('status', 'pending')->latest()->first();
                    if ($payment) {
                        app(PaymentService::class)->confirmBankTransfer($payment, $data['gateway_ref'] ?? null);
                        Notification::make()->title('Thanh toán đã xác nhận')->success()->send();
                    }
                }),
        ];
    }
}
