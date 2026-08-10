@extends('adminlte::page')

@section('title', 'แก้ไขข้อมูลผู้ใช้งาน | PrimeForecast')

@section('content_header')
    <h1>แก้ไขข้อมูลผู้ใช้งาน</h1>
@stop

@section('content')
    <div class="card">
        <div class="card-body">
            <form action="{{ route('admin.users.update', $user->user_id) }}" method="POST">
                @csrf
                @method('PUT')
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="email">Email <span class="text-danger">*</span></label>
                            <input type="email" name="email" id="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email', $user->email) }}" required>
                            @error('email')
                                <span class="invalid-feedback">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="password">Password (เว้นว่างถ้าไม่ต้องการเปลี่ยน)</label>
                            <input type="password" name="password" id="password" class="form-control @error('password') is-invalid @enderror" minlength="12">
                            @error('password')
                                <span class="invalid-feedback">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="nname">ชื่อ <span class="text-danger">*</span></label>
                            <input type="text" name="nname" id="nname" class="form-control @error('nname') is-invalid @enderror" value="{{ old('nname', $user->nname) }}" required>
                            @error('nname')
                                <span class="invalid-feedback">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="surename">นามสกุล <span class="text-danger">*</span></label>
                            <input type="text" name="surename" id="surename" class="form-control @error('surename') is-invalid @enderror" value="{{ old('surename', $user->surename) }}" required>
                            @error('surename')
                                <span class="invalid-feedback">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="role_id">Role <span class="text-danger">*</span></label>
                            <select name="role_id" id="role_id" class="form-control @error('role_id') is-invalid @enderror" required>
                                <option value="">-- เลือก Role --</option>
                                @foreach($roles as $role)
                                    <option value="{{ $role->role_id }}" {{ old('role_id', $user->role_id) == $role->role_id ? 'selected' : '' }}>
                                        {{ $role->role }}
                                    </option>
                                @endforeach
                            </select>
                            @error('role_id')
                                <span class="invalid-feedback">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="position_id">ตำแหน่ง <span class="text-danger">*</span></label>
                            <select name="position_id" id="position_id" class="form-control @error('position_id') is-invalid @enderror" required>
                                <option value="">-- เลือกตำแหน่ง --</option>
                                @foreach($positions as $position)
                                    <option value="{{ $position->position_id }}" {{ old('position_id', $user->position_id) == $position->position_id ? 'selected' : '' }}>
                                        {{ $position->position }}
                                    </option>
                                @endforeach
                            </select>
                            @error('position_id')
                                <span class="invalid-feedback">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>
                </div>
                
                <!-- Forecast Targets Section -->
                <div class="row">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">Forecast Target ตามปี</h3>
                                <button type="button" class="btn btn-sm btn-success float-right" onclick="addYearRow()">
                                    <i class="fas fa-plus"></i> เพิ่มปี
                                </button>
                            </div>
                            <div class="card-body">
                                <table class="table table-bordered">
                                    <thead>
                                        <tr>
                                            <th width="30%">ปีงบประมาณ (ค.ศ.)</th>
                                            <th width="50%">Target (บาท)</th>
                                            <th width="20%">จัดการ</th>
                                        </tr>
                                    </thead>
                                    <tbody id="targetYearTable">
                                        @forelse($userTargets as $target)
                                        <tr>
                                            <td><input type="text" name="targets[{{ $loop->index }}][year]" value="{{ old('targets.'.$loop->index.'.year', $target->fiscal_year) }}" class="form-control" placeholder="เช่น 2026" data-year-type="ce"></td>
                                            <td><input type="number" name="targets[{{ $loop->index }}][value]" value="{{ old('targets.'.$loop->index.'.value', $target->target_value) }}" class="form-control" placeholder="0.00" step="0.01"></td>
                                            <td><button type="button" class="btn btn-sm btn-danger" onclick="removeRow(this)"><i class="fas fa-trash"></i></button></td>
                                        </tr>
                                        @empty
                                        <tr>
                                            <td><input type="text" name="targets[0][year]" value="{{ old('targets.0.year', date('Y')) }}" class="form-control" placeholder="เช่น 2026" data-year-type="ce"></td>
                                            <td><input type="number" name="targets[0][value]" value="{{ old('targets.0.value', 0) }}" class="form-control" placeholder="0.00" step="0.01"></td>
                                            <td><button type="button" class="btn btn-sm btn-danger" onclick="removeRow(this)"><i class="fas fa-trash"></i></button></td>
                                        </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-12">
                        <div class="form-group">
                            <label>ทีมที่รับผิดชอบ</label>
                            <div class="row">
                                @foreach($teams as $team)
                                    <div class="col-md-3">
                                        <div class="custom-control custom-checkbox">
                                            <input type="checkbox" class="custom-control-input" id="team_{{ $team->team_id }}" name="teams[]" value="{{ $team->team_id }}" {{ in_array($team->team_id, old('teams', $userTeams)) ? 'checked' : '' }}>
                                            <label class="custom-control-label" for="team_{{ $team->team_id }}">{{ $team->team }}</label>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <a href="{{ route('admin.users.index') }}" class="btn btn-secondary">ยกเลิก</a>
                    <button type="submit" class="btn btn-primary">บันทึก</button>
                </div>
            </form>
        </div>
    </div>
@stop

@section('css')
<style>
    .content-wrapper { background-color: #b3d6e4; }
</style>
@stop

@section('js')
<script>
function addYearRow() {
    const table = document.getElementById('targetYearTable');
    const rowCount = table.rows.length;
    const row = table.insertRow();
    row.innerHTML = `
        <td><input type="text" name="targets[${rowCount}][year]" class="form-control" placeholder="เช่น 2026" data-year-type="ce"></td>
        <td><input type="number" name="targets[${rowCount}][value]" class="form-control" placeholder="0.00" step="0.01"></td>
        <td><button type="button" class="btn btn-sm btn-danger" onclick="removeRow(this)"><i class="fas fa-trash"></i></button></td>
    `;
}

function removeRow(btn) {
    const row = btn.closest('tr');
    const table = document.getElementById('targetYearTable');
    if (table.rows.length > 1) {
        row.remove();
    } else {
        alert('ต้องมีอย่างน้อย 1 ปี');
    }
}
</script>
@stop
