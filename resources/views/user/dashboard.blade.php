@extends('adminlte::page')

@section('title', 'ภาพรวมการขาย | PrimeForecast')

@section('content_header')
@stop

@section('content')
@php
    $targetValue = (float) ($winForecastData->Target ?? 0);
    $forecastValue = (float) ($winForecastData->Forecast ?? 0);
    $winValue = (float) ($winForecastData->Win ?? 0);
    $winRate = $forecastValue > 0 ? ($winValue / $forecastValue) * 100 : 0;
    $displayName = auth()->user()->nname ?? auth()->user()->name ?? 'Sales';
    $activeYear = $selectedYear ?? date('Y');
    $moneyShort = function ($value) {
        $value = (float) $value;
        if ($value >= 1000000) return '฿' . number_format($value / 1000000, 1) . 'M';
        if ($value >= 1000) return '฿' . number_format($value / 1000, 1) . 'K';
        return '฿' . number_format($value, 0);
    };
@endphp

<div class="pf-v3">
    <main class="pf-shell">
        @if(session('success'))
            <div class="pf-alert pf-alert-success"><i class="fas fa-check-circle"></i><span>{{ session('success') }}</span></div>
        @endif

        <header class="pf-page-head">
            <div>
                <span class="pf-eyebrow">MY PERFORMANCE</span>
                <h1>สวัสดี {{ $displayName }} 👋</h1>
                <p>ติดตาม Forecast และโครงการที่ต้องดำเนินการของคุณในที่เดียว</p>
            </div>
            <div class="pf-head-actions">
                <a href="{{ route('user.dashboard.table') }}" class="pf-btn"><i class="fas fa-list"></i> โครงการของฉัน</a>
                <a href="{{ route('user.sales.create') }}" class="pf-btn pf-btn-primary"><i class="fas fa-plus"></i> เพิ่มโครงการ</a>
            </div>
        </header>

        <section class="pf-filterbar" aria-label="ตัวกรองข้อมูล">
            <form method="GET" action="{{ route('user.dashboard') }}">
                <select name="year" aria-label="ปีงบประมาณ" onchange="this.form.submit()">
                    <option value="all" {{ $activeYear === 'all' ? 'selected' : '' }}>ทุกปีงบประมาณ</option>
                    @foreach($availableYears as $year)
                        <option value="{{ $year }}" {{ (string) $activeYear === (string) $year ? 'selected' : '' }}>ปีงบประมาณ {{ $year + 543 }}</option>
                    @endforeach
                </select>
                <select name="quarter" aria-label="ไตรมาส" onchange="this.form.submit()">
                    <option value="">ทุกไตรมาส</option>
                    @for($q = 1; $q <= 4; $q++)
                        <option value="{{ $q }}" {{ (string) $quarter === (string) $q ? 'selected' : '' }}>ไตรมาส {{ $q }}</option>
                    @endfor
                </select>
                @if($activeYear === 'all' || $quarter)
                    <a href="{{ route('user.dashboard') }}" class="pf-btn pf-icon-btn" title="ล้างตัวกรอง"><i class="fas fa-undo"></i></a>
                @endif
            </form>
            <span class="pf-filter-meta"><i class="far fa-clock"></i> อัปเดตล่าสุด {{ now()->locale('th')->diffForHumans() }}</span>
        </section>

        <section class="pf-kpi-grid" aria-label="ตัวชี้วัดสำคัญ">
            <article class="pf-card pf-kpi">
                <div class="pf-kpi-top"><small>Forecast ของฉัน</small><span class="pf-kpi-icon"><i class="fas fa-chart-line"></i></span></div>
                <strong>{{ $moneyShort($forecastValue) }}</strong><em>มูลค่า Pipeline ปัจจุบัน</em>
            </article>
            <article class="pf-card pf-kpi">
                <div class="pf-kpi-top"><small>ยอด Win</small><span class="pf-kpi-icon" style="color:var(--pf-mint);background:#e9fbf6"><i class="fas fa-check"></i></span></div>
                <strong>{{ $moneyShort($winValue) }}</strong><em class="positive">{{ $forecastValue > 0 ? number_format(($winValue / $forecastValue) * 100, 1) : 0 }}% ของ Forecast</em>
            </article>
            <article class="pf-card pf-kpi">
                <div class="pf-kpi-top"><small>Win rate</small><span class="pf-kpi-icon" style="color:var(--pf-amber);background:#fff7e6"><i class="fas fa-bullseye"></i></span></div>
                <strong>{{ number_format($winRate, 1) }}%</strong><em>เปรียบเทียบจากมูลค่าโครงการ</em>
            </article>
            <article class="pf-card pf-kpi">
                <div class="pf-kpi-top"><small>โครงการทั้งหมด</small><span class="pf-kpi-icon" style="color:var(--pf-sky);background:#edf5ff"><i class="fas fa-layer-group"></i></span></div>
                <strong>{{ number_format($projectCount) }}</strong><em>{{ count($attentionProjects) }} โครงการที่ควรติดตาม</em>
            </article>
        </section>

        <section class="pf-dashboard-grid">
            <article class="pf-card pf-chart-card">
                <header class="pf-card-head">
                    <div><h2>Target / Forecast / Win</h2><p>ภาพรวมผลงานตามตัวกรองที่เลือก</p></div>
                    <button class="pf-btn pf-icon-btn" type="button" title="ดูข้อมูลใหม่" onclick="window.location.reload()"><i class="fas fa-sync-alt"></i></button>
                </header>
                <div class="pf-chart-wrap"><canvas id="performanceChart"></canvas></div>
            </article>

            <div class="pf-side-stack">
                <article class="pf-card">
                    <header class="pf-card-head">
                        <div><h3>สิ่งที่ต้องติดตาม</h3><p>เรียงตามวันสำคัญของโครงการ</p></div>
                        <a href="{{ route('user.dashboard.table') }}" class="pf-btn-link">ดูทั้งหมด</a>
                    </header>
                    <div class="pf-card-body pf-task-list">
                        @forelse($attentionProjects as $project)
                            @php
                                $due = $project->due_date ? \Carbon\Carbon::parse($project->due_date) : null;
                                $days = $due ? now()->startOfDay()->diffInDays($due->startOfDay(), false) : null;
                            @endphp
                            <a class="pf-task" href="{{ route('user.sales.edit', $project->transac_id) }}">
                                <span class="pf-task-icon"><i class="far fa-clock"></i></span>
                                <span><b>{{ $project->Product_detail }}</b><small>{{ $project->company ?? 'ไม่ระบุลูกค้า' }} · {{ $project->step_level ?? 'ยังไม่ระบุสถานะ' }}</small></span>
                                <time>{{ $days === null ? '-' : ($days < 0 ? 'เลยกำหนด' : ($days === 0 ? 'วันนี้' : 'อีก '.$days.' วัน')) }}</time>
                            </a>
                        @empty
                            <p class="pf-muted text-center mb-0 py-3">ยังไม่มีโครงการที่ต้องติดตาม</p>
                        @endforelse
                    </div>
                </article>

                <article class="pf-card">
                    <header class="pf-card-head">
                        <div><h3>โครงการล่าสุด</h3><p>รายการที่คุณอัปเดตล่าสุด</p></div>
                        <a href="{{ route('user.dashboard.table') }}" class="pf-btn-link">ดูทั้งหมด</a>
                    </header>
                    <div class="pf-card-body pf-project-list">
                        @forelse($recentProjects->take(3) as $project)
                            <a class="pf-recent" href="{{ route('user.sales.edit', $project->transac_id) }}">
                                <span class="pf-project-icon">{{ mb_strtoupper(mb_substr($project->Product_detail, 0, 2)) }}</span>
                                <span><b>{{ $project->Product_detail }}</b><small>{{ $project->company ?? 'ไม่ระบุลูกค้า' }} · {{ $moneyShort($project->product_value) }}</small></span>
                                <em class="pf-status" data-status="{{ $project->step_level }}">{{ $project->step_level ?? 'Draft' }}</em>
                            </a>
                        @empty
                            <p class="pf-muted text-center mb-0 py-3">ยังไม่มีข้อมูลโครงการ</p>
                        @endforelse
                    </div>
                </article>
            </div>
        </section>

        <article class="pf-card mt-3">
            <header class="pf-card-head">
                <div><h3>Pipeline ตามเดือนและสถานะ</h3><p>คลิกแท่งกราฟเพื่อดูรายละเอียดโครงการ</p></div>
            </header>
            <div class="pf-chart-wrap"><canvas id="pipelineChart"></canvas></div>
        </article>
    </main>

    @include('user.partials.mobile-nav')
