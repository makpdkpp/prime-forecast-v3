@extends('adminlte::page')

@section('title', 'Dashboard (ตาราง) | PrimeForecast')

@section('content_header')
    <div class="pf-exec-head">
        <div>
            <div class="pf-exec-eyebrow">Sales operations</div>
            <h1>Sales Pipeline</h1>
            <p>ค้นหา กรอง ส่งออก และเปิดรายละเอียดโครงการได้จาก Workspace เดียว</p>
        </div>
        <div class="pf-exec-actions">
            <a href="{{ route('admin.dashboard') }}" class="btn btn-outline-primary"><i class="fas fa-chart-pie"></i> ภาพรวมธุรกิจ</a>
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
    
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            {{ session('error') }}
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    @endif

    <!-- Filter Section -->
    <div class="row mb-2">
        <div class="col-md-12">
            <div class="card card-outline card-primary collapsed-card">
                <div class="card-header py-2">
                    <h3 class="card-title"><i class="fas fa-filter"></i> กรองข้อมูล</h3>
                    <div class="card-tools">
                        @if(request('year') || request('quarter') || request('bidding_date_from') || request('bidding_date_to') || request('contract_date_from') || request('contract_date_to'))
                            @if(request('year') || request('quarter'))
                            <span class="badge badge-info mr-2">
                                {{ request('year') ? 'ปี ' . (request('year') + 543) : '' }}
                                {{ request('quarter') ? (request('year') ? ' / ' : '') . 'Q' . request('quarter') : '' }}
                            </span>
                            @endif
                            @if(request('bidding_date_from') || request('bidding_date_to') || request('bidding_user_id'))
                            <span class="badge badge-warning mr-2"><i class="fas fa-gavel"></i> Bidding filter</span>
                            @endif
                            @if(request('contract_date_from') || request('contract_date_to') || request('contract_user_id'))
                            <span class="badge badge-success mr-2"><i class="fas fa-file-signature"></i> สัญญา filter</span>
                            @endif
                        @endif
                        <button type="button" class="btn btn-tool" data-card-widget="collapse">
                            <i class="fas fa-plus"></i>
                        </button>
                    </div>
                </div>
                <div class="card-body py-2" style="display: none;">
                    <form method="GET" action="{{ route('admin.dashboard.table') }}" id="tableFilterForm">
                        <div class="row align-items-end">
                            <div class="col-md-2">
                                <label class="mb-1"><small>ปีงบประมาณ (พ.ศ.):</small></label>
                                <select name="year" class="form-control form-control-sm">
                                    <option value="">ทุกปี</option>
                                    @foreach($availableYears as $y)
                                        <option value="{{ $y }}" {{ request('year') == $y ? 'selected' : '' }}>
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
                            <div class="col-md-3">
                                <label class="mb-1"><small>พนักงาน:</small></label>
                                <select name="user_id" class="form-control form-control-sm">
                                    <option value="">ทุกคน</option>
                                    @foreach($availableUsers as $u)
                                        <option value="{{ $u->user_id }}" {{ (string)request('user_id') === (string)$u->user_id ? 'selected' : '' }}>
                                            {{ trim(($u->nname ?? '') . ' ' . ($u->surename ?? '')) }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        {{-- Filter: วันยื่น Bidding --}}
                        <div class="row align-items-end mt-2">
                            <div class="col-12"><small class="text-muted font-weight-bold"><i class="fas fa-gavel"></i> กรองวันยื่น Bidding</small></div>
                            <div class="col-md-3">
                                <label class="mb-1"><small>ชื่อเซล:</small></label>
                                <select name="bidding_user_id" class="form-control form-control-sm">
                                    <option value="">ทุกคน</option>
                                    @foreach($availableUsers as $u)
                                        <option value="{{ $u->user_id }}" {{ (string)request('bidding_user_id') === (string)$u->user_id ? 'selected' : '' }}>
                                            {{ trim(($u->nname ?? '') . ' ' . ($u->surename ?? '')) }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="mb-1"><small>วันเริ่ม:</small></label>
                                <input type="text" name="bidding_date_from" id="bidding_date_from"
                                    class="form-control form-control-sm flatpickr-filter"
                                    placeholder="dd/mm/yyyy"
                                    data-iso="{{ request('bidding_date_from') ?? '' }}"
                                    autocomplete="off" readonly>
                            </div>
                            <div class="col-md-2">
                                <label class="mb-1"><small>วันสิ้นสุด:</small></label>
                                <input type="text" name="bidding_date_to" id="bidding_date_to"
                                    class="form-control form-control-sm flatpickr-filter"
                                    placeholder="dd/mm/yyyy"
                                    data-iso="{{ request('bidding_date_to') ?? '' }}"
                                    autocomplete="off" readonly>
                            </div>
                        </div>

                        {{-- Filter: วันเซ็นสัญญา --}}
                        <div class="row align-items-end mt-2">
                            <div class="col-12"><small class="text-muted font-weight-bold"><i class="fas fa-file-signature"></i> กรองวันเซ็นสัญญา</small></div>
                            <div class="col-md-3">
                                <label class="mb-1"><small>ชื่อเซล:</small></label>
                                <select name="contract_user_id" class="form-control form-control-sm">
                                    <option value="">ทุกคน</option>
                                    @foreach($availableUsers as $u)
                                        <option value="{{ $u->user_id }}" {{ (string)request('contract_user_id') === (string)$u->user_id ? 'selected' : '' }}>
                                            {{ trim(($u->nname ?? '') . ' ' . ($u->surename ?? '')) }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="mb-1"><small>วันเริ่ม:</small></label>
                                <input type="text" name="contract_date_from" id="contract_date_from"
                                    class="form-control form-control-sm flatpickr-filter"
                                    placeholder="dd/mm/yyyy"
                                    data-iso="{{ request('contract_date_from') ?? '' }}"
                                    autocomplete="off" readonly>
                            </div>
                            <div class="col-md-2">
                                <label class="mb-1"><small>วันสิ้นสุด:</small></label>
                                <input type="text" name="contract_date_to" id="contract_date_to"
                                    class="form-control form-control-sm flatpickr-filter"
                                    placeholder="dd/mm/yyyy"
                                    data-iso="{{ request('contract_date_to') ?? '' }}"
                                    autocomplete="off" readonly>
                            </div>
                        </div>

                        <div class="row mt-2">
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-primary btn-sm btn-block" id="tableFilterBtn">
                                    <span id="tableFilterBtnText"><i class="fas fa-search"></i> กรอง</span>
                                    <span id="tableFilterBtnSpinner" class="spinner-border spinner-border-sm ml-1" style="display:none;"></span>
                                </button>
                            </div>
                            <div class="col-md-2">
                                <a href="{{ route('admin.dashboard.table') }}" class="btn btn-secondary btn-sm btn-block">
                                    <i class="fas fa-redo"></i> รีเซ็ต
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Forecast Data Table (All Users)</h3>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table id="salesTable" class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>ชื่อโครงการ</th>
                            <th>หน่วยงาน/บริษัท</th>
                            <th>มูลค่า (฿)</th>
                            <th>สถานะ</th>
                            <th>โอกาสชนะ</th>
                            <th>ปีงบประมาณ</th>
                            <th>วันที่เริ่ม</th>
                            <th>วันยื่น Bidding</th>
                            <th>วันเซ็นสัญญา</th>
                            <th>กลุ่มสินค้า</th>
                            <th>ชื่อผู้ใช้</th>
                            <th>ทีม</th>
                            <th>หมายเหตุ</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Data will be loaded via DataTables AJAX -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- View Detail Modal -->
    <div class="modal fade" id="viewDetailModal" tabindex="-1" role="dialog" aria-labelledby="viewDetailModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="viewDetailModalLabel"><i class="fas fa-info-circle"></i> รายละเอียดข้อมูลการขาย</h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <h5 class="text-primary" id="modal-project"></h5>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="card card-outline card-info mb-3">
                                <div class="card-header py-2"><strong><i class="fas fa-building"></i> ข้อมูลโครงการ</strong></div>
                                <div class="card-body py-2">
                                    <p class="mb-1"><strong>หน่วยงาน/บริษัท:</strong> <span id="modal-company"></span></p>
                                    <p class="mb-1"><strong>มูลค่า:</strong> <span id="modal-value" class="text-success font-weight-bold"></span> บาท</p>
                                    <p class="mb-1"><strong>กลุ่มสินค้า:</strong> <span id="modal-product"></span></p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card card-outline card-warning mb-3">
                                <div class="card-header py-2"><strong><i class="fas fa-coins"></i> งบประมาณ</strong></div>
                                <div class="card-body py-2">
                                    <p class="mb-1"><strong>แหล่งงบประมาณ:</strong> <span id="modal-source"></span></p>
                                    <p class="mb-1"><strong>ปีงบประมาณ:</strong> <span id="modal-year"></span></p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="card card-outline card-success mb-3">
                                <div class="card-header py-2"><strong><i class="fas fa-calendar-alt"></i> วันที่สำคัญ</strong></div>
                                <div class="card-body py-2">
                                    <p class="mb-1"><strong>วันที่เริ่ม:</strong> <span id="modal-start"></span></p>
                                    <p class="mb-1"><strong>วันยื่น Bidding:</strong> <span id="modal-bidding"></span></p>
                                    <p class="mb-1"><strong>วันเซ็นสัญญา:</strong> <span id="modal-contract"></span></p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card card-outline card-danger mb-3">
                                <div class="card-header py-2"><strong><i class="fas fa-chart-line"></i> สถานะ</strong></div>
                                <div class="card-body py-2">
                                    <p class="mb-1"><strong>สถานะปัจจุบัน:</strong> <span id="modal-status" class="badge badge-info"></span></p>
                                    <p class="mb-1"><strong>โอกาสชนะ:</strong> <span id="modal-priority"></span></p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="card card-outline card-secondary mb-3">
                                <div class="card-header py-2"><strong><i class="fas fa-users"></i> ผู้รับผิดชอบ</strong></div>
                                <div class="card-body py-2">
                                    <p class="mb-1"><strong>ชื่อผู้ใช้:</strong> <span id="modal-user"></span></p>
                                    <p class="mb-1"><strong>ทีม:</strong> <span id="modal-team"></span></p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card card-outline card-primary mb-3">
                                <div class="card-header py-2"><strong><i class="fas fa-user-tie"></i> ข้อมูลลูกค้า</strong></div>
                                <div class="card-body py-2">
                                    <p class="mb-1"><strong>ชื่อผู้ติดต่อ:</strong> <span id="modal-contact-person"></span></p>
                                    <p class="mb-1"><strong>เบอร์โทร:</strong> <span id="modal-contact-phone"></span></p>
                                    <p class="mb-1"><strong>อีเมล:</strong> <span id="modal-contact-email"></span></p>
                                    <p class="mb-1"><strong>อื่นๆ:</strong> <span id="modal-contact-note"></span></p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-12">
                            <div class="card card-outline card-dark">
                                <div class="card-header py-2"><strong><i class="fas fa-sticky-note"></i> หมายเหตุ</strong></div>
                                <div class="card-body py-2">
                                    <p class="mb-0" id="modal-remark"></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">ปิด</button>
                </div>
            </div>
        </div>
    </div>
@stop

@section('js')
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap4.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.5.0/js/responsive.bootstrap4.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.bootstrap4.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.colVis.min.js"></script>

<script>
$(function () {
    const csrfToken = '{{ csrf_token() }}';

    const table = $("#salesTable").DataTable({
        "processing": true,
        "serverSide": true,
        "responsive": false,
        "lengthChange": true,
        "autoWidth": false,
        "language": { "url": "//cdn.datatables.net/plug-ins/1.13.7/i18n/th.json" },
        "dom": 'lBfrtip',
        "ajax": {
            "url": "{{ route('admin.dashboard.table.data') }}",
            "data": function(d) {
                d.year = $('select[name="year"]').val() || '{{ $year }}';
                d.quarter = $('select[name="quarter"]').val() || '{{ $quarter }}';
                d.user_id = $('select[name="user_id"]').val() || '{{ $userId }}';
                d.bidding_user_id = $('select[name="bidding_user_id"]').val() || '{{ $biddingUserId }}';
                d.bidding_date_from = $('input[name="bidding_date_from"]').data('isoValue') || '{{ $biddingDateFrom }}';
                d.bidding_date_to = $('input[name="bidding_date_to"]').data('isoValue') || '{{ $biddingDateTo }}';
                d.contract_user_id = $('select[name="contract_user_id"]').val() || '{{ $contractUserId }}';
                d.contract_date_from = $('input[name="contract_date_from"]').data('isoValue') || '{{ $contractDateFrom }}';
                d.contract_date_to = $('input[name="contract_date_to"]').data('isoValue') || '{{ $contractDateTo }}';
            }
        },
        "columns": [
            { data: 'project', render: $.fn.dataTable.render.text() },
            { data: 'company', render: $.fn.dataTable.render.text() },
            { data: 'value', render: function(v){ return Number(v || 0).toLocaleString('th-TH'); } },
            { data: 'status', render: $.fn.dataTable.render.text() },
            { data: 'priority', render: $.fn.dataTable.render.text() },
            { data: 'year', render: $.fn.dataTable.render.text() },
            { data: 'start', render: function(v){ return formatThaiDate(v); } },
            { data: 'bidding', render: function(v){ return formatThaiDate(v); } },
            { data: 'contract', render: function(v){ return formatThaiDate(v); } },
            { data: 'product', render: $.fn.dataTable.render.text() },
            { data: 'user', render: $.fn.dataTable.render.text() },
            { data: 'team', render: $.fn.dataTable.render.text() },
            { data: 'remark', render: $.fn.dataTable.render.text() },
            { data: 'action', orderable: false, searchable: false, className: 'text-center' }
        ],
        "buttons": [
            {
                extend: 'excelHtml5',
                text: '<i class="fas fa-file-excel"></i> Export to Excel',
                className: 'btn btn-success',
                titleAttr: 'Export to Excel',
                bom: true,
                exportOptions: {
                    columns: ':not(:last-child)'
                }
            },
            {
                extend: 'colvis',
                text: 'เลือกคอลัมน์',
                className: 'btn btn-info'
            }
        ],
        "order": [[6, 'desc']]
    });

    // Row click to show detail modal
    $('#salesTable tbody').on('click', 'tr', function(e) {
        // Don't trigger if clicking on action buttons
        if ($(e.target).closest('td:last-child').length) return;

        const row = table.row(this).data();
        if (!row) return;

        $('#modal-project').text(row.project || '-');
        $('#modal-company').text(row.company || '-');
        $('#modal-value').text(Number(row.value || 0).toLocaleString('th-TH'));
        $('#modal-status').text(row.status || '-');
        $('#modal-priority').text(row.priority || '-');
        $('#modal-year').text(row.year || '-');
        $('#modal-start').text(formatThaiDate(row.start));
        $('#modal-bidding').text(formatThaiDate(row.bidding));
        $('#modal-contract').text(formatThaiDate(row.contract));
        $('#modal-product').text(row.product || '-');
        $('#modal-user').text(row.user || '-');
        $('#modal-team').text(row.team || '-');
        $('#modal-source').text(row.source || '-');
        $('#modal-contact-person').text(row.contact_person || '-');
        $('#modal-contact-phone').text(row.contact_phone || '-');
        $('#modal-contact-email').text(row.contact_email || '-');
        $('#modal-contact-note').text(row.contact_note || '-');
        $('#modal-remark').text(row.remark || '-');
        
        $('#viewDetailModal').modal('show');
    });

    $('#salesTable tbody').on('click', '.js-delete-sale', function(e) {
        e.preventDefault();
        e.stopPropagation();

        const deleteUrl = $(this).data('delete-url');
        if (!deleteUrl) return;

        if (!confirm('คุณแน่ใจหรือไม่ว่าต้องการลบข้อมูลนี้?')) {
            return;
        }

        const form = document.createElement('form');
        form.method = 'POST';
        form.action = deleteUrl;
        form.style.display = 'none';

        const csrfInput = document.createElement('input');
        csrfInput.type = 'hidden';
        csrfInput.name = '_token';
        csrfInput.value = csrfToken;

        const methodInput = document.createElement('input');
        methodInput.type = 'hidden';
        methodInput.name = '_method';
        methodInput.value = 'DELETE';

        form.appendChild(csrfInput);
        form.appendChild(methodInput);
        document.body.appendChild(form);
        form.submit();
    });

    // Helper function to format date to Thai format
    function formatThaiDate(dateStr) {
        if (!dateStr || dateStr === '-') return '-';
        try {
            const date = new Date(dateStr);
            const day = String(date.getDate()).padStart(2, '0');
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const year = date.getFullYear() + 543;
            return `${day}/${month}/${year}`;
        } catch (e) {
            return dateStr;
        }
    }

    // Flatpickr for filter date inputs
    var fpLocale = {
        firstDayOfWeek: 1,
        weekdays: {
            shorthand: ['อา', 'จ', 'อ', 'พ', 'พฤ', 'ศ', 'ส'],
            longhand: ['อาทิตย์', 'จันทร์', 'อังคาร', 'พุธ', 'พฤหัสบดี', 'ศุกร์', 'เสาร์']
        },
        months: {
            shorthand: ['ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'],
            longhand: ['มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน', 'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม']
        }
    };

    function thaiDisplay(dateObj) {
        var day = String(dateObj.getDate()).padStart(2, '0');
        var month = String(dateObj.getMonth() + 1).padStart(2, '0');
        return day + '/' + month + '/' + (dateObj.getFullYear() + 543);
    }

    function isoStr(dateObj) {
        var day = String(dateObj.getDate()).padStart(2, '0');
        var month = String(dateObj.getMonth() + 1).padStart(2, '0');
        return dateObj.getFullYear() + '-' + month + '-' + day;
    }

    document.querySelectorAll('.flatpickr-filter').forEach(function(el) {
        var isoVal = el.getAttribute('data-iso') || '';
        var fp = flatpickr(el, {
            dateFormat: 'Y-m-d',
            locale: fpLocale,
            defaultDate: isoVal || null,
            onChange: function(selectedDates, dateStr, instance) {
                if (selectedDates.length > 0) {
                    instance.input.setAttribute('data-iso', isoStr(selectedDates[0]));
                    instance.input.value = thaiDisplay(selectedDates[0]);
                }
            }
        });
        // Set display value after init if defaultDate was set
        if (isoVal && fp.selectedDates.length > 0) {
            el.value = thaiDisplay(fp.selectedDates[0]);
        }
    });

    // Before submitting, replace display values with ISO dates
    $('#tableFilterForm').on('submit', function () {
        ['bidding_date_from','bidding_date_to','contract_date_from','contract_date_to'].forEach(function(name) {
            var input = document.querySelector('input[name="' + name + '"]');
            if (input) {
                var iso = input.getAttribute('data-iso');
                if (iso) input.value = iso;
            }
        });
        $('#tableFilterBtn').prop('disabled', true);
        $('#tableFilterBtnText').text('กำลังกรอง...');
        $('#tableFilterBtnSpinner').show();
    });
});
</script>
@stop

@section('css')
<link rel="stylesheet" href="{{ asset('css/executive-v3.css') }}">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap4.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap4.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap4.min.css">
<style>
    .content-wrapper {
        background-color: #b3d6e4;
    }
    .table-responsive {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }
</style>
@stop
