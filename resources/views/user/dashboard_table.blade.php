@extends('adminlte::page')

@section('title', 'โครงการของฉัน | PrimeForecast')
@section('plugins.Datatables', true)

@section('content_header')
@stop

@section('content')
@php
    $activeYear = $selectedYear ?? 'all';
    $totalValue = (float) ($projectSummary->total_value ?? 0);
@endphp

<div class="pf-v3">
    <main class="pf-shell">
        @if(session('success'))
            <div class="pf-alert pf-alert-success"><i class="fas fa-check-circle"></i><span>{{ session('success') }}</span></div>
        @endif
        @if(session('error'))
            <div class="pf-alert pf-alert-danger"><i class="fas fa-exclamation-circle"></i><span>{{ session('error') }}</span></div>
        @endif

        <header class="pf-page-head">
            <div>
                <span class="pf-eyebrow">MY PIPELINE</span>
                <h1>โครงการของฉัน <span class="pf-status">{{ number_format($projectSummary->total_count ?? 0) }}</span></h1>
                <p>ค้นหา ติดตาม และแก้ไขข้อมูลโครงการทั้งหมดของคุณ</p>
            </div>
            <div class="pf-head-actions">
                <a href="{{ route('user.dashboard') }}" class="pf-btn"><i class="fas fa-chart-line"></i> ดูภาพรวม</a>
                <a href="{{ route('user.sales.create') }}" class="pf-btn pf-btn-primary"><i class="fas fa-plus"></i> เพิ่มโครงการ</a>
            </div>
        </header>

        <section class="pf-card pf-toolbar" aria-label="ค้นหาและกรองโครงการ">
            <div class="pf-search-wrap"><i class="fas fa-search"></i><input type="search" id="projectSearch" class="pf-search" placeholder="ค้นหาชื่อโครงการ ลูกค้า หรือกลุ่มสินค้า..." autocomplete="off"></div>
            <form method="GET" action="{{ route('user.dashboard.table') }}" class="pf-inline-actions" id="projectFilters">
                <select name="year" class="pf-search" aria-label="ปีงบประมาณ" onchange="this.form.submit()">
                    <option value="all" {{ $activeYear === 'all' ? 'selected' : '' }}>ทุกปี</option>
                    @foreach($availableYears as $year)
                        <option value="{{ $year }}" {{ (string) $activeYear === (string) $year ? 'selected' : '' }}>{{ $year + 543 }}</option>
                    @endforeach
                </select>
                <select name="quarter" class="pf-search" aria-label="ไตรมาส" onchange="this.form.submit()">
                    <option value="">ทุกไตรมาส</option>
                    @for($q = 1; $q <= 4; $q++)
                        <option value="{{ $q }}" {{ (string) $quarter === (string) $q ? 'selected' : '' }}>Q{{ $q }}</option>
                    @endfor
                </select>
                <select name="status" class="pf-search" aria-label="สถานะ" onchange="this.form.submit()">
                    <option value="">ทุกสถานะ</option>
                    @foreach($availableSteps as $step)
                        <option value="{{ $step->level_id }}" {{ (string) $status === (string) $step->level_id ? 'selected' : '' }}>{{ $step->level }}</option>
                    @endforeach
                </select>
                @if($activeYear !== 'all' || $quarter || $status)
                    <a href="{{ route('user.dashboard.table') }}" class="pf-btn pf-icon-btn" title="ล้างตัวกรอง"><i class="fas fa-undo"></i></a>
                @endif
            </form>
        </section>

        <section class="pf-card pf-summary-strip" aria-label="สรุปโครงการ">
            <div class="pf-summary-item"><span>ทั้งหมด</span><strong>{{ number_format($projectSummary->total_count ?? 0) }}</strong></div>
            <div class="pf-summary-item"><span>กำลังดำเนินการ</span><strong>{{ number_format($projectSummary->active_count ?? 0) }}</strong></div>
            <div class="pf-summary-item"><span>Win</span><strong style="color:var(--pf-mint)">{{ number_format($projectSummary->win_count ?? 0) }}</strong></div>
            <div class="pf-summary-item"><span>Lost</span><strong style="color:var(--pf-red)">{{ number_format($projectSummary->lost_count ?? 0) }}</strong></div>
            <div class="pf-summary-item"><span>มูลค่ารวม</span><strong>฿{{ $totalValue >= 1000000 ? number_format($totalValue / 1000000, 1).'M' : number_format($totalValue) }}</strong></div>
        </section>

        <section class="pf-card pf-table-card">
            <div class="pf-table-scroll">
                <table id="salesTable" class="pf-table" style="width:100%">
                    <thead><tr>
                        <th>โครงการ</th><th>ลูกค้า</th><th>มูลค่า</th><th>สถานะ</th><th>โอกาสชนะ</th><th>ปี</th><th>เริ่มโครงการ</th><th>Bidding</th><th>คาดว่าจะปิด</th><th>กลุ่มสินค้า</th><th>ทีม</th><th>หมายเหตุ</th><th></th>
                    </tr></thead>
                </table>
            </div>
            <div class="pf-mobile-projects" id="mobileProjects" aria-live="polite"></div>
        </section>
    </main>

    @include('user.partials.mobile-nav')
