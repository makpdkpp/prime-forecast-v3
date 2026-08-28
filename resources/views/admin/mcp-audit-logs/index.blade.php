@extends('adminlte::page')

@section('title', 'MCP Audit Logs | PrimeForecast')

@section('content_header')
    <div class="d-flex flex-wrap justify-content-between align-items-center">
        <div><h1 class="mb-1">MCP Demo Test & Audit Logs</h1><p class="text-muted mb-0">ตรวจผลการเรียก MCP และดาวน์โหลด Log ที่ตัดข้อมูลอ่อนไหวแล้ว</p></div>
        @if($storageReady)
            <a class="btn btn-primary mt-2 mt-md-0" href="{{ route('admin.mcp-audit-logs.export', request()->query()) }}"><i class="fas fa-download mr-1"></i> ดาวน์โหลด JSON</a>
        @endif
    </div>
@stop

@section('content')
    @if(! $storageReady)
        <div class="alert alert-danger"><i class="fas fa-exclamation-triangle mr-1"></i>ยังไม่มีตาราง <code>mcp_audit_logs</code> กรุณารัน Laravel migration บน Demo ก่อนทดสอบ</div>
    @else
        <div class="row">
            @foreach(['total' => ['ทั้งหมด', 'info'], 'allowed' => ['อนุญาต', 'success'], 'denied' => ['ปฏิเสธ', 'warning'], 'errors' => ['HTTP Error', 'danger']] as $key => [$label, $color])
                <div class="col-6 col-lg-3"><div class="small-box bg-{{ $color }}"><div class="inner"><h3>{{ number_format($summary[$key]) }}</h3><p>{{ $label }}</p></div><div class="icon"><i class="fas fa-clipboard-check"></i></div></div></div>
            @endforeach
        </div>

        <div class="card card-outline card-primary">
            <div class="card-header"><h3 class="card-title">ตัวกรอง Log</h3></div>
            <form method="GET" action="{{ route('admin.mcp-audit-logs.index') }}">
                <div class="card-body"><div class="row">
                    <div class="col-md-2"><label>ตั้งแต่วันที่</label><input type="date" name="date_from" class="form-control" value="{{ $filters['date_from'] ?? '' }}"></div>
                    <div class="col-md-2"><label>ถึงวันที่</label><input type="date" name="date_to" class="form-control" value="{{ $filters['date_to'] ?? '' }}"></div>
                    <div class="col-md-2"><label>Role</label><select name="role" class="form-control"><option value="">ทั้งหมด</option>@foreach(['sales','team_admin','admin'] as $role)<option value="{{ $role }}" @selected(($filters['role'] ?? '') === $role)>{{ $role }}</option>@endforeach</select></div>
                    <div class="col-md-2"><label>ผลอนุญาต</label><select name="decision" class="form-control"><option value="">ทั้งหมด</option><option value="allowed" @selected(($filters['decision'] ?? '') === 'allowed')>allowed</option><option value="denied" @selected(($filters['decision'] ?? '') === 'denied')>denied</option></select></div>
                    <div class="col-md-2"><label>Tool</label><select name="tool" class="form-control"><option value="">ทั้งหมด</option>@foreach($tools as $tool)<option value="{{ $tool }}" @selected(($filters['tool'] ?? '') === $tool)>{{ $tool }}</option>@endforeach</select></div>
                    <div class="col-md-2"><label>HTTP Status</label><input type="number" min="100" max="599" name="status" class="form-control" value="{{ $filters['status'] ?? '' }}"></div>
                </div></div>
                <div class="card-footer"><button class="btn btn-primary"><i class="fas fa-filter mr-1"></i>กรอง</button><a href="{{ route('admin.mcp-audit-logs.index') }}" class="btn btn-default ml-1">ล้างตัวกรอง</a></div>
            </form>
        </div>

        <div class="card">
            <div class="card-header"><h3 class="card-title">รายการล่าสุด</h3></div>
            <div class="table-responsive"><table class="table table-sm table-hover mb-0">
                <thead><tr><th>เวลา</th><th>Role / User</th><th>Tool</th><th>ผลลัพธ์</th><th>HTTP</th><th>Duration</th><th>Request ID</th><th>Scope / Arguments</th></tr></thead>
                <tbody>
                @forelse($logs as $log)
                    <tr>
                        <td class="text-nowrap">{{ $log->created_at }}</td>
                        <td><b>{{ $log->role ?? '-' }}</b><br><small>#{{ $log->user_id ?? '-' }}</small></td>
                        <td><code>{{ $log->tool_name ?? $log->endpoint }}</code></td>
                        <td><span class="badge badge-{{ $log->decision === 'allowed' ? 'success' : 'warning' }}">{{ $log->decision }}</span></td>
                        <td><span class="badge badge-{{ $log->http_status >= 400 ? 'danger' : 'secondary' }}">{{ $log->http_status }}</span></td>
                        <td>{{ number_format($log->duration_ms) }} ms</td>
                        <td><small class="text-monospace">{{ $log->request_id }}</small></td>
                        <td style="min-width:260px;max-width:420px;word-break:break-word"><small>scope: {{ $log->scope ?: '{}' }}<br>args: {{ $log->arguments ?: '{}' }}</small></td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center text-muted py-4">ไม่พบ Log ตามเงื่อนไข</td></tr>
                @endforelse
                </tbody>
            </table></div>
            <div class="card-footer">{{ $logs->links() }}</div>
        </div>
    @endif

    <div class="card card-outline card-success">
        <div class="card-header"><h3 class="card-title"><i class="fas fa-tasks mr-1"></i>หัวข้อทดสอบ MCP Demo</h3></div>
        <div class="card-body table-responsive p-0"><table class="table table-striped mb-0">
            <thead><tr><th style="width:90px">Test ID</th><th>สิ่งที่ทดสอบ</th><th>ผลที่คาดหวัง</th></tr></thead>
            <tbody>
                <tr><td>MCP-01</td><td>เปิด <code>/healthz</code> และ <code>/readyz</code></td><td>ตอบ <code>ok</code> และ <code>ready</code></td></tr>
                <tr><td>MCP-02</td><td>เชื่อม ChatGPT Developer Mode และ Login ผ่าน OAuth</td><td>Authorize สำเร็จและกลับไป ChatGPT</td></tr>
                <tr><td>MCP-03</td><td>Admin เรียก <code>tools/list</code></td><td>เห็นเครื่องมือครบ 4 รายการ</td></tr>
                <tr><td>MCP-04</td><td>Admin เรียก <code>get_company_forecast</code></td><td>สำเร็จและยอดตรง Dashboard</td></tr>
                <tr><td>MCP-05</td><td>Team Admin เรียก Team/Sales ในทีมตนเอง</td><td>สำเร็จ</td></tr>
                <tr><td>MCP-06</td><td>Team Admin ระบุ Team/Sales นอกทีม</td><td>ถูกปฏิเสธ 403</td></tr>
                <tr><td>MCP-07</td><td>Sales เรียก <code>get_my_forecast</code></td><td>เห็นเฉพาะโครงการของตนเอง</td></tr>
                <tr><td>MCP-08</td><td>Sales พยายามเรียก Team/Company forecast</td><td>ไม่เห็น tool หรือถูกปฏิเสธ 403</td></tr>
                <tr><td>MCP-09</td><td>ทดสอบตัวกรองวันที่และ pagination</td><td>จำนวนและยอดตรง Dashboard ช่วงเดียวกัน</td></tr>
                <tr><td>MCP-10</td><td>Revoke Token แล้วเรียกซ้ำ</td><td>ถูกปฏิเสธทันที</td></tr>
                <tr><td>MCP-11</td><td>เปิด Audit Logs และกรองช่วงเวลาทดสอบ</td><td>มีทั้ง allowed/denied และไม่พบ token/secret</td></tr>
                <tr><td>MCP-12</td><td>ดาวน์โหลด JSON แล้วส่งให้ทีมพัฒนา</td><td>ไฟล์ไม่มี token, service key, IP หรือ cookie</td></tr>
            </tbody>
        </table></div>
    </div>
@stop