</div>

<div class="modal fade" id="projectDetailModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title" id="projectDetailTitle">รายละเอียดโครงการ</h5><button type="button" class="close" data-dismiss="modal" aria-label="ปิด"><span aria-hidden="true">&times;</span></button></div>
            <div class="modal-body">
                <div id="projectDetailLoading" class="text-center py-5"><i class="fas fa-circle-notch fa-spin"></i> กำลังโหลดข้อมูล</div>
                <div class="table-responsive d-none" id="projectDetailTableWrap">
                    <table class="table table-hover"><thead><tr><th>โครงการ</th><th>ลูกค้า</th><th>กลุ่มสินค้า</th><th>สถานะ</th><th class="text-right">มูลค่า</th></tr></thead><tbody id="projectDetailBody"></tbody></table>
                </div>
                <p id="projectDetailEmpty" class="text-center text-muted d-none py-4">ไม่พบข้อมูล</p>
            </div>
        </div>
    </div>
</div>
@stop

@section('css')
<link rel="stylesheet" href="{{ asset('css/sales-v3.css') }}">
@stop

@section('js')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
(function () {
    const performance = @json($winForecastData);
    const pipeline = @json($saleStepData);
    const money = new Intl.NumberFormat('th-TH', { notation: 'compact', maximumFractionDigits: 1 });
    const colors = { target: '#a8afc3', forecast: '#635bff', win: '#16b88a' };

    function showProjects(type, value, title, value2) {
        const modal = $('#projectDetailModal');
        const body = document.getElementById('projectDetailBody');
        const loading = document.getElementById('projectDetailLoading');
        const wrap = document.getElementById('projectDetailTableWrap');
        const empty = document.getElementById('projectDetailEmpty');
        document.getElementById('projectDetailTitle').textContent = title;
        body.innerHTML = '';
        loading.classList.remove('d-none'); wrap.classList.add('d-none'); empty.classList.add('d-none'); modal.modal('show');
        const params = new URLSearchParams(window.location.search);
        params.set('type', type); params.set('value', value);
        if (value2) params.set('value2', value2);
        fetch('{{ route('user.dashboard.chartDetail') }}?' + params.toString())
            .then(response => response.ok ? response.json() : Promise.reject(new Error('โหลดข้อมูลไม่สำเร็จ')))
            .then(rows => {
                loading.classList.add('d-none');
                if (!rows.length) { empty.classList.remove('d-none'); return; }
                rows.forEach(row => {
                    const tr = document.createElement('tr');
                    [row.Product_detail, row.company || '-', row.product_group || '-', row.step_name || '-'].forEach(value => {
                        const td = document.createElement('td'); td.textContent = value; tr.appendChild(td);
                    });
                    const amount = document.createElement('td'); amount.className = 'text-right'; amount.textContent = Number(row.product_value || 0).toLocaleString('th-TH'); tr.appendChild(amount);
                    body.appendChild(tr);
                });
                wrap.classList.remove('d-none');
            })
            .catch(error => { loading.classList.add('d-none'); empty.textContent = error.message; empty.classList.remove('d-none'); });
    }

    const performanceChart = document.getElementById('performanceChart');
    if (performanceChart) {
        new Chart(performanceChart, {
            type: 'bar',
            data: { labels: ['Target', 'Forecast', 'Win'], datasets: [{ data: [Number(performance.Target || 0), Number(performance.Forecast || 0), Number(performance.Win || 0)], backgroundColor: [colors.target, colors.forecast, colors.win], borderRadius: 9, maxBarThickness: 72 }] },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false }, tooltip: { callbacks: { label: context => '฿' + Number(context.raw).toLocaleString('th-TH') } } }, scales: { x: { grid: { display: false } }, y: { beginAtZero: true, ticks: { callback: value => money.format(value) }, grid: { color: '#edf0f5' } } }, onClick: (_, items) => { if (!items.length || items[0].index === 0) return; const index = items[0].index; showProjects(index === 1 ? 'user_forecast' : 'user_win', {{ auth()->id() }}, index === 1 ? 'โครงการใน Forecast' : 'โครงการที่ Win'); } }
        });
    }

    const labels = pipeline.map(row => row.month);
    const statusSets = [
        ['Present', 'present_value', '#8b85ff', 1], ['Budget', 'budgeted_value', '#4aa8ff', 2], ['TOR', 'tor_value', '#f4be4f', 3],
        ['Bidding', 'bidding_value', '#f28c52', 4], ['Win', 'win_value', '#16b88a', 5], ['Lost', 'lost_value', '#e5484d', 6]
    ];
    const pipelineChart = document.getElementById('pipelineChart');
    if (pipelineChart) {
        new Chart(pipelineChart, {
            type: 'bar',
            data: { labels, datasets: statusSets.map(set => ({ label: set[0], data: pipeline.map(row => Number(row[set[1]] || 0)), backgroundColor: set[2], borderRadius: 5, orderLevel: set[3] })) },
            options: { responsive: true, maintainAspectRatio: false, interaction: { mode: 'nearest', intersect: true }, plugins: { legend: { position: 'bottom', labels: { usePointStyle: true, boxWidth: 8 } }, tooltip: { callbacks: { label: context => context.dataset.label + ': ฿' + Number(context.raw).toLocaleString('th-TH') } } }, scales: { x: { stacked: true, grid: { display: false } }, y: { stacked: true, ticks: { callback: value => money.format(value) }, grid: { color: '#edf0f5' } } }, onClick: (_, items, chart) => { if (!items.length) return; const item = items[0]; const dataset = chart.data.datasets[item.datasetIndex]; showProjects('step', dataset.orderLevel, dataset.label + ' · ' + labels[item.index], labels[item.index]); } }
        });
    }
})();
</script>
@stop
