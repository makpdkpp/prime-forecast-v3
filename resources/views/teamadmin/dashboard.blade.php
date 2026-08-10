@extends('adminlte::page')

@section('title', 'Admin Dashboard | PrimeForecast')

@section('content_header')
    <h1>Dashboard</h1>
@stop

@section('content')
    @php
        $activeYear = $selectedYear ?? (request('year') ?: 'all');
    @endphp

    <!-- Filter Section -->
    <div class="row mb-2">
        <div class="col-md-12">
            <div class="card card-outline card-primary collapsed-card">
                <div class="card-header py-2">
                    <h3 class="card-title"><i class="fas fa-filter"></i> กรองข้อมูล</h3>
                    <div class="card-tools">
                        @if($activeYear !== 'all' || request('quarter'))
                            <span class="badge badge-info mr-2">
                                @if($activeYear !== 'all')
                                    ปี {{ (int) $activeYear + 543 }}
                                @endif
                                @if(request('quarter'))
                                    @if($activeYear !== 'all') / @endif
                                    Q{{ request('quarter') }}
                                @endif
                            </span>
                        @endif
                        <button type="button" class="btn btn-tool" data-card-widget="collapse">
                            <i class="fas fa-plus"></i>
                        </button>
                    </div>
                </div>
                <div class="card-body py-2" style="display: none;">
                    <form method="GET" action="{{ route('teamadmin.dashboard') }}" id="filterForm">
                        <div class="row align-items-end">
                            <div class="col-md-2">
                                <label class="mb-1"><small>ปีงบประมาณ (พ.ศ.):</small></label>
                                <select name="year" class="form-control form-control-sm">
                                    <option value="all" {{ $activeYear === 'all' ? 'selected' : '' }}>ทุกปี</option>
                                    @foreach($availableYears as $y)
                                        <option value="{{ $y }}" {{ (string) $activeYear === (string) $y ? 'selected' : '' }}>{{ $y + 543 }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="mb-1"><small>ไตรมาส:</small></label>
                                <select name="quarter" class="form-control form-control-sm">
                                    <option value="">ทุกไตรมาส</option>
                                    <option value="1" {{ request('quarter') == '1' ? 'selected' : '' }}>Q1</option>
                                    <option value="2" {{ request('quarter') == '2' ? 'selected' : '' }}>Q2</option>
                                    <option value="3" {{ request('quarter') == '3' ? 'selected' : '' }}>Q3</option>
                                    <option value="4" {{ request('quarter') == '4' ? 'selected' : '' }}>Q4</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-primary btn-sm btn-block">
                                    <i class="fas fa-search"></i> กรอง
                                </button>
                            </div>
                            <div class="col-md-2">
                                <a href="{{ route('teamadmin.dashboard') }}" class="btn btn-secondary btn-sm btn-block">
                                    <i class="fas fa-redo"></i> รีเซ็ต
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Summary Boxes -->
    <div class="row mb-3">
        <div class="col-lg-3 col-md-6">
            <div class="summary-box">
                <h4>มูลค่า Forecast ทั้งหมด</h4>
                <p>{{ number_format($estimateValue) }} บาท</p>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="summary-box">
                <h4>มูลค่าที่ WIN ทั้งหมด</h4>
                <p>{{ number_format($winValue) }} บาท</p>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="summary-box">
                <h4>จำนวนโครงการที่ WIN</h4>
                <p>{{ number_format($winCount) }} โครงการ</p>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="summary-box">
                <h4>จำนวนโครงการที่ LOST</h4>
                <p>{{ number_format($lostCount) }} โครงการ</p>
            </div>
        </div>
    </div>

    <!-- Charts Row 1 -->
    <div class="row">
        <div class="col-md-6">
            <div class="card card-success">
                <div class="card-header">
                    <h3 class="card-title">ยอดขายรวม(มูลค่า)</h3>
                    <div class="card-tools">
                        <button type="button" class="btn btn-tool" data-card-widget="maximize"><i class="fas fa-expand"></i></button>
                    </div>
                </div>
                <div class="card-body">
                    <canvas id="winstatusValueChart" height="180"></canvas>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card card-info">
                <div class="card-header">
                    <h3 class="card-title">ยอดขายรายทีม(บาท)</h3>
                    <div class="card-tools">
                        <button type="button" class="btn btn-tool" data-card-widget="maximize"><i class="fas fa-expand"></i></button>
                    </div>
                </div>
                <div class="card-body">
                    <canvas id="teamSumChart" height="180"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Row 2 -->
    <div class="row">
        <div class="col-md-6">
            <div class="card card-success">
                <div class="card-header">
                    <h3 class="card-title">ยอดขายรายคน(บาท)</h3>
                    <div class="card-tools">
                        <button type="button" class="btn btn-tool" data-card-widget="maximize"><i class="fas fa-expand"></i></button>
                    </div>
                </div>
                <div class="card-body">
                    <canvas id="personSumChart" height="180"></canvas>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card card-info">
                <div class="card-header">
                    <h3 class="card-title">สถานะการขายในแต่ละขั้นตอน</h3>
                    <div class="card-tools">
                        <button type="button" class="btn btn-tool" data-card-widget="maximize"><i class="fas fa-expand"></i></button>
                    </div>
                </div>
                <div class="card-body">
                    <canvas id="salestatusChart" height="180"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Row 3 -->
    <div class="row">
        <div class="col-md-6">
            <div class="card card-success">
                <div class="card-header">
                    <h3 class="card-title">ประมาณการมูลค่าในแต่ละขั้นตอนการขาย</h3>
                    <div class="card-tools">
                        <button type="button" class="btn btn-tool" data-card-widget="maximize"><i class="fas fa-expand"></i></button>
                    </div>
                </div>
                <div class="card-body">
                    <canvas id="statusValueChart" height="180"></canvas>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card card-info">
                <div class="card-header">
                    <h3 class="card-title">กราฟเปรียบเทียบ Target/Forecast/Win</h3>
                    <div class="card-tools">
                        <button type="button" class="btn btn-tool" data-card-widget="maximize"><i class="fas fa-expand"></i></button>
                    </div>
                </div>
                <div class="card-body">
                    <canvas id="targetForecastWinChart" height="180"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Row 4 -->
    <div class="row">
        <div class="col-md-6">
            <div class="card card-info">
                <div class="card-header">
                    <h3 class="card-title">TOP 10 ประเภทโซลูชั่น</h3>
                    <div class="card-tools">
                        <button type="button" class="btn btn-tool" data-card-widget="maximize"><i class="fas fa-expand"></i></button>
                    </div>
                </div>
                <div class="card-body">
                    <canvas id="topProductsChart" height="180"></canvas>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card card-primary">
                <div class="card-header">
                    <h3 class="card-title">ยอดขาย Top 10 ของลูกค้า</h3>
                    <div class="card-tools">
                        <button type="button" class="btn btn-tool" data-card-widget="maximize"><i class="fas fa-expand"></i></button>
                    </div>
                </div>
                <div class="card-body">
                    <canvas id="topCustomerChart" height="180"></canvas>
                </div>
            </div>
        </div>
    </div>
    <!-- Modal: Generic Chart Detail -->
    <div class="modal fade" id="chartDetailModal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-xl" role="document">
            <div class="modal-content">
                <div class="modal-header bg-info">
                    <h5 class="modal-title text-white"><i class="fas fa-chart-bar"></i> <span id="chartDetailTitle"></span></h5>
                    <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
                </div>
                <div class="modal-body">
                    <div id="chartDetailLoading" class="text-center py-4" style="display:none;">
                        <i class="fas fa-spinner fa-spin fa-2x"></i>
                        <p class="mt-2">กำลังโหลดข้อมูล...</p>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover table-sm" id="chartDetailTable" style="display:none;">
                            <thead class="thead-dark">
                                <tr>
                                    <th>#</th>
                                    <th>โปรเจค</th>
                                    <th>ลูกค้า</th>
                                    <th>กลุ่มสินค้า</th>
                                    <th>พนักงาน</th>
                                    <th>ทีม</th>
                                    <th>สถานะ</th>
                                    <th class="text-right">มูลค่า (บาท)</th>
                                    <th>วันที่เริ่ม</th>
                                </tr>
                            </thead>
                            <tbody id="chartDetailBody"></tbody>
                            <tfoot>
                                <tr class="font-weight-bold bg-light">
                                    <td colspan="7" class="text-right">รวมทั้งหมด</td>
                                    <td class="text-right" id="chartDetailTotal"></td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    <div id="chartDetailEmpty" class="text-center text-muted py-4" style="display:none;">
                        <i class="fas fa-inbox fa-2x"></i>
                        <p class="mt-2">ไม่พบข้อมูล</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
@stop

@section('js')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
(function() {
    const cumulativeWinData = @json($cumulativeWin);
    const sumByTeamData = @json($sumByTeam);
    const sumByPersonData = @json($sumByPerson);
    const saleStatusData = @json($saleStatus);
    const saleStatusValueData = @json($saleStatusValue);
    const topProductsData = @json($topProducts);
    const targetForecastWinData = @json($targetForecastWin);
    const topCustomersData = @json($topCustomers);

    const chartIds = [
        'winstatusValueChart',
        'teamSumChart',
        'personSumChart',
        'salestatusChart',
        'statusValueChart',
        'targetForecastWinChart',
        'topProductsChart',
        'topCustomerChart'
    ];

    function formatMonthYearBe(rawMonth) {
        if (!rawMonth) return '-';
        const parts = String(rawMonth).split('-');
        if (parts.length < 2) return rawMonth;

        const year = Number(parts[0]);
        const month = Number(parts[1]);
        if (!Number.isFinite(year) || !Number.isFinite(month) || month < 1 || month > 12) {
            return rawMonth;
        }

        return String(month).padStart(2, '0') + '-' + String(year + 543);
    }

    function injectChartSkeletons() {
        chartIds.forEach((id) => {
            const canvas = document.getElementById(id);
            if (!canvas || !canvas.parentElement) return;
            const host = canvas.parentElement;
            host.classList.add('chart-host');
            if (host.querySelector('[data-chart-skeleton]')) return;
            const skeleton = document.createElement('div');
            skeleton.className = 'chart-skeleton';
            skeleton.setAttribute('data-chart-skeleton', id);
            skeleton.innerHTML = '<div class="chart-skeleton-bar"></div><div class="chart-skeleton-bar w-75"></div><div class="chart-skeleton-bar w-50"></div>';
            host.appendChild(skeleton);
        });
    }

    function renderWithSkeleton(id, renderFn) {
        renderFn();
        const skeleton = document.querySelector('[data-chart-skeleton="' + id + '"]');
        if (skeleton) skeleton.classList.add('loaded');
    }

    function escapeHtml(value) {
        return $('<div>').text(value ?? '-').html();
    }

    // Generic Chart Detail Popup
    function showChartDetail(type, value, title, value2) {
        const modal = $('#chartDetailModal');
        const loading = $('#chartDetailLoading');
        const table = $('#chartDetailTable');
        const tbody = $('#chartDetailBody');
        const total = $('#chartDetailTotal');
        const empty = $('#chartDetailEmpty');

        $('#chartDetailTitle').text(title);
        loading.show();
        table.hide();
        empty.hide();
        tbody.empty();
        modal.modal('show');

        const params = new URLSearchParams(window.location.search);
        params.set('type', type);
        params.set('value', value);
        if (value2) params.set('value2', value2);

        fetch('/teamadmin/dashboard/chart-detail?' + params.toString())
            .then(res => res.json())
            .then(projects => {
                loading.hide();
                if (!projects.length) {
                    empty.show();
                    return;
                }
                let sumValue = 0;
                projects.forEach((p, i) => {
                    const val = Number(p.product_value) || 0;
                    sumValue += val;
                    tbody.append(`
                        <tr>
                            <td>${i + 1}</td>
                            <td>${escapeHtml(p.Product_detail)}</td>
                            <td>${escapeHtml(p.company)}</td>
                            <td>${escapeHtml(p.product_group)}</td>
                            <td>${escapeHtml((p.nname || '') + ' ' + (p.surename || ''))}</td>
                            <td>${escapeHtml(p.team)}</td>
                            <td>${escapeHtml(p.step_name)}</td>
                            <td class="text-right">${val.toLocaleString('th-TH', {minimumFractionDigits: 2})}</td>
                            <td>${escapeHtml(p.contact_start_date)}</td>
                        </tr>
                    `);
                });
                total.text(sumValue.toLocaleString('th-TH', {minimumFractionDigits: 2}));
                table.show();
            })
            .catch(() => {
                loading.hide();
                empty.show();
            });
    }

    // Win Status Value Chart (Cumulative Win by Month)
    function renderWinStatusValueChart(data) {
        const ctx = document.getElementById('winstatusValueChart');
        if (!ctx) return;
        
        const rawMonths = data.map(r => r.sale_month);
        const labels = rawMonths.map(formatMonthYearBe);
        const values = data.map(r => Number(r.cumulative_win_value));
        
        new Chart(ctx.getContext('2d'), {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'ยอด Win สะสม',
                    data: values,
                    backgroundColor: 'rgba(40, 167, 69, 0.8)'
                }]
            },
            options: {
                responsive: true,
                onClick: function(evt, elements) {
                    if (elements.length === 0) return;
                    const idx = elements[0].index;
                    const month = rawMonths[idx];
                    const displayMonth = labels[idx];
                    showChartDetail('month', month, 'ยอดขาย Win เดือน ' + displayMonth);
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return value.toLocaleString('th-TH');
                            }
                        }
                    }
                }
            }
        });
    }

    // Team Sum Chart
    function renderTeamSumChart(data) {
        const ctx = document.getElementById('teamSumChart');
        if (!ctx) return;
        
        const labels = data.map(r => r.team);
        const values = data.map(r => Number(r.total_value));
        
        new Chart(ctx.getContext('2d'), {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'ยอดขาย (บาท)',
                    data: values,
                    backgroundColor: 'rgba(54, 162, 235, 0.8)'
                }]
            },
            options: {
                responsive: true,
                onClick: function(evt, elements) {
                    if (elements.length === 0) return;
                    const idx = elements[0].index;
                    const item = data[idx];
                    showChartDetail('team', item.team_id, 'ยอดขายทีม: ' + item.team);
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return value.toLocaleString('th-TH');
                            }
                        }
                    }
                }
            }
        });
    }

    // Person Sum Chart
    function renderPersonSumChart(data) {
        const ctx = document.getElementById('personSumChart');
        if (!ctx) return;
        
        const labels = data.map(r => r.nname + ' ' + (r.surename || ''));
        const values = data.map(r => Number(r.total_value));
        
        new Chart(ctx.getContext('2d'), {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'ยอดขาย (บาท)',
                    data: values,
                    backgroundColor: 'rgba(75, 192, 192, 0.8)'
                }]
            },
            options: {
                responsive: true,
                onClick: function(evt, elements) {
                    if (elements.length === 0) return;
                    const idx = elements[0].index;
                    const item = data[idx];
                    showChartDetail('user', item.user_id, 'ยอดขาย: ' + item.nname + ' ' + (item.surename || ''));
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return value.toLocaleString('th-TH');
                            }
                        }
                    }
                }
            }
        });
    }

    // Sale Status Chart (Grouped Bar by Month)
    function renderSaleStatusChart(data) {
        const ctx = document.getElementById('salestatusChart');
        if (!ctx) return;

        const stepConfig = {
            1: { label: 'นำเสนอ Solution', color: 'rgba(54, 162, 235, 0.8)', border: 'rgba(54, 162, 235, 1)' },
            2: { label: 'ตั้งงบประมาณ', color: 'rgba(255, 206, 86, 0.8)', border: 'rgba(255, 206, 86, 1)' },
            3: { label: 'ร่าง TOR', color: 'rgba(255, 159, 64, 0.8)', border: 'rgba(255, 159, 64, 1)' },
            4: { label: 'Bidding / เสนอราคา', color: 'rgba(153, 102, 255, 0.8)', border: 'rgba(153, 102, 255, 1)' },
            5: { label: 'WIN', color: 'rgba(40, 167, 69, 0.8)', border: 'rgba(40, 167, 69, 1)' },
            6: { label: 'LOST', color: 'rgba(220, 53, 69, 0.8)', border: 'rgba(220, 53, 69, 1)' }
        };

        const months = [...new Set(data.map(r => r.sale_month))].sort();
        const displayMonths = months.map(formatMonthYearBe);

        const datasets = Object.keys(stepConfig).map(orderlv => {
            const cfg = stepConfig[orderlv];
            const values = months.map(m => {
                const row = data.find(r => r.sale_month === m && r.orderlv == orderlv);
                return row ? Number(row.count) : 0;
            });
            return {
                label: cfg.label,
                data: values,
                backgroundColor: cfg.color,
                borderColor: cfg.border,
                borderWidth: 1
            };
        });

        new Chart(ctx.getContext('2d'), {
            type: 'bar',
            data: {
                labels: displayMonths,
                datasets: datasets
            },
            options: {
                responsive: true,
                onClick: function(evt, elements) {
                    if (elements.length === 0) return;
                    const el = elements[0];
                    const month = months[el.index];
                    const displayMonth = displayMonths[el.index];
                    const orderlv = Object.keys(stepConfig)[el.datasetIndex];
                    const stepLabel = stepConfig[orderlv].label;
                    showChartDetail('step', orderlv, 'สถานะ: ' + stepLabel + ' — ' + displayMonth, month);
                },
                scales: {
                    x: { stacked: false },
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1,
                            callback: function(value) {
                                return Number.isInteger(value) ? value : '';
                            }
                        }
                    }
                },
                plugins: {
                    legend: { position: 'top' },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.dataset.label + ': ' + context.raw + ' รายการ';
                            }
                        }
                    }
                }
            }
        });
    }

    // Status Value Chart (Grouped Bar by Month)
    function renderStatusValueChart(data) {
        const ctx = document.getElementById('statusValueChart');
        if (!ctx) return;

        const stepConfig = {
            1: { label: 'นำเสนอ Solution', color: 'rgba(54, 162, 235, 0.8)', border: 'rgba(54, 162, 235, 1)' },
            2: { label: 'ตั้งงบประมาณ', color: 'rgba(255, 206, 86, 0.8)', border: 'rgba(255, 206, 86, 1)' },
            3: { label: 'ร่าง TOR', color: 'rgba(255, 159, 64, 0.8)', border: 'rgba(255, 159, 64, 1)' },
            4: { label: 'Bidding / เสนอราคา', color: 'rgba(153, 102, 255, 0.8)', border: 'rgba(153, 102, 255, 1)' },
            5: { label: 'WIN', color: 'rgba(40, 167, 69, 0.8)', border: 'rgba(40, 167, 69, 1)' },
            6: { label: 'LOST', color: 'rgba(220, 53, 69, 0.8)', border: 'rgba(220, 53, 69, 1)' }
        };

        const months = [...new Set(data.map(r => r.sale_month))].sort();
        const displayMonths = months.map(formatMonthYearBe);

        const datasets = Object.keys(stepConfig).map(orderlv => {
            const cfg = stepConfig[orderlv];
            const values = months.map(m => {
                const row = data.find(r => r.sale_month === m && r.orderlv == orderlv);
                return row ? Number(row.total_value) : 0;
            });
            return {
                label: cfg.label,
                data: values,
                backgroundColor: cfg.color,
                borderColor: cfg.border,
                borderWidth: 1
            };
        });

        new Chart(ctx.getContext('2d'), {
            type: 'bar',
            data: {
                labels: displayMonths,
                datasets: datasets
            },
            options: {
                responsive: true,
                onClick: function(evt, elements) {
                    if (elements.length === 0) return;
                    const el = elements[0];
                    const month = months[el.index];
                    const displayMonth = displayMonths[el.index];
                    const orderlv = Object.keys(stepConfig)[el.datasetIndex];
                    const stepLabel = stepConfig[orderlv].label;
                    showChartDetail('step', orderlv, 'มูลค่า: ' + stepLabel + ' — ' + displayMonth, month);
                },
                scales: {
                    x: { stacked: false },
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return value.toLocaleString('th-TH');
                            }
                        }
                    }
                },
                plugins: {
                    legend: { position: 'top' },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.dataset.label + ': ' + Number(context.raw).toLocaleString('th-TH') + ' บาท';
                            }
                        }
                    }
                }
            }
        });
    }

    // Target/Forecast/Win Chart
    function renderTargetForecastWinChart(data) {
        const ctx = document.getElementById('targetForecastWinChart');
        if (!ctx) return;

        const labels = data.map(r => r.nname + ' ' + (r.surename || ''));
        const targetValues = data.map(r => Number(r.target_value) || 0);
        const forecastValues = data.map(r => Number(r.forecast_value) || 0);
        const winValues = data.map(r => Number(r.win_value) || 0);

        new Chart(ctx.getContext('2d'), {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Target',
                        data: targetValues,
                        backgroundColor: 'rgba(153, 102, 255, 0.8)',
                        borderColor: 'rgba(153, 102, 255, 1)',
                        borderWidth: 1
                    },
                    {
                        label: 'Forecast',
                        data: forecastValues,
                        backgroundColor: 'rgba(255, 206, 86, 0.8)',
                        borderColor: 'rgba(255, 206, 86, 1)',
                        borderWidth: 1
                    },
                    {
                        label: 'Win',
                        data: winValues,
                        backgroundColor: 'rgba(40, 167, 69, 0.8)',
                        borderColor: 'rgba(40, 167, 69, 1)',
                        borderWidth: 1
                    }
                ]
            },
            options: {
                responsive: true,
                onClick: function(evt, elements) {
                    if (elements.length === 0) return;
                    const idx = elements[0].index;
                    const dsIndex = elements[0].datasetIndex;
                    const person = data[idx];
                    const userName = person.nname + ' ' + (person.surename || '');
                    const types = ['user_forecast', 'user_forecast', 'user'];
                    const labels = ['Target', 'Forecast', 'Win'];
                    showChartDetail(types[dsIndex], person.user_id, labels[dsIndex] + ': ' + userName);
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return value.toLocaleString('th-TH');
                            }
                        }
                    }
                },
                plugins: {
                    legend: {
                        position: 'top'
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.dataset.label + ': ' + Number(context.raw).toLocaleString('th-TH') + ' บาท';
                            }
                        }
                    }
                }
            }
        });
    }

    // Top Products Chart
    function renderTopProductsChart(data) {
        const ctx = document.getElementById('topProductsChart');
        if (!ctx) return;
        
        const labels = data.map(r => r.product);
        const values = data.map(r => Number(r.total_value));
        
        new Chart(ctx.getContext('2d'), {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'มูลค่า (บาท)',
                    data: values,
                    backgroundColor: 'rgba(255, 159, 64, 0.8)'
                }]
            },
            options: {
                responsive: true,
                indexAxis: 'y',
                onClick: function(evt, elements) {
                    if (elements.length === 0) return;
                    const idx = elements[0].index;
                    const item = data[idx];
                    showChartDetail('product', item.product_id, 'โซลูชั่น: ' + item.product);
                },
                scales: {
                    x: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return value.toLocaleString('th-TH');
                            }
                        }
                    }
                }
            }
        });
    }

    // Top Customer Chart
    function renderTopCustomerChart(data) {
        const ctx = document.getElementById('topCustomerChart');
        if (!ctx) return;
        
        const labels = data.map(r => r.company);
        const values = data.map(r => Number(r.total_value));
        
        new Chart(ctx.getContext('2d'), {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'มูลค่า (บาท)',
                    data: values,
                    backgroundColor: 'rgba(54, 162, 235, 0.8)'
                }]
            },
            options: {
                responsive: true,
                onClick: function(evt, elements) {
                    if (elements.length === 0) return;
                    const idx = elements[0].index;
                    const item = data[idx];
                    showChartDetail('company', item.company_id, 'ลูกค้า: ' + item.company);
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return value.toLocaleString('th-TH');
                            }
                        }
                    }
                }
            }
        });
    }

    injectChartSkeletons();

    // Progressive initialization: render key charts first, then stagger the rest.
    renderWithSkeleton('winstatusValueChart', () => renderWinStatusValueChart(cumulativeWinData));
    renderWithSkeleton('teamSumChart', () => renderTeamSumChart(sumByTeamData));

    const deferredCharts = [
        () => renderWithSkeleton('personSumChart', () => renderPersonSumChart(sumByPersonData)),
        () => renderWithSkeleton('salestatusChart', () => renderSaleStatusChart(saleStatusData)),
        () => renderWithSkeleton('statusValueChart', () => renderStatusValueChart(saleStatusValueData)),
        () => renderWithSkeleton('targetForecastWinChart', () => renderTargetForecastWinChart(targetForecastWinData)),
        () => renderWithSkeleton('topProductsChart', () => renderTopProductsChart(topProductsData)),
        () => renderWithSkeleton('topCustomerChart', () => renderTopCustomerChart(topCustomersData)),
    ];

    deferredCharts.forEach((renderFn, idx) => {
        setTimeout(renderFn, 120 + (idx * 90));
    });
})();
</script>
@stop

