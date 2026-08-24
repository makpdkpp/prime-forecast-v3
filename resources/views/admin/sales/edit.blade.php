@extends('adminlte::page')

@section('title', 'แก้ไขข้อมูลการขาย | PrimeForecast')

@section('content_header')
    <h1>แก้ไขข้อมูลการขาย</h1>
@stop

@section('content')
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show">
            {{ session('error') }}
            <button type="button" class="close" data-dismiss="alert">&times;</button>
        </div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger">
            <strong>บันทึกไม่สำเร็จ กรุณาตรวจสอบข้อมูล</strong>
            <ul class="mb-0 mt-2">
                @foreach($errors->all() as $message)
                    <li>{{ $message }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card">
        <div class="card-body">
            <form action="{{ request()->routeIs('teamadmin.*') ? route('teamadmin.sales.update', $transaction->transac_id) : route('admin.sales.update', $transaction->transac_id) }}" method="POST">
                @csrf
                @method('PUT')
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="user_id">ผู้ใช้งาน <span class="text-danger">*</span></label>
                            <select name="user_id" id="user_id" class="form-control @error('user_id') is-invalid @enderror" required>
                                <option value="">-- เลือกผู้ใช้งาน --</option>
                                @foreach($users as $user)
                                    <option value="{{ $user->user_id }}" {{ old('user_id', $transaction->user_id) == $user->user_id ? 'selected' : '' }}>
                                        {{ $user->nname }} {{ $user->surename }}
                                    </option>
                                @endforeach
                            </select>
                            @error('user_id')
                                <span class="invalid-feedback">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="Product_detail">ชื่อโครงการ <span class="text-danger">*</span></label>
                            <input type="text" name="Product_detail" id="Product_detail" class="form-control @error('Product_detail') is-invalid @enderror" value="{{ old('Product_detail', $transaction->Product_detail) }}" required>
                            @error('Product_detail')
                                <span class="invalid-feedback">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="company_id">หน่วยงาน / บริษัท <span class="text-danger">*</span></label>
                            <select name="company_id" id="company_id" class="form-control @error('company_id') is-invalid @enderror" required>
                                <option value="">-- เลือกบริษัท --</option>
                                @foreach($companies as $company)
                                    <option value="{{ $company->company_id }}" {{ old('company_id', $transaction->company_id) == $company->company_id ? 'selected' : '' }}>
                                        {{ $company->company }}
                                    </option>
                                @endforeach
                            </select>
                            @error('company_id')
                                <span class="invalid-feedback">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="Product_id">กลุ่มสินค้า <span class="text-danger">*</span></label>
                            <select name="Product_id" id="Product_id" class="form-control @error('Product_id') is-invalid @enderror" required>
                                <option value="">-- เลือกกลุ่มสินค้า --</option>
                                @foreach($products as $product)
                                    <option value="{{ $product->product_id }}" {{ old('Product_id', $transaction->Product_id) == $product->product_id ? 'selected' : '' }}>
                                        {{ $product->product }}
                                    </option>
                                @endforeach
                            </select>
                            @error('Product_id')
                                <span class="invalid-feedback">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="product_value">มูลค่าโครงการ (บาท) <span class="text-danger">*</span></label>
                            <input type="text" name="product_value" id="product_value" class="form-control @error('product_value') is-invalid @enderror" value="{{ old('product_value', number_format($transaction->product_value)) }}" required>
                            @error('product_value')
                                <span class="invalid-feedback">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="team_id">ทีมขาย <span class="text-danger">*</span></label>
                            <select name="team_id" id="team_id" class="form-control @error('team_id') is-invalid @enderror" required>
                                <option value="">-- เลือกทีม --</option>
                                @foreach($teams as $team)
                                    <option value="{{ $team->team_id }}" {{ old('team_id', $transaction->team_id) == $team->team_id ? 'selected' : '' }}>
                                        {{ $team->team }}
                                    </option>
                                @endforeach
                            </select>
                            @error('team_id')
                                <span class="invalid-feedback">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="priority_id">โอกาสการชนะ</label>
                            <select name="priority_id" id="priority_id" class="form-control @error('priority_id') is-invalid @enderror">
                                <option value="">-- เลือกโอกาส --</option>
                                @foreach($priorities as $priority)
                                    <option value="{{ $priority->priority_id }}" {{ old('priority_id', $transaction->priority_id) == $priority->priority_id ? 'selected' : '' }}>
                                        {{ $priority->priority }}
                                    </option>
                                @endforeach
                            </select>
                            @error('priority_id')
                                <span class="invalid-feedback">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="Source_budget_id">ที่มาของงบประมาณ <span class="text-danger">*</span></label>
                            <select name="Source_budget_id" id="Source_budget_id" class="form-control @error('Source_budget_id') is-invalid @enderror" required>
                                <option value="">-- เลือกที่มา --</option>
                                @foreach($sources as $source)
                                    <option value="{{ $source->Source_budget_id }}" {{ old('Source_budget_id', $transaction->Source_budget_id) == $source->Source_budget_id ? 'selected' : '' }}>
                                        {{ $source->Source_budge }}
                                    </option>
                                @endforeach
                            </select>
                            @error('Source_budget_id')
                                <span class="invalid-feedback">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="fiscalyear">ปีงบประมาณ <span class="text-danger">*</span></label>
                            <select name="fiscalyear" id="fiscalyear" class="form-control @error('fiscalyear') is-invalid @enderror" required>
                                @for($year = date('Y') - 2; $year <= date('Y') + 5; $year++)
                                    <option value="{{ $year }}" {{ old('fiscalyear', $transaction->fiscalyear) == $year ? 'selected' : '' }}>{{ $year + 543 }}</option>
                                @endfor
                            </select>
                            @error('fiscalyear')
                                <span class="invalid-feedback">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="contact_start_date">วันที่เริ่มติดต่อ <span class="text-danger">*</span></label>
                            <input type="text" name="contact_start_date" id="contact_start_date" class="form-control flatpickr-thai @error('contact_start_date') is-invalid @enderror" value="{{ old('contact_start_date', $transaction->contact_start_date) }}" required readonly>
                            @error('contact_start_date')
                                <span class="invalid-feedback">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="date_of_closing_of_sale">วันยื่น Bidding</label>
                            <input type="text" name="date_of_closing_of_sale" id="date_of_closing_of_sale" class="form-control flatpickr-thai @error('date_of_closing_of_sale') is-invalid @enderror" value="{{ old('date_of_closing_of_sale', $transaction->date_of_closing_of_sale) }}" readonly>
                            @error('date_of_closing_of_sale')
                                <span class="invalid-feedback">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="sales_can_be_close">วันเซ็นสัญญา</label>
                            <input type="text" name="sales_can_be_close" id="sales_can_be_close" class="form-control flatpickr-thai @error('sales_can_be_close') is-invalid @enderror" value="{{ old('sales_can_be_close', $transaction->sales_can_be_close) }}" readonly>
                            @error('sales_can_be_close')
                                <span class="invalid-feedback">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label>ขั้นตอนการขาย</label>
                    @error('step')
                        <div class="text-danger small mb-2">{{ $message }}</div>
                    @enderror
                    <div class="row">
                        @foreach($steps as $step)
                            @php($stepDateErrorKey = 'step_date.'.$step->level_id)
                            <div class="col-md-3">
                                <div class="custom-control custom-checkbox">
                                    <input type="checkbox" class="custom-control-input step-checkbox" id="step_{{ $step->level_id }}" name="step[{{ $step->level_id }}]" value="1" {{ isset($transactionSteps[$step->level_id]) ? 'checked' : '' }}>
                                    <label class="custom-control-label" for="step_{{ $step->level_id }}">{{ $step->level }}</label>
                                </div>
                                <input type="text" name="step_date[{{ $step->level_id }}]" class="form-control form-control-sm mt-1 step-date flatpickr-step @error($stepDateErrorKey) is-invalid @enderror" id="step_date_{{ $step->level_id }}" value="{{ $transactionSteps[$step->level_id]->date ?? '' }}" {{ isset($transactionSteps[$step->level_id]) ? '' : 'disabled' }} readonly>
                                @error($stepDateErrorKey)
                                    <div class="text-danger small mt-1">{{ $message }}</div>
                                @enderror
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="form-group">
                    <label for="remark">หมายเหตุ</label>
                    <textarea name="remark" id="remark" class="form-control" rows="3">{{ old('remark', $transaction->remark) }}</textarea>
                </div>

                <div class="card mt-3">
                    <div class="card-header bg-light">
                        <h5 class="card-title mb-0"><i class="fas fa-user-tie"></i> ข้อมูลลูกค้า (ไม่บังคับ)</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4 form-group">
                                <label for="contact_person">ชื่อผู้ติดต่อ</label>
                                <input type="text" name="contact_person" id="contact_person" class="form-control" value="{{ old('contact_person', $transaction->contact_person) }}" placeholder="ชื่อ-นามสกุล">
                            </div>
                            <div class="col-md-4 form-group">
                                <label for="contact_phone">เบอร์โทรศัพท์</label>
                                <input type="text" name="contact_phone" id="contact_phone" class="form-control" value="{{ old('contact_phone', $transaction->contact_phone) }}" placeholder="0xx-xxx-xxxx">
                            </div>
                            <div class="col-md-4 form-group">
                                <label for="contact_email">อีเมล</label>
                                <input type="email" name="contact_email" id="contact_email" class="form-control" value="{{ old('contact_email', $transaction->contact_email) }}" placeholder="email@example.com">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-sm-12 form-group">
                                <label for="contact_note">อื่นๆ</label>
                                <textarea name="contact_note" id="contact_note" rows="2" class="form-control" placeholder="รายละเอียดเพิ่มเติมเกี่ยวกับลูกค้า">{{ old('contact_note', $transaction->contact_note) }}</textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <a href="{{ route('admin.dashboard.table') }}" class="btn btn-secondary">ยกเลิก</a>
                    <button type="submit" class="btn btn-primary">บันทึก</button>
                    <a href="{{ route('admin.sales.transfer.history', $transaction->transac_id) }}" class="btn btn-outline-info">
                        <i class="fas fa-history"></i> ประวัติการโอน
                    </a>
                </div>
            </form>
        </div>
    </div>
@stop

@section('js')
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>
$(function() {
    // Format product value with commas
    $('#product_value').on('input', function() {
        let value = $(this).val().replace(/,/g, '');
        if (!isNaN(value) && value !== '') {
            $(this).val(Number(value).toLocaleString());
        }
    });

    // Flatpickr Thai Date Picker
    function initThaiDatePicker(selector, currentValue) {
        const input = document.querySelector(selector);
        if (!input) return;
        
        let defaultDate = null;
        if (currentValue && currentValue !== '') {
            const date = new Date(currentValue);
            if (!isNaN(date.getTime())) {
                defaultDate = date;
            }
        }
        
        flatpickr(selector, {
            dateFormat: 'd/m/Y',
            defaultDate: defaultDate,
            locale: {
                firstDayOfWeek: 1,
                weekdays: {
                    shorthand: ['อา', 'จ', 'อ', 'พ', 'พฤ', 'ศ', 'ส'],
                    longhand: ['อาทิตย์', 'จันทร์', 'อังคาร', 'พุธ', 'พฤหัสบดี', 'ศุกร์', 'เสาร์']
                },
                months: {
                    shorthand: ['ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'],
                    longhand: ['มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน', 'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม']
                }
            },
            onReady: function(selectedDates, dateStr, instance) {
                if (selectedDates.length > 0) {
                    const date = selectedDates[0];
                    const day = String(date.getDate()).padStart(2, '0');
                    const month = String(date.getMonth() + 1).padStart(2, '0');
                    const year = date.getFullYear() + 543;
                    instance.input.value = `${day}/${month}/${year}`;
                }
            },
            onChange: function(selectedDates, dateStr, instance) {
                if (selectedDates.length > 0) {
                    const date = selectedDates[0];
                    const day = String(date.getDate()).padStart(2, '0');
                    const month = String(date.getMonth() + 1).padStart(2, '0');
                    const year = date.getFullYear() + 543;
                    instance.input.value = `${day}/${month}/${year}`;
                }
            }
        });
    }
    
    // Initialize Thai date pickers
    initThaiDatePicker('#contact_start_date', '{{ $transaction->contact_start_date }}');
    initThaiDatePicker('#date_of_closing_of_sale', '{{ $transaction->date_of_closing_of_sale }}');
    initThaiDatePicker('#sales_can_be_close', '{{ $transaction->sales_can_be_close }}');
    
    // Convert Thai date back to YYYY-MM-DD before form submission
    $('form').on('submit', function() {
        $('.flatpickr-thai, .flatpickr-step').each(function() {
            const thaiDate = $(this).val();
            if (thaiDate && thaiDate.includes('/')) {
                const parts = thaiDate.split('/');
                if (parts.length === 3) {
                    const day = parts[0];
                    const month = parts[1];
                    const buddhistYear = parseInt(parts[2]);
                    const christianYear = buddhistYear - 543;
                    $(this).val(`${christianYear}-${month}-${day}`);
                }
            }
        });
    });

    // Initialize Flatpickr for step dates
    document.querySelectorAll('.flatpickr-step').forEach(function(input) {
        const currentValue = input.value;
        let defaultDate = null;
        if (currentValue && currentValue !== '') {
            const date = new Date(currentValue);
            if (!isNaN(date.getTime())) {
                defaultDate = date;
            }
        }
        
        flatpickr(input, {
            dateFormat: 'd/m/Y',
            defaultDate: defaultDate,
            locale: {
                firstDayOfWeek: 1,
                weekdays: {
                    shorthand: ['อา', 'จ', 'อ', 'พ', 'พฤ', 'ศ', 'ส'],
                    longhand: ['อาทิตย์', 'จันทร์', 'อังคาร', 'พุธ', 'พฤหัสบดี', 'ศุกร์', 'เสาร์']
                },
                months: {
                    shorthand: ['ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'],
                    longhand: ['มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน', 'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม']
                }
            },
            onReady: function(selectedDates, dateStr, instance) {
                if (selectedDates.length > 0) {
                    const date = selectedDates[0];
                    const day = String(date.getDate()).padStart(2, '0');
                    const month = String(date.getMonth() + 1).padStart(2, '0');
                    const year = date.getFullYear() + 543;
                    instance.input.value = `${day}/${month}/${year}`;
                }
            },
            onChange: function(selectedDates, dateStr, instance) {
                if (selectedDates.length > 0) {
                    const date = selectedDates[0];
                    const day = String(date.getDate()).padStart(2, '0');
                    const month = String(date.getMonth() + 1).padStart(2, '0');
                    const year = date.getFullYear() + 543;
                    instance.input.value = `${day}/${month}/${year}`;
                }
            }
        });
    });

    // Enable/disable step date based on checkbox
    $('.step-checkbox').on('change', function() {
        const stepId = $(this).attr('id').replace('step_', '');
        const dateInput = $('#step_date_' + stepId);
        if ($(this).is(':checked')) {
            dateInput.prop('disabled', false);
        } else {
            dateInput.prop('disabled', true).val('');
        }
    });
});
</script>
@stop

@section('css')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<style>
    .content-wrapper { background-color: #b3d6e4; }
</style>
@stop
