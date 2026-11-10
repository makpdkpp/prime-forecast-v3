@extends('adminlte::page')

@section('title', 'จัดการผู้ใช้งาน | PrimeForecast')

@section('content_header')
    <h1>จัดการผู้ใช้งาน</h1>
@stop

@section('content')
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">
            {{ session('success') }}
            <button type="button" class="close" data-dismiss="alert">&times;</button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show">
            {{ session('error') }}
            <button type="button" class="close" data-dismiss="alert">&times;</button>
        </div>
    @endif

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">รายการผู้ใช้งานทั้งหมด</h3>
            <div class="card-tools">
                <a href="{{ route('admin.users.create') }}" class="btn btn-primary btn-sm">
                    <i class="fas fa-plus"></i> เพิ่มผู้ใช้งานใหม่
                </a>
            </div>
        </div>
        <div class="card-body">
            <table id="dataTable" class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th style="width:60px; text-align:center;">ลำดับ</th>
                        <th>ชื่อ-นามสกุล</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>ตำแหน่ง</th>
                        <th>Forecast</th>
                        <th>สถานะ</th>
                        <th>MCP Create</th>
                        <th>จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($users as $user)
                    <tr>
                        <td style="text-align:center;">{{ $loop->iteration }}</td>
                        <td>{{ $user->nname }} {{ $user->surename }}</td>
                        <td>{{ $user->email }}</td>
                        <td>{{ $user->role->role ?? '-' }}</td>
                        <td>{{ $user->position->position ?? '-' }}</td>
                        <td>{{ number_format($user->forecast) }}</td>
                        <td>
                            @if($user->is_active)
                                <span class="badge badge-success">Active</span>
                            @else
                                <span class="badge badge-danger">Inactive</span>
                            @endif
                        </td>
                        <td>
                            @if($user->mcp_sales_create_enabled && in_array((int) $user->role_id, [2, 3], true))
                                <span class="badge badge-success">อนุญาต</span>
                            @else
                                <span class="badge badge-secondary">ปิด</span>
                            @endif
                        </td>
                        <td>
                            <a href="{{ route('admin.users.edit', $user->user_id) }}" class="btn btn-sm btn-info" title="แก้ไข">
                                <i class="fas fa-edit"></i>
                            </a>
                            <form action="{{ route('admin.users.toggle-status', $user->user_id) }}" method="POST" style="display:inline;">
                                @csrf
                                @method('PATCH')
                                @if($user->is_active)
                                    <button type="submit" class="btn btn-sm btn-warning" title="ปิดใช้งาน" onclick="return confirm('ยืนยันการปิดใช้งานผู้ใช้นี้?')">
                                        <i class="fas fa-user-slash"></i>
                                    </button>
                                @else
                                    <button type="submit" class="btn btn-sm btn-success" title="เปิดใช้งาน" onclick="return confirm('ยืนยันการเปิดใช้งานผู้ใช้นี้?')">
                                        <i class="fas fa-user-check"></i>
                                    </button>
                                @endif
                            </form>
                            <form action="{{ route('admin.users.destroy', $user->user_id) }}" method="POST" style="display:inline;" onsubmit="return confirm('ยืนยันการลบ?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-danger" title="ลบ">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@stop

@section('js')
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap4.min.js"></script>
<script>
$(function() {
    $('#dataTable').DataTable({
        "language": { "url": "//cdn.datatables.net/plug-ins/1.13.7/i18n/th.json" }
    });
});
</script>
@stop

@section('css')
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap4.min.css">
<style>
    .content-wrapper { background-color: #b3d6e4; }
</style>
@stop
