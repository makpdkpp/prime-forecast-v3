@extends('adminlte::page')

@section('title', 'Demo MCP Token | PrimeForecast')
@section('content_header')
    <h1>Demo MCP Token</h1>
@stop

@section('content')
    <div class="row">
        <div class="col-lg-8">
            <div class="card card-primary">
                <div class="card-header"><h3 class="card-title"><i class="fas fa-key mr-2"></i>ออก Token สำหรับ MCP Demo</h3></div>
                <div class="card-body">
                    @if(session('success'))
                        <div class="alert alert-success">{{ session('success') }}</div>
                    @endif
                    @if($errors->any())
                        <div class="alert alert-danger">{{ $errors->first() }}</div>
                    @endif

                    <p>หน้านี้ใช้สำหรับทดสอบ MCP บน Demo เท่านั้น ต้องเข้าสู่ระบบและเปิดใช้งาน 2FA แล้ว</p>
                    <ul>
                        <li>สิทธิ์: <code>mcp:read</code> (อ่านข้อมูลเท่านั้น)</li>
                        <li>อายุ Token: 60 นาที</li>
                        <li>การออกใหม่จะยกเลิก Token Demo เดิมทันที</li>
                    </ul>

                    @if($hasToken)
                        <div class="alert alert-warning"><i class="fas fa-exclamation-triangle mr-1"></i>มี Demo MCP Token ที่ยังไม่หมดอายุอยู่แล้ว</div>
                    @endif

                    <form method="POST" action="{{ route('mcp-demo-token.store') }}" class="d-inline">
                        @csrf
                        <button type="submit" class="btn btn-primary"><i class="fas fa-plus-circle mr-1"></i>ออก Token ใหม่</button>
                    </form>
                    @if($hasToken)
                        <form method="POST" action="{{ route('mcp-demo-token.destroy') }}" class="d-inline ml-2">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-outline-danger"><i class="fas fa-ban mr-1"></i>Revoke Token</button>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </div>
@stop
