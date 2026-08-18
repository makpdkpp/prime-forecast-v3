@extends('adminlte::page')

@section('title', 'แก้ไขโครงการ | PrimeForecast')

@section('content_header')
@stop

@section('content')
@php
    $currentStep = $steps->firstWhere('level_id', $transaction->Step_id);
    $requiredValues = [
        $transaction->Product_detail, $transaction->company_id, $transaction->product_value,
        $transaction->Product_id, $transaction->Source_budget_id, $transaction->fiscalyear,
        $transaction->team_id, $transaction->contact_start_date,
    ];
    $completePercent = (int) round((collect($requiredValues)->filter(fn($value) => $value !== null && $value !== '')->count() / count($requiredValues)) * 100);
@endphp

<div class="pf-v3">
    <main class="pf-shell">
        @if($errors->any())
            <div class="pf-alert pf-alert-danger"><i class="fas fa-exclamation-circle"></i><span>บันทึกยังไม่สำเร็จ กรุณาตรวจสอบข้อมูลที่ระบุด้านล่าง</span></div>
        @endif
        @if(session('error'))
            <div class="pf-alert pf-alert-danger"><i class="fas fa-exclamation-circle"></i><span>{{ session('error') }}</span></div>
        @endif

        <header class="pf-page-head">
            <div>
                <a href="{{ route('user.dashboard.table') }}" class="pf-back"><i class="fas fa-arrow-left"></i> กลับรายการโครงการ</a>
                <h1>แก้ไขโครงการ</h1>
                <p>PF-{{ str_pad($transaction->transac_id, 6, '0', STR_PAD_LEFT) }} · อัปเดตล่าสุด {{ optional($transaction->updated_at)->locale('th')->diffForHumans() ?? '-' }}</p>
            </div>
            <div class="pf-head-actions">
                <span class="pf-status" data-status="{{ $currentStep->level ?? '' }}">{{ $currentStep->level ?? 'Draft' }}</span>
                <button type="submit" form="salesForm" class="pf-btn pf-btn-primary"><i class="fas fa-save"></i> บันทึกการแก้ไข</button>
            </div>
        </header>

        <div class="pf-edit-layout">
            <aside class="pf-card pf-section-nav" aria-label="หมวดข้อมูลโครงการ">
                <a href="#project-information" class="active" data-section-link>ข้อมูลโครงการ <i class="fas fa-check"></i></a>
                <a href="#sales-plan" data-section-link>แผนการขาย <i class="fas fa-check"></i></a>
                <a href="#status-timeline" data-section-link>สถานะและ Timeline <i class="fas fa-stream"></i></a>
                <a href="#contact-information" data-section-link>ข้อมูลผู้ติดต่อ <i class="fas fa-address-card"></i></a>
                <div class="pf-completion d-none d-md-block px-2 pb-2">
                    <div class="pf-completion-head"><span>ความครบถ้วน</span><b data-completion-value>{{ $completePercent }}%</b></div>
                    <div class="pf-progress-bar"><i data-completion-bar style="width:{{ $completePercent }}%"></i></div>
                    <small class="pf-muted">ตรวจข้อมูลให้ครบก่อนบันทึก</small>
                </div>
            </aside>

            <form action="{{ route('user.sales.update', $transaction->transac_id) }}" method="POST" id="salesForm" data-pf-sales-form autocomplete="off" class="pf-card pf-form-card">
                @csrf
                @method('PUT')
                @include('user.sales._form-fields')

                <div class="pf-form-actions">
                    <span class="pf-dirty" data-dirty-indicator><i class="fas fa-circle"></i>&nbsp; การเปลี่ยนแปลงยังไม่ถูกบันทึก</span>
                    <a href="{{ route('user.dashboard.table') }}" class="pf-btn">ยกเลิก</a>
                    <button type="submit" class="pf-btn pf-btn-primary"><i class="fas fa-save"></i> บันทึกการแก้ไข</button>
                </div>
            </form>
        </div>
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
