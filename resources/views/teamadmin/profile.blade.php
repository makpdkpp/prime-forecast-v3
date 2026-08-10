@extends('adminlte::page')

@section('title', 'โปรไฟล์ | PrimeForecast')

@section('content_header')
    <h1>โปรไฟล์ผู้ใช้</h1>
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

    <div class="row">
        <div class="col-md-8 offset-md-2">
            <div class="card">
                <div class="card-header text-center">
                    <h3 class="card-title">👋 Hello!</h3>
                </div>
                <div class="card-body">
                    <!-- Avatar -->
                    <div class="text-center mb-4">
                        <div class="avatar-wrapper" style="position: relative; width: 160px; height: 160px; margin: 0 auto;">
                            <img id="avatarPreview" src="{{ Auth::user()->avatar_path ? asset(Auth::user()->avatar_path) : asset('dist/img/user2-160x160.jpg') }}" alt="Avatar" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%; border: 2px solid #ccc;">
                        </div>
                    </div>

                    <!-- Details Grid -->
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <p class="font-weight-bold mb-1">Name</p>
                            <p>{{ Auth::user()->nname ?? 'N/A' }}</p>
                        </div>
                        <div class="col-md-6 mb-3">
                            <p class="font-weight-bold mb-1">Surname</p>
                            <p>{{ Auth::user()->surename ?? 'N/A' }}</p>
                        </div>
                        <div class="col-md-6 mb-3">
                            <p class="font-weight-bold mb-1">Role</p>
                            <p>{{ $roles->firstWhere('role_id', Auth::user()->role_id)->role ?? 'Unknown' }}</p>
                        </div>
                        <div class="col-md-6 mb-3">
                            <p class="font-weight-bold mb-1">Position</p>
                            <p>{{ $positions->firstWhere('position_id', Auth::user()->position_id)->position ?? 'Unknown' }}</p>
                        </div>
                        <div class="col-12 mb-3">
                            <p class="font-weight-bold mb-1">Email</p>
                            <p>{{ Auth::user()->email }}</p>
                        </div>
                    </div>

                    <!-- Edit Button -->
                    <div class="text-right">
                        <button class="btn btn-primary" data-toggle="modal" data-target="#editModal">
                            <i class="fa fa-pencil"></i> Edit
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Modal -->
    <div class="modal fade" id="editModal" tabindex="-1" role="dialog" aria-labelledby="editModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <form method="POST" action="{{ route('teamadmin.profile.update') }}" enctype="multipart/form-data">
                    @csrf
                    @method('PUT')
                    <div class="modal-header">
                        <h5 class="modal-title" id="editModalLabel"><i class="fa fa-pencil-alt"></i> แก้ไขโปรไฟล์</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="user_id" value="{{ Auth::id() }}">
                        <div class="form-group text-center">
                            <div class="avatar-wrapper mb-3" style="position: relative; width: 100px; height: 100px; margin: 0 auto;">
                                <img id="avatarInputPreview" src="{{ Auth::user()->avatar_path ? asset(Auth::user()->avatar_path) : asset('dist/img/user2-160x160.jpg') }}" class="rounded-circle" style="width: 100px; height: 100px; object-fit: cover;">
                                <button type="button" class="btn btn-sm btn-danger" style="position: absolute; bottom: 0; right: 0; padding: 4px;" id="changeAvatarBtnModal">
                                    <i class="fa fa-camera"></i>
                                </button>
                                <input type="file" name="avatar" id="avatarInputModal" accept="image/*" style="display: none;">
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="nname">ชื่อ (Name)</label>
                            <input type="text" class="form-control" name="nname" id="nname" value="{{ Auth::user()->nname }}" required>
                        </div>
                        <div class="form-group">
                            <label for="surname">นามสกุล (Surname)</label>
                            <input type="text" class="form-control" name="surname" id="surname" value="{{ Auth::user()->surename }}" required>
                        </div>
                        <div class="form-group">
                            <label for="emailField">E-mail</label>
                            <input type="email" class="form-control" id="emailField" value="{{ Auth::user()->email }}" disabled>
                        </div>
                        
                        <hr>
                        
                        <div class="form-group">
                            <label>การยืนยันตัวตนแบบ 2 ขั้นตอน (2FA)</label>
                            <div class="custom-control custom-switch">
                                <input type="checkbox" class="custom-control-input" id="twoFactorSwitch" 
                                       {{ $twoFactorEnabled ? 'checked' : '' }}
                                       onchange="toggleTwoFactor(this)">
                                <label class="custom-control-label" for="twoFactorSwitch">
                                    <span id="twoFactorStatus">
                                        {{ $twoFactorEnabled ? 'เปิดใช้งาน' : 'ปิดใช้งาน' }}
                                    </span>
                                </label>
                            </div>
                            <small class="form-text text-muted">
                                <i class="fas fa-info-circle"></i> 
                                เมื่อเปิดใช้งาน คุณจะต้องกรอกรหัส OTP ที่ส่งไปยัง email ทุกครั้งที่ login
                            </small>
                            <div id="twoFactorPasswordGroup" class="form-group mt-3 {{ $twoFactorEnabled ? '' : 'd-none' }}">
                                <label for="twoFactorCurrentPassword">ยืนยันรหัสผ่านปัจจุบันก่อนปิด 2FA</label>
                                <input type="password" id="twoFactorCurrentPassword" class="form-control" autocomplete="current-password">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">ยกเลิก</button>
                        <button type="submit" class="btn btn-success">บันทึก</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@stop

