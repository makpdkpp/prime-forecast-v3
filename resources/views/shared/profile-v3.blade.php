@php
    $roleName = optional($roles->firstWhere('role_id', $user->role_id))->role ?? 'ผู้ใช้งาน';
    $positionName = optional($positions->firstWhere('position_id', $user->position_id))->position ?? 'ยังไม่ระบุตำแหน่ง';
    $projectCount = (int) ($profileStats->project_count ?? 0);
    $winCount = (int) ($profileStats->win_count ?? 0);
    $winRate = $projectCount > 0 ? ($winCount / $projectCount) * 100 : 0;
    $avatarUrl = $user->avatar_path ? asset($user->avatar_path) : asset('dist/img/user2-160x160.jpg');
@endphp

<div class="pf-v3">
    <main class="pf-shell">
        @if(session('success'))<div class="pf-alert pf-alert-success"><i class="fas fa-check-circle"></i><span>{{ session('success') }}</span></div>@endif
        @if(session('error'))<div class="pf-alert pf-alert-danger"><i class="fas fa-exclamation-circle"></i><span>{{ session('error') }}</span></div>@endif
        @if($errors->any())<div class="pf-alert pf-alert-danger"><i class="fas fa-exclamation-circle"></i><span>{{ $errors->first() }}</span></div>@endif

        <header class="pf-page-head">
            <div><span class="pf-eyebrow">MY ACCOUNT</span><h1>โปรไฟล์และความปลอดภัย</h1><p>จัดการข้อมูลส่วนตัว รูปโปรไฟล์ และการเข้าสู่ระบบ</p></div>
        </header>

        <div class="pf-profile-layout">
            <aside class="pf-card pf-profile-card">
                <div class="pf-profile-avatar"><img src="{{ $avatarUrl }}" alt="รูปโปรไฟล์ของ {{ $user->nname }}" id="profileAvatarDisplay"></div>
                <h2>{{ trim(($user->nname ?? '').' '.($user->surename ?? '')) }}</h2>
                <p>{{ $positionName }} · {{ $roleName }}</p>
                <span>{{ $user->email }}</span>
                <div class="pf-profile-stats">
                    <div><small>โครงการ</small><strong>{{ number_format($projectCount) }}</strong></div>
                    <div><small>Win rate</small><strong>{{ number_format($winRate, 1) }}%</strong></div>
                </div>
            </aside>

            <section class="pf-profile-settings">
                <form method="POST" action="{{ route($routePrefix.'.profile.update') }}" enctype="multipart/form-data" class="pf-card pf-profile-panel">
                    @csrf
                    @method('PUT')
                    <header><div><h2>ข้อมูลส่วนตัว</h2><p>แก้ไขชื่อและรูปโปรไฟล์ของคุณ</p></div><button type="submit" class="pf-btn pf-btn-primary"><i class="fas fa-save"></i> บันทึกข้อมูล</button></header>
                    <div class="pf-profile-edit-grid">
                        <div class="pf-avatar-editor">
                            <img src="{{ $avatarUrl }}" alt="ตัวอย่างรูปโปรไฟล์" id="avatarInputPreview">
                            <button type="button" class="pf-btn" id="changeAvatarButton"><i class="fas fa-camera"></i> เปลี่ยนรูป</button>
                            <input type="file" name="avatar" id="avatarInput" accept="image/jpeg,image/png" hidden>
                            <small>JPG หรือ PNG ขนาดไม่เกิน 2MB</small>
                        </div>
                        <div class="pf-field-grid">
                            <div class="pf-field"><label for="profileName">ชื่อ <b>*</b></label><input id="profileName" name="nname" value="{{ old('nname', $user->nname) }}" required maxlength="255"></div>
                            <div class="pf-field"><label for="profileSurname">นามสกุล <b>*</b></label><input id="profileSurname" name="surname" value="{{ old('surname', $user->surename) }}" required maxlength="255"></div>
                            <div class="pf-field"><label>ตำแหน่ง</label><input value="{{ $positionName }}" disabled></div>
                            <div class="pf-field"><label>สิทธิ์การใช้งาน</label><input value="{{ $roleName }}" disabled></div>
                            <div class="pf-field pf-field-full"><label>อีเมล</label><input value="{{ $user->email }}" disabled><small class="pf-hint"><i class="fas fa-check-circle" style="color:var(--pf-mint)"></i> บัญชีที่ใช้เข้าสู่ระบบ</small></div>
                        </div>
                    </div>
                </form>

                <article class="pf-card pf-profile-panel">
                    <header><div><h2>ความปลอดภัย</h2><p>เพิ่มการป้องกันให้บัญชีของคุณ</p></div><span class="pf-security-state {{ $twoFactorEnabled ? 'is-on' : '' }}" id="twoFactorState">{{ $twoFactorEnabled ? 'เปิดใช้งาน' : 'ปิดใช้งาน' }}</span></header>
                    <div class="pf-security-row">
                        <span class="pf-security-icon"><i class="fas fa-shield-alt"></i></span>
                        <div><b>การยืนยันตัวตนสองขั้นตอน</b><small>รับรหัส OTP ผ่านอีเมลทุกครั้งที่เข้าสู่ระบบ</small><div id="twoFactorMessage" class="pf-hint"></div></div>
                        <label class="pf-toggle"><input type="checkbox" id="twoFactorToggle" data-endpoint="{{ route($routePrefix.'.profile.toggle-2fa') }}" data-csrf="{{ csrf_token() }}" {{ $twoFactorEnabled ? 'checked' : '' }}><i></i></label>
                    </div>
                    <div class="pf-security-password {{ $twoFactorEnabled ? '' : 'd-none' }}" id="twoFactorPasswordPanel">
                        <label for="twoFactorPassword">หากต้องการปิด 2FA กรุณายืนยันรหัสผ่านปัจจุบัน</label>
                        <input type="password" id="twoFactorPassword" class="pf-search" autocomplete="current-password" placeholder="รหัสผ่านปัจจุบัน">
                    </div>
                    <div class="pf-security-row">
                        <span class="pf-security-icon"><i class="fas fa-key"></i></span>
                        <div><b>รหัสผ่าน</b><small>ใช้หน้าลืมรหัสผ่านเพื่อกำหนดรหัสใหม่อย่างปลอดภัย</small></div>
                        <a href="{{ route('password.request') }}" class="pf-btn">เปลี่ยนรหัสผ่าน</a>
                    </div>
                </article>
            </section>
        </div>
    </main>
</div>