</div>
@stop

@section('css')
<link rel="stylesheet" href="{{ asset('css/sales-v3.css') }}">
@stop

@section('js')
<script>
(function () {
    const escapeHtml = value => $('<div>').text(value == null ? '-' : value).html();
    const formatDate = value => {
        if (!value) return '-';
        const date = new Date(String(value).replace(' ', 'T'));
        return Number.isNaN(date.getTime()) ? escapeHtml(value) : date.toLocaleDateString('th-TH', { day: 'numeric', month: 'short', year: '2-digit' });
    };
    const status = value => '<span class="pf-status" data-status="' + escapeHtml(value) + '">' + escapeHtml(value || 'Draft') + '</span>';
    const editUrl = id => '{{ url('/user/sales') }}/' + Number(id) + '/edit';

    const table = $('#salesTable').DataTable({
        processing: true,
        serverSide: true,
        pageLength: 25,
        lengthChange: false,
        searchDelay: 350,
        order: [[6, 'desc']],
        ajax: {
            url: '{{ route('user.dashboard.table.data') }}',
            data: function (data) {
                data.year = @json($activeYear === 'all' ? null : $activeYear);
                data.quarter = @json($quarter ?: null);
                data.status = @json($status ?: null);
            }
        },
        columns: [
            { data: 'project', render: function (value, type, row) { if (type !== 'display') return value; return '<div class="pf-project-cell"><b>' + escapeHtml(value) + '</b><small>PF-' + String(row.id).padStart(6, '0') + '</small></div>'; } },
            { data: 'company', render: $.fn.dataTable.render.text() },
            { data: 'value', render: function (value) { return '<b>฿' + Number(value || 0).toLocaleString('th-TH') + '</b>'; } },
            { data: 'status', render: function (value) { return status(value); } },
            { data: 'priority', render: $.fn.dataTable.render.text() },
            { data: 'year', visible: false },
            { data: 'start', visible: false, render: function (value) { return formatDate(value); } },
            { data: 'bidding', render: function (value) { return formatDate(value); } },
            { data: 'contract', visible: false, render: function (value) { return formatDate(value); } },
            { data: 'product', visible: false, render: $.fn.dataTable.render.text() },
            { data: 'team', visible: false, render: $.fn.dataTable.render.text() },
            { data: 'remark', visible: false, render: $.fn.dataTable.render.text() },
            { data: 'id', orderable: false, searchable: false, render: function (id) { return '<a class="pf-btn pf-icon-btn" href="' + editUrl(id) + '" title="แก้ไข"><i class="fas fa-pencil-alt"></i></a>'; } }
        ],
        language: {
            processing: 'กำลังโหลดโครงการ...', emptyTable: 'ยังไม่มีโครงการ', zeroRecords: 'ไม่พบโครงการที่ค้นหา',
            info: 'แสดง _START_–_END_ จาก _TOTAL_ โครงการ', infoEmpty: 'ไม่มีข้อมูล', paginate: { previous: 'ก่อนหน้า', next: 'ถัดไป' }
        },
        drawCallback: function () {
            const rows = this.api().rows({ page: 'current' }).data().toArray();
            const mobile = document.getElementById('mobileProjects');
            mobile.innerHTML = '';
            if (!rows.length) { mobile.innerHTML = '<p class="pf-muted text-center py-4 mb-0">ไม่พบโครงการ</p>'; return; }
            rows.forEach(function (row) {
                const card = document.createElement('article'); card.className = 'pf-mobile-project';
                card.innerHTML = '<div class="pf-mobile-project-head"><div><h3>' + escapeHtml(row.project) + '</h3><p>' + escapeHtml(row.company) + '</p></div>' + status(row.status) + '</div>' +
                    '<div class="pf-mobile-project-meta"><div><span>มูลค่า</span><b>฿' + Number(row.value || 0).toLocaleString('th-TH') + '</b></div><div><span>วัน Bidding</span><b>' + formatDate(row.bidding) + '</b></div><div><span>กลุ่มสินค้า</span><b>' + escapeHtml(row.product) + '</b></div><div><span>โอกาสชนะ</span><b>' + escapeHtml(row.priority) + '</b></div></div>' +
                    '<div class="pf-mobile-project-actions"><a class="pf-btn" href="' + editUrl(row.id) + '"><i class="fas fa-pencil-alt"></i> แก้ไขโครงการ</a></div>';
                mobile.appendChild(card);
            });
        }
    });

    let searchTimer;
    document.getElementById('projectSearch').addEventListener('input', function (event) {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(function () { table.search(event.target.value).draw(); }, 280);
    });
})();
</script>
@stop
