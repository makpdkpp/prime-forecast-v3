<div class="card card-outline card-info">
    <div class="card-header">
        <h3 class="card-title">สิทธิ์ MCP</h3>
    </div>
    <div class="card-body">
        <input type="hidden" name="mcp_sales_create_enabled" value="0">
        <div class="custom-control custom-switch">
            <input type="checkbox" class="custom-control-input" id="mcp_sales_create_enabled"
                name="mcp_sales_create_enabled" value="1"
                {{ old('mcp_sales_create_enabled', isset($user) ? $user->mcp_sales_create_enabled : false) ? 'checked' : '' }}>
            <label class="custom-control-label" for="mcp_sales_create_enabled">อนุญาตสร้างแบบร่างงานขายผ่าน MCP</label>
        </div>
        @error('mcp_sales_create_enabled')
            <div class="text-danger">{{ $message }}</div>
        @enderror
        <small class="text-muted d-block mt-2">
            สำหรับ Sales และ Team Admin ที่บัญชี Active เท่านั้น ผู้ใช้ต้องเชื่อมต่อด้วย Token ที่มีสิทธิ์ Create และยืนยันผ่านหน้าเว็บก่อนบันทึกงานขายจริง
        </small>
        <small class="text-muted d-block">สิทธิ์ Read ยังคงเป็นไปตาม Role และทีมเดิม</small>
        @if(!app(\App\Services\McpSalesCreateAccess::class)->enabled())
            <div class="alert alert-warning mt-2 mb-0">
                ขณะนี้ระบบส่วนกลางยังไม่เปิดใช้งาน MCP Create ใน environment นี้ สามารถบันทึกสิทธิ์ไว้ล่วงหน้าได้
            </div>
        @endif
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const role = document.getElementById('role_id');
    const permission = document.getElementById('mcp_sales_create_enabled');
    function updateCreatePermission() {
        permission.disabled = !['2', '3'].includes(role.value);
        if (permission.disabled) permission.checked = false;
    }
    role.addEventListener('change', updateCreatePermission);
    updateCreatePermission();
});
</script>
