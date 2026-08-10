@extends('adminlte::page')

@section('title', 'Windate Report | PrimeForecast')

@section('content_header')
    <h1>รายงาน Windate</h1>
@stop

@section('content')
<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">ฟิลเตอร์ข้อมูล</h3>
                <div class="card-tools">
                    <button type="button" class="btn btn-tool" data-card-widget="collapse">
                        <i class="fas fa-minus"></i>
                    </button>
                </div>
            </div>
            <div class="card-body">
                <form id="reportFilterForm">
                    <div class="row">
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="user_id">ชื่อผู้ใช้</label>
                                <select name="user_id" id="user_id" class="form-control">
                                    <option value="">ทุกคน</option>
                                    @foreach($availableUsers as $user)
                                        <option value="{{ $user->user_id }}"
                                            data-active="{{ $user->is_active ? '1' : '0' }}"
                                            {{ $user->is_active ? '' : 'class=text-muted' }}>
                                            {{ trim(($user->nname ?? '') . ' ' . ($user->surename ?? '')) }}{{ $user->is_active ? '' : ' (ไม่ได้ใช้งาน)' }}
                                        </option>
                                    @endforeach
                                </select>
                                <div class="mt-1">
                                    <div class="icheck-primary d-inline">
                                        <input type="checkbox" id="showInactive" name="show_inactive">
                                        <label for="showInactive">แสดงเซลล์ที่ไม่ได้ใช้งาน</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="date_from">วันที่เริ่มต้น</label>
                                <input type="text" name="date_from" id="date_from" class="form-control flatpickr" placeholder="dd/mm/yyyy" autocomplete="off" readonly>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="date_to">วันที่สิ้นสุด</label>
                                <input type="text" name="date_to" id="date_to" class="form-control flatpickr" placeholder="dd/mm/yyyy" autocomplete="off" readonly>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>&nbsp;</label><br>
                                <button type="button" id="filterBtn" class="btn btn-primary">
                                    <i class="fas fa-search"></i> กรองข้อมูล
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">ผลลัพธ์รายงาน</h3>
                <div class="card-tools">
                    <button type="button" id="exportExcel" class="btn btn-success btn-sm">
                        <i class="fas fa-file-excel"></i> Export Excel
                    </button>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table id="reportTable" class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>ชื่อโครงการ</th>
                                <th>หน่วยงาน/บริษัท</th>
                                <th>มูลค่า (฿)</th>
                                <th>Windate</th>
                                <th>ชื่อผู้รับผิดชอบ</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@stop

@section('js')
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap4.min.js"></script>

<script>
$(function () {
    $.ajaxSetup({
        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
    });

    // User dropdown: hide inactive by default
    function filterUserDropdown(showInactive) {
        $('#user_id option').each(function() {
            if ($(this).val() === '') return;
            const isActive = $(this).data('active') == '1';
            $(this).toggle(showInactive || isActive);
        });
        if (!showInactive && $('#user_id option:selected').data('active') == '0') {
            $('#user_id').val('');
        }
    }
    filterUserDropdown(false);
    $('#showInactive').on('change', function() {
        filterUserDropdown($(this).is(':checked'));
    });

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

    document.querySelectorAll('.flatpickr').forEach(function(el) {
        flatpickr(el, {
            dateFormat: 'Y-m-d',
            locale: fpLocale,
            onChange: function(selectedDates, dateStr, instance) {
                if (selectedDates.length > 0) {
                    var d = selectedDates[0];
                    var day = String(d.getDate()).padStart(2, '0');
                    var month = String(d.getMonth() + 1).padStart(2, '0');
                    instance.input.setAttribute('data-iso', d.getFullYear() + '-' + month + '-' + day);
                    instance.input.value = day + '/' + month + '/' + (d.getFullYear() + 543);
                }
            }
        });
    });

    var table = $("#reportTable").DataTable({
        "processing": true,
        "serverSide": false,
        "autoWidth": false,
        "language": {
            "processing": "กำลังดำเนินการ...",
            "search": "ค้นหา:",
            "lengthMenu": "แสดง _MENU_ รายการ",
            "info": "แสดง _START_ ถึง _END_ จาก _TOTAL_ รายการ",
            "infoEmpty": "แสดง 0 ถึง 0 จาก 0 รายการ",
            "infoFiltered": "(กรองจาก _MAX_ รายการทั้งหมด)",
            "zeroRecords": "ไม่พบข้อมูล",
            "emptyTable": "ไม่มีข้อมูลในตาราง",
            "paginate": { "first": "หน้าแรก", "previous": "ก่อนหน้า", "next": "ถัดไป", "last": "หน้าสุดท้าย" }
        },
        "data": [],
        "columns": [
            { data: 'project_name', render: $.fn.dataTable.render.text() },
            { data: 'company_name', render: $.fn.dataTable.render.text() },
            { data: 'value', render: $.fn.dataTable.render.text() },
            { data: 'win_date', render: $.fn.dataTable.render.text() },
            { data: 'user_name', render: $.fn.dataTable.render.text() }
        ]
    });

    function getParams() {
        return {
            user_id: $('#user_id').val(),
            date_from: $('input[name="date_from"]').attr('data-iso') || '',
            date_to: $('input[name="date_to"]').attr('data-iso') || ''
        };
    }

    $('#filterBtn').on('click', function() {
        $.post('{{ route("admin.reports.windate.data") }}', getParams(), function(response) {
            table.clear().rows.add(response.data).draw();
        });
    });

    $('#exportExcel').on('click', function() {
        var params = $.extend({ export_type: 'excel' }, getParams());
        var form = $('<form>', { method: 'POST', action: '{{ route("admin.reports.windate.data") }}' });
        form.append($('<input>', { type: 'hidden', name: '_token', value: '{{ csrf_token() }}' }));
        $.each(params, function(key, val) {
            form.append($('<input>', { type: 'hidden', name: key, value: val }));
        });
        $('body').append(form);
        form.submit();
        form.remove();
    });
});
</script>
@stop

@section('css')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap4.min.css">
@stop