@section('js')
<script>
// เปิด file input เมื่อคลิกปุ่มกล้องใน modal
document.getElementById('changeAvatarBtnModal').addEventListener('click', function(){
    document.getElementById('avatarInputModal').click();
});

// แสดงพรีวิวรูปที่เลือก
document.getElementById('avatarInputModal').addEventListener('change', function(){
    const file = this.files[0];
    if(file && file.type.startsWith('image/')){
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('avatarInputPreview').src = e.target.result;
        };
        reader.readAsDataURL(file);
    }
});

// Toggle 2FA function
function toggleTwoFactor(checkbox) {
    const isEnabled = checkbox.checked;
    const statusText = isEnabled ? 'เปิดใช้งาน' : 'ปิดใช้งาน';
    const actionText = isEnabled ? 'เปิด' : 'ปิด';
    
    const passwordInput = document.getElementById('twoFactorCurrentPassword');
    const currentPassword = passwordInput ? passwordInput.value : '';

    if (!isEnabled && !currentPassword) {
        checkbox.checked = true;
        alert('กรุณากรอกรหัสผ่านปัจจุบันก่อนปิด 2FA');
        passwordInput.focus();
        return;
    }

    if (confirm('คุณต้องการ' + actionText + 'การใช้งาน 2FA หรือไม่?')) {
        // Send AJAX request
        fetch('{{ route("teamadmin.profile.toggle-2fa") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({ enabled: isEnabled, current_password: currentPassword })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('twoFactorStatus').textContent = statusText;
                document.getElementById('twoFactorPasswordGroup').classList.toggle('d-none', !data.enabled);
                passwordInput.value = '';
                alert(data.message);
            } else {
                checkbox.checked = !isEnabled;
                alert(data.message || 'เกิดข้อผิดพลาด กรุณาลองใหม่อีกครั้ง');
            }
        })
        .catch(error => {
            checkbox.checked = !isEnabled;
            alert('เกิดข้อผิดพลาด กรุณาลองใหม่อีกครั้ง');
        });
    } else {
        checkbox.checked = !isEnabled;
    }
}
</script>
@stop

@section('css')
<style>
    .content-wrapper {
        background-color: #b3d6e4;
    }
</style>
@stop