@section('css')
<style>
    .content-wrapper {
        background-color: #b3d6e4;
    }
    .summary-box {
        background: #fff;
        padding: 20px;
        border-radius: 8px;
        text-align: left;
        box-shadow: 0 4px 8px rgba(0,0,0,0.05);
        border-left: 5px solid #007bff;
        margin-bottom: 20px;
    }
    .summary-box h4 {
        font-size: 16px;
        margin-bottom: 10px;
        color: #555;
        font-weight: bold;
    }
    .summary-box p {
        font-size: 26px;
        font-weight: bold;
        margin: 0;
        color: #333;
    }
    .chart-host {
        position: relative;
        min-height: 180px;
    }
    .chart-skeleton {
        position: absolute;
        inset: 0;
        background: #f8fafc;
        border-radius: 8px;
        padding: 16px;
        display: flex;
        flex-direction: column;
        justify-content: center;
        gap: 10px;
        transition: opacity 0.25s ease;
        pointer-events: none;
    }
    .chart-skeleton.loaded {
        opacity: 0;
    }
    .chart-skeleton-bar {
        height: 12px;
        border-radius: 999px;
        background: linear-gradient(90deg, #e2e8f0, #cbd5e1, #e2e8f0);
        background-size: 200% 100%;
        animation: chartPulse 1.1s linear infinite;
    }
    @keyframes chartPulse {
        0% { background-position: 200% 0; }
        100% { background-position: -200% 0; }
    }
</style>
@stop
