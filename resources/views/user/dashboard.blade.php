@extends('adminlte::page')

@section('title', 'Sales Dashboard (Charts) | PrimeForecast')

@section('content_header')
    <div class="row mb-2">
        <div class="col-sm-6">
            <h1>Sales Dashboard (Charts)</h1>
        </div>
    </div>
@stop

@section('content')
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    @endif
    
    @php
        $activeYear = $selectedYear ?? (request('year') ?: 'all');
    @endphp

    <!-- Filter Section -->
    <div class="row mb-2">
        <div class="col-md-12">
            <div class="card card-outline card-success collapsed-card">
                <div class="card-header py-2">
                    <h3 class="card-title"><i class="fas fa-filter"></i> กรองข้อมูล</h3>
                    <div class="card-tools">
                        @if($activeYear !== 'all' || request('quarter'))
                            <span class="badge badge-success mr-2">
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
                    <form method="GET" action="{{ route('user.dashboard') }}" id="filterForm">
                        <div class="row align-items-end">
                            <div class="col-md-2">
                                <label class="mb-1"><small>ปีงบประมาณ (พ.ศ.):</small></label>
                                <select name="year" class="form-control form-control-sm">
                                    <option value="all" {{ $activeYear === 'all' ? 'selected' : '' }}>ทุกปี</option>
                                    @foreach($availableYears as $y)
                                        <option value="{{ $y }}" {{ (string) $activeYear === (string) $y ? 'selected' : '' }}>
                                            {{ $y + 543 }}
                                        </option>
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
                                <button type="submit" class="btn btn-success btn-sm btn-block">
                                    <i class="fas fa-search"></i> กรอง
                                </button>
                            </div>
                            <div class="col-md-2">
                                <a href="{{ route('user.dashboard') }}" class="btn btn-secondary btn-sm btn-block">
                                    <i class="fas fa-redo"></i> รีเซ็ต
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <div class="col-md-6">
            <div class="card card-success">
                <div class="card-header d-flex align-items-center">
                    <h3 class="card-title">สถานะโครงการในแต่ละขั้นตอน</h3>
                    <button class="btn btn-tool btn-fullscreen ms-auto float-end" style="margin-left:auto;" title="ขยายเต็มจอ" type="button"><i class="fas fa-expand"></i></button>
                </div>
                <div class="card-body"><canvas id="stepChart"></canvas></div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card card-success">
                <div class="card-header d-flex align-items-center">
                    <h3 class="card-title">กราฟเปรียบเทียบ Target/Forecast/Win</h3>
                    <button class="btn btn-tool btn-fullscreen ms-auto float-end" style="margin-left:auto;" title="ขยายเต็มจอ" type="button"><i class="fas fa-expand"></i></button>
                </div>
                <div class="card-body"><canvas id="winForecastChart"></canvas></div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <div class="col-md-6">
            <div class="card card-success">
                <div class="card-header d-flex align-items-center">
                    <h3 class="card-title">กราฟเปรียบเทียบสัดส่วนของกลุ่มสินค้า</h3>
                    <button class="btn btn-tool btn-fullscreen ms-auto float-end" style="margin-left:auto;" title="ขยายเต็มจอ" type="button"><i class="fas fa-expand"></i></button>
                </div>
                <div class="card-body"><canvas id="sumValuePercentChart" style="min-height: 300px; height: 300px; max-height: 300px; max-width: 100%;"></canvas></div>
            </div>
        </div>
    </div>

    <!-- Chart Detail Modal -->
    <div class="modal fade" id="chartDetailModal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-xl" role="document">
            <div class="modal-content">
                <div class="modal-header bg-info">
                    <h5 class="modal-title text-white"><i class="fas fa-chart-bar"></i> <span id="chartDetailTitle">รายละเอียดโครงการ</span></h5>
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
                                    <th>ทีม</th>
                                    <th>สถานะ</th>
                                    <th>วันที่ WIN</th>
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

    <!-- Win Projects Modal -->
    <div class="modal fade" id="winProjectsModal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header bg-success">
                    <h5 class="modal-title text-white"><i class="fas fa-trophy"></i> โปรเจคที่ Win - <span id="winProjectsUserName">ฉัน</span></h5>
                    <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
                </div>
                <div class="modal-body">
                    <div id="winProjectsLoading" class="text-center py-4" style="display:none;">
                        <i class="fas fa-spinner fa-spin fa-2x"></i>
                        <p class="mt-2">กำลังโหลดข้อมูล...</p>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover" id="winProjectsTable" style="display:none;">
                            <thead class="thead-dark">
                                <tr>
                                    <th>#</th>
                                    <th>โปรเจค</th>
                                    <th>ลูกค้า</th>
                                    <th>กลุ่มสินค้า</th>
                                    <th class="text-right">มูลค่า (บาท)</th>
                                    <th>วันที่ Win</th>
                                </tr>
                            </thead>
                            <tbody id="winProjectsBody"></tbody>
                            <tfoot>
                                <tr class="font-weight-bold bg-light">
                                    <td colspan="4" class="text-right">รวมทั้งหมด</td>
                                    <td class="text-right" id="winProjectsTotal"></td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    <div id="winProjectsEmpty" class="text-center text-muted py-4" style="display:none;">
                        <i class="fas fa-inbox fa-2x"></i>
                        <p class="mt-2">ไม่พบข้อมูลโปรเจค</p>
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
    const saleStepData = @json($saleStepData);
    const winForecastData = @json($winForecastData);
    const sumValuePercentData = @json($sumValuePercentData);
    const currentUserName = @json(auth()->user()->nname ?? 'ฉัน');

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

    // Step Chart
    function drawStepChart(rows) {
        const chartNode = document.getElementById('stepChart');
        if (!chartNode || !Array.isArray(rows) || rows.length === 0) {
            if (chartNode) chartNode.parentNode.innerHTML = '<p class="text-center text-muted">ไม่มีข้อมูลแสดงกราฟขั้นตอน</p>';
            return;
        }

        const labels = rows.map(r => formatMonthYearBe(r.month));
        const stepConfig = [
            { orderlv: 1, label: 'Present', key: 'present_value', backgroundColor: 'rgba(128, 81, 255, 1)' },
            { orderlv: 2, label: 'Budget', key: 'budgeted_value', backgroundColor: 'rgba(255, 0, 144, 1)' },
            { orderlv: 3, label: 'TOR', key: 'tor_value', backgroundColor: 'rgba(230, 180, 40, 1)' },
            { orderlv: 4, label: 'Bidding', key: 'bidding_value', backgroundColor: 'rgba(230, 120, 40, 1)' },
            { orderlv: 5, label: 'Win', key: 'win_value', backgroundColor: 'rgba(34, 139, 34, 1)' },
            { orderlv: 6, label: 'Lost', key: 'lost_value', backgroundColor: 'rgba(178, 34, 34, 1)' }
        ];
        const datasets = stepConfig.map(cfg => ({
            label: cfg.label,
            data: rows.map(r => +r[cfg.key]),
            backgroundColor: cfg.backgroundColor,
            orderlv: cfg.orderlv
        }));

        new Chart(chartNode.getContext('2d'), {
            type: 'bar',
            data: { labels, datasets },
            options: {
                responsive: true,
                plugins: {
                    title: { display: true, text: 'สถานะโครงการในแต่ละเดือน' },
                    legend: { position: 'top' }
                },
                scales: {
                    x: { stacked: false, title: { display: true, text: 'เดือน' } },
                    y: { stacked: false, beginAtZero: true, title: { display: true, text: 'มูลค่าโครงการ' } }
                },
                onClick: function(evt, elements) {
                    if (!elements.length) return;
                    const el = elements[0];
                    const monthRaw = saleStepData[el.index]?.month;
                    const dataset = datasets[el.datasetIndex];
                    const stepLabel = dataset.label;
                    const orderlv = dataset.orderlv;
                    showChartDetail('step', orderlv, 'สถานะ: ' + stepLabel + ' — ' + formatMonthYearBe(monthRaw), monthRaw);
                }
            }
        });
    }

    // Win Forecast Chart
    function drawWinForecastChart(data) {
        const chartNode = document.getElementById('winForecastChart');
        if (!chartNode || !data) {
            if (chartNode) chartNode.parentNode.innerHTML = '<p class="text-center text-muted">ไม่มีข้อมูลแสดงกราฟ Forecast</p>';
            return;
        }

        const labels = ['Target', 'Forecast', 'Win'];
        const values = [+data.Target || 0, +data.Forecast || 0, +data.Win || 0];
        const colors = ['rgba(153,102,255,0.7)', 'rgba(54,162,235,0.7)', 'rgba(34, 139, 34, 0.7)'];

        new Chart(chartNode.getContext('2d'), {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'หน่วย: จำนวนเงิน',
                    data: values,
                    backgroundColor: colors
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true, title: { display: true, text: 'จำนวนเงิน' } } },
                onClick: function(evt, elements) {
                    if (!elements.length) return;
                    const index = elements[0].index;
                    const label = labels[index];
                    if (label === 'Win') {
                        showWinProjects(authUserId(), currentUserName);
                    } else if (label === 'Forecast') {
                        showChartDetail('user_forecast', authUserId(), 'รายละเอียดรายการ: Forecast');
                    }
                    // Target: ไม่แสดง modal
                }
            }
        });
    }

    // Sum Value Percent Chart
    function drawSumValuePercentChart(rows) {
        const chartNode = document.getElementById('sumValuePercentChart');
        if (!chartNode || !Array.isArray(rows) || rows.length === 0) {
            if (chartNode) chartNode.parentNode.innerHTML = '<p class="text-center text-muted">ไม่มีข้อมูลแสดงกราฟสัดส่วน</p>';
            return;
        }

        const labels = rows.map(r => r.product);
        const values = rows.map(r => +r.sum_value);
        const total = values.reduce((acc, v) => acc + v, 0);
        
        const palette = ['rgba(54,162,235,0.8)', 'rgba(255,99,132,0.8)', 'rgba(255,206,86,0.8)', 'rgba(75,192,192,0.8)', 'rgba(153,102,255,0.8)', 'rgba(255,159,64,0.8)', 'rgba(0,128,128,0.8)'];
        const backgroundColors = values.map((_, i) => palette[i % palette.length]);
        const borderColors = backgroundColors.map(color => color.replace('0.8', '1'));

        new Chart(chartNode.getContext('2d'), {
            type: 'pie',
            data: {
                labels: labels,
                datasets: [{ 
                    data: values, 
                    backgroundColor: backgroundColors,
                    borderColor: borderColors,
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    title: { 
                        display: true, 
                        text: 'สัดส่วนกลุ่มสินค้า',
                        font: { size: 18, weight: 'bold' },
                        padding: { top: 10, bottom: 20 }
                    },
                    legend: { 
                        position: 'right',
                        labels: { boxWidth: 20, font: { size: 12 } }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.parsed || 0;
                                const percent = total > 0 ? (value / total * 100).toFixed(2) : 0;
                                return `${label}: ${value.toLocaleString('th-TH')} (${percent}%)`;
                            }
                        }
                    }
                },
                onClick: function(evt, elements) {
                    if (!elements.length) return;
                    const idx = elements[0].index;
                    const productId = rows[idx]?.product_id;
                    const productName = rows[idx]?.product || '';
                    if (!productId) return;
                    showChartDetail('product', productId, 'โซลูชั่น: ' + productName);
                }
            }
        });
    }

    function authUserId() {
        return {{ auth()->id() }};
    }

    function escapeHtml(value) {
        return $('<div>').text(value ?? '-').html();
    }

    function showChartDetail(type, value, title, value2) {
        const modal = $('#chartDetailModal');
        const loading = $('#chartDetailLoading');
        const table = $('#chartDetailTable');
        const tbody = $('#chartDetailBody');
        const total = $('#chartDetailTotal');
        const empty = $('#chartDetailEmpty');

        $('#chartDetailTitle').text(title || 'รายละเอียดโครงการ');
        loading.show();
        table.hide();
        empty.hide();
        tbody.empty();
        modal.modal('show');

        const params = new URLSearchParams(window.location.search);
        params.set('type', type);
        params.set('value', value);
        if (value2) params.set('value2', value2);

        fetch('/user/dashboard/chart-detail?' + params.toString())
            .then(res => {
                if (!res.ok) throw new Error('HTTP ' + res.status);
                return res.json();
            })
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
                    tbody.append(`<tr>
                        <td>${i + 1}</td>
                        <td>${escapeHtml(p.Product_detail)}</td>
                        <td>${escapeHtml(p.company)}</td>
                        <td>${escapeHtml(p.product_group)}</td>
                        <td>${escapeHtml(p.team)}</td>
                        <td>${escapeHtml(p.step_name)}</td>
                        <td>${escapeHtml(p.win_date)}</td>
                        <td class="text-right">${val.toLocaleString('th-TH', {minimumFractionDigits: 2})}</td>
                        <td>${escapeHtml(p.contact_start_date)}</td>
                    </tr>`);
                });
                total.text(sumValue.toLocaleString('th-TH', {minimumFractionDigits: 2}));
                table.show();
            })
            .catch(err => {
                loading.hide();
                empty.text('เกิดข้อผิดพลาด: ' + err.message).show();
            });
    }

    function showWinProjects(userId, userName) {
        const modal = $('#winProjectsModal');
        const loading = $('#winProjectsLoading');
        const table = $('#winProjectsTable');
        const tbody = $('#winProjectsBody');
        const total = $('#winProjectsTotal');
        const empty = $('#winProjectsEmpty');

        $('#winProjectsUserName').text(userName || 'ฉัน');
        loading.show();
        table.hide();
        empty.hide();
        tbody.empty();
        modal.modal('show');

        const params = new URLSearchParams(window.location.search);

        fetch('/user/dashboard/win-projects?' + params.toString())
            .then(res => {
                if (!res.ok) throw new Error('HTTP ' + res.status);
                return res.json();
            })
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
                    tbody.append(`<tr>
                        <td>${i + 1}</td>
                        <td>${escapeHtml(p.Product_detail)}</td>
                        <td>${escapeHtml(p.company)}</td>
                        <td>${escapeHtml(p.product_group)}</td>
                        <td class="text-right">${val.toLocaleString('th-TH', {minimumFractionDigits: 2})}</td>
                        <td>${escapeHtml(p.win_date)}</td>
                    </tr>`);
                });
                total.text(sumValue.toLocaleString('th-TH', {minimumFractionDigits: 2}));
                table.show();
            })
            .catch(err => {
                loading.hide();
                empty.text('เกิดข้อผิดพลาด: ' + err.message).show();
            });
    }

    // Initialize charts
    drawStepChart(saleStepData);
    drawWinForecastChart(winForecastData);
    drawSumValuePercentChart(sumValuePercentData);
})();
</script>
@stop

@section('css')
<style>
    .content-wrapper {
        background-color: #b3d6e4;
    }
</style>
@stop
