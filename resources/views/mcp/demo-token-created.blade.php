@extends('adminlte::page')

@section('title', 'Demo MCP Token พร้อมใช้งาน | PrimeForecast')
@section('content_header')
    <h1>Demo MCP Token พร้อมใช้งาน</h1>
@stop

@section('content')
    <div class="row">
        <div class="col-lg-10">
            <div class="card card-success">
                <div class="card-header"><h3 class="card-title"><i class="fas fa-shield-alt mr-2"></i>คัดลอก Token นี้ทันที</h3></div>
                <div class="card-body">
                    <div class="alert alert-warning"><i class="fas fa-exclamation-triangle mr-1"></i>Token จะแสดงเพียงครั้งเดียว ห้ามส่งต่อหรือบันทึกลง Git</div>
                    <div class="form-group">
                        <label for="mcpDemoToken">Bearer Token</label>
                        <div class="input-group">
                            <input id="mcpDemoToken" type="text" class="form-control" value="{{ $plainTextToken }}" readonly autocomplete="off">
                            <div class="input-group-append"><button type="button" class="btn btn-outline-secondary" id="copyMcpDemoToken"><i class="fas fa-copy mr-1"></i>คัดลอก</button></div>
                        </div>
                        <small class="form-text text-muted">หมดอายุ {{ $expiresAt->format('Y-m-d H:i:s T') }}</small>
                    </div>
                    <p id="copyMcpDemoTokenStatus" class="text-success d-none">คัดลอก Token แล้ว</p>
                    <a href="{{ route('mcp-demo-token.show') }}" class="btn btn-secondary">กลับหน้าจัดการ Token</a>
                </div>
            </div>
        </div>
    </div>
@stop

@section('js')
<script>
document.getElementById('copyMcpDemoToken')?.addEventListener('click', async function () {
    const input = document.getElementById('mcpDemoToken');
    try {
        await navigator.clipboard.writeText(input.value);
    } catch (error) {
        input.select();
        document.execCommand('copy');
    }
    document.getElementById('copyMcpDemoTokenStatus').classList.remove('d-none');
});
</script>
@stop
