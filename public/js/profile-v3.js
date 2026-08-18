(function () {
    'use strict';
    const avatarInput = document.getElementById('avatarInput');
    const avatarButton = document.getElementById('changeAvatarButton');
    const avatarPreview = document.getElementById('avatarInputPreview');
    const avatarDisplay = document.getElementById('profileAvatarDisplay');
    if (avatarButton && avatarInput) avatarButton.addEventListener('click', function () { avatarInput.click(); });
    if (avatarInput) avatarInput.addEventListener('change', function () {
        const file = avatarInput.files && avatarInput.files[0];
        if (!file || !file.type.startsWith('image/')) return;
        const url = URL.createObjectURL(file);
        avatarPreview.src = url; avatarDisplay.src = url;
    });

    const toggle = document.getElementById('twoFactorToggle');
    if (!toggle) return;
    const state = document.getElementById('twoFactorState');
    const panel = document.getElementById('twoFactorPasswordPanel');
    const password = document.getElementById('twoFactorPassword');
    const message = document.getElementById('twoFactorMessage');

    toggle.addEventListener('change', function () {
        const enabled = toggle.checked;
        const currentPassword = password ? password.value : '';
        if (!enabled && !currentPassword) {
            toggle.checked = true; panel.classList.remove('d-none'); password.focus();
            message.textContent = 'กรุณากรอกรหัสผ่านปัจจุบันก่อนปิด 2FA'; message.style.color = 'var(--pf-red)'; return;
        }
        if (!window.confirm('ยืนยันการ' + (enabled ? 'เปิด' : 'ปิด') + 'ใช้งาน 2FA?')) { toggle.checked = !enabled; return; }
        toggle.disabled = true; message.textContent = 'กำลังบันทึก...'; message.style.color = 'var(--pf-muted)';
        fetch(toggle.dataset.endpoint, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': toggle.dataset.csrf, 'Accept': 'application/json' }, body: JSON.stringify({ enabled: enabled, current_password: currentPassword }) })
            .then(function (response) { return response.json().then(function (data) { if (!response.ok) throw new Error(data.message || 'บันทึกไม่สำเร็จ'); return data; }); })
            .then(function (data) {
                state.textContent = data.enabled ? 'เปิดใช้งาน' : 'ปิดใช้งาน'; state.classList.toggle('is-on', data.enabled);
                panel.classList.toggle('d-none', !data.enabled); if (password) password.value = '';
                message.textContent = data.message; message.style.color = 'var(--pf-mint)';
            })
            .catch(function (error) { toggle.checked = !enabled; message.textContent = error.message; message.style.color = 'var(--pf-red)'; })
            .finally(function () { toggle.disabled = false; });
    });
})();
