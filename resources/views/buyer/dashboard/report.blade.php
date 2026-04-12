@extends('frontpage.layouts.app', ['activeNav' => 'dashboard', 'bodyClass' => ''])

@section('title', 'Báo cáo — {{ $campaign->name }} | OOHX')

@push('head')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
@endpush

@section('content')
<div class="w" style="padding-top:24px;padding-bottom:64px">

    <div class="buyer-welcome">
        <div>
            <div style="font-size:12px;color:var(--t4);font-weight:600;margin-bottom:4px">{{ $campaign->code }}</div>
            <h1 class="buyer-welcome-title">{{ $campaign->name }}</h1>
            <p class="buyer-welcome-sub">Báo cáo campaign</p>
        </div>
        <a href="{{ route('buyer.campaigns.show', $campaign) }}" class="btn btn-s btn-sm">Chi tiết Campaign</a>
    </div>

    {{-- Overview stats --}}
    <div class="rpt-stats">
        <div class="rpt-stat">
            <div class="rpt-stat-n">{{ number_format($overview['total_actual_impressions']) }}</div>
            <div class="rpt-stat-l">Impressions thực tế</div>
            <div class="rpt-stat-sub">/ {{ number_format($overview['total_estimated_impressions']) }} ước tính</div>
        </div>
        <div class="rpt-stat">
            <div class="rpt-stat-n" style="color:{{ $overview['delivery_rate'] >= 80 ? 'var(--grn)' : ($overview['delivery_rate'] >= 50 ? 'var(--org)' : 'var(--red)') }}">{{ $overview['delivery_rate'] }}%</div>
            <div class="rpt-stat-l">Delivery Rate</div>
            <div class="rpt-stat-sub">{{ $overview['days_elapsed'] }}/{{ $overview['days_total'] }} ngày</div>
        </div>
        <div class="rpt-stat">
            <div class="rpt-stat-n">{{ number_format($overview['total_actual_cost'], 0, ',', '.') }}</div>
            <div class="rpt-stat-l">Chi phí thực tế (₫)</div>
            <div class="rpt-stat-sub">/ {{ number_format($overview['total_estimated_cost'], 0, ',', '.') }} ước tính</div>
        </div>
        <div class="rpt-stat">
            <div class="rpt-stat-n">{{ $overview['total_screens'] }}</div>
            <div class="rpt-stat-l">Màn hình</div>
            <div class="rpt-stat-sub">{{ $overview['days_remaining'] }} ngày còn lại</div>
        </div>
    </div>

    {{-- Progress bar --}}
    <div class="rpt-progress">
        <div class="rpt-progress-head">
            <span>Tiến độ campaign</span>
            <span>{{ $overview['progress_pct'] }}%</span>
        </div>
        <div class="rpt-progress-bar">
            <div class="rpt-progress-fill" style="width:{{ $overview['progress_pct'] }}%"></div>
        </div>
    </div>

    {{-- Daily impressions chart --}}
    <div class="rpt-card">
        <div class="rpt-card-title">Impressions theo ngày</div>
        <div style="position:relative;height:300px">
            <canvas id="impressionsChart"></canvas>
        </div>
    </div>

    {{-- Screen breakdown table --}}
    <div class="rpt-card" style="margin-top:16px">
        <div class="rpt-card-title">Chi tiết theo màn hình</div>
        <div class="rpt-table-wrap">
            <table class="rpt-table">
                <thead>
                    <tr>
                        <th>Màn hình</th>
                        <th>Owner</th>
                        <th>Thời gian</th>
                        <th class="rpt-num">Ước tính</th>
                        <th class="rpt-num">Thực tế</th>
                        <th class="rpt-num">Delivery</th>
                        <th class="rpt-num">Chi phí</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($breakdown as $row)
                    <tr>
                        <td><strong>{{ $row['screen_name'] }}</strong><div style="font-size:11px;color:var(--t4)">{{ $row['city'] }}</div></td>
                        <td>{{ $row['owner_name'] }}</td>
                        <td>{{ $row['dates'] }}</td>
                        <td class="rpt-num">{{ number_format($row['estimated_impressions']) }}</td>
                        <td class="rpt-num">{{ number_format($row['actual_impressions']) }}</td>
                        <td class="rpt-num">
                            <span style="color:{{ $row['delivery_rate'] >= 80 ? 'var(--grn)' : ($row['delivery_rate'] >= 50 ? 'var(--org)' : 'var(--red)') }}">{{ $row['delivery_rate'] }}%</span>
                        </td>
                        <td class="rpt-num">{{ number_format($row['actual_cost'], 0, ',', '.') }} ₫</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function(){
    var ctx = document.getElementById('impressionsChart');
    if (!ctx) return;

    var data = @json($dailyData);

    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: data.labels,
            datasets: [{
                label: 'Impressions',
                data: data.impressions,
                backgroundColor: 'rgba(42,79,246,.15)',
                borderColor: 'rgba(42,79,246,.8)',
                borderWidth: 1.5,
                borderRadius: 4,
                maxBarThickness: 32,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: function(ctx) {
                            return ctx.parsed.y.toLocaleString('vi-VN') + ' impressions';
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: { color: 'rgba(0,0,0,.05)' },
                    ticks: {
                        callback: function(val) {
                            if (val >= 1000000) return (val/1000000).toFixed(1) + 'M';
                            if (val >= 1000) return (val/1000).toFixed(0) + 'K';
                            return val;
                        },
                        font: { size: 11, family: 'var(--font)' },
                        color: '#999',
                    }
                },
                x: {
                    grid: { display: false },
                    ticks: { font: { size: 11, family: 'var(--font)' }, color: '#999', maxRotation: 0 }
                }
            }
        }
    });
})();
</script>
@endpush
