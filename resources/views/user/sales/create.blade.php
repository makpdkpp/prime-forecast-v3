@extends('adminlte::page')

@section('title', 'เพิ่มโครงการ | PrimeForecast')

@section('content_header')
@stop

@section('content')
<div class="pf-v3">
    <main class="pf-shell">
        @if($errors->any())
            <div class="pf-alert pf-alert-danger">
                <i class="fas fa-exclamation-circle"></i>
                <div>
                    <span>บันทึกยังไม่สำเร็จ กรุณาตรวจสอบข้อมูลที่ระบุด้านล่าง</span>
                    <ul class="pf-error-list">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif

        <header class="pf-page-head">
            <div>
                <a href="{{ route('user.dashboard.table') }}" class="pf-back"><i class="fas fa-arrow-left"></i> กลับรายการโครงการ</a>
                <h1>เพิ่มโครงการใหม่</h1>
                <p>กรอกข้อมูลหลักให้ครบเพื่อสร้าง Forecast ที่ติดตามต่อได้ทันที</p>
            </div>
            <span class="pf-status"><i class="far fa-file-alt"></i> รายการใหม่</span>
        </header>

        <section class="pf-form-progress" aria-label="ขั้นตอนการเพิ่มโครงการ">
            <div class="pf-progress-step active"><i>1</i><span><b>ข้อมูลโครงการ</b><small>ชื่อ ลูกค้า และมูลค่า</small></span></div>
            <div class="pf-progress-step"><i>2</i><span><b>แผนการขาย</b><small>สถานะและวันสำคัญ</small></span></div>
            <div class="pf-progress-step"><i>3</i><span><b>ข้อมูลติดต่อ</b><small>รายละเอียดเพิ่มเติม</small></span></div>
        </section>

        <form action="{{ route('user.sales.store') }}" method="POST" id="salesForm" data-pf-sales-form autocomplete="off">
            @csrf
            <div class="pf-form-layout">
                <div class="pf-card pf-form-card">
                    @include('user.sales._form-fields')

                    <div class="pf-form-actions">
                        <span class="pf-dirty" data-dirty-indicator><i class="fas fa-circle"></i>&nbsp; มีข้อมูลที่ยังไม่บันทึก</span>
                        <a href="{{ route('user.dashboard.table') }}" class="pf-btn">ยกเลิก</a>
                        <button type="submit" class="pf-btn pf-btn-primary"><i class="fas fa-check"></i> สร้างโครงการ</button>
                    </div>
                </div>

                <aside class="pf-card pf-summary-card" aria-label="สรุปโครงการ">
                    <div class="pf-summary-head"><span class="pf-project-icon">PF</span><div><small class="pf-muted">ตัวอย่างโครงการ</small><b data-summary-title>โครงการใหม่</b></div></div>
                    <div class="pf-summary-value"><small>มูลค่าโครงการ</small><strong data-summary-value>฿0</strong></div>
                    <div class="pf-summary-list">
                        <div class="pf-summary-row"><span>ลูกค้า</span><b data-summary-company>-</b></div>
                        <div class="pf-summary-row"><span>กลุ่มสินค้า</span><b data-summary-product>-</b></div>
                        <div class="pf-summary-row"><span>ปีงบประมาณ</span><b data-summary-year>-</b></div>
                    </div>
                    <div class="pf-completion">
                        <div class="pf-completion-head"><span>ความครบถ้วน</span><b data-completion-value>0%</b></div>
                        <div class="pf-progress-bar"><i data-completion-bar style="width:0"></i></div>
                        <small class="pf-muted">ช่องที่มีเครื่องหมาย * จำเป็นต้องกรอก</small>
                    </div>
                    <div class="pf-alert mt-3 mb-0" style="background:#f7f6ff;border-color:#e5e2ff;color:#5149b7"><i class="fas fa-lightbulb"></i><small>ระบุวันที่ Bidding และวันที่คาดว่าจะปิดการขาย เพื่อให้ระบบแจ้งเตือนได้แม่นยำขึ้น</small></div>
                </aside>
            </div>
        </form>
    </main>

    @include('user.partials.mobile-nav')
</div>

@include('user.sales._company-modal')
@stop

@section('css')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<link rel="stylesheet" href="{{ asset('css/sales-v3.css') }}">
@stop

@section('js')
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="{{ asset('js/sales-form-v3.js') }}"></script>
<script src="{{ asset('js/sales-v3.js') }}"></script>
@stop
