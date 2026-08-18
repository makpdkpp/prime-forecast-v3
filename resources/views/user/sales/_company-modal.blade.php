<div class="modal fade" id="requestCompanyModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header"><div><h5 class="modal-title mb-1">ขอเพิ่มบริษัทใหม่</h5><small class="text-muted">ผู้ดูแลระบบจะตรวจสอบก่อนนำเข้าสู่รายการบริษัท</small></div><button type="button" class="close" data-dismiss="modal" aria-label="ปิด"><span aria-hidden="true">&times;</span></button></div>
            <form id="companyRequestForm" action="{{ route('user.company.request') }}" method="POST">
                @csrf
                <div class="modal-body">
                    <div id="requestStatus"></div>
                    <div class="form-group"><label for="newCompanyName">ชื่อบริษัท <span class="text-danger">*</span></label><input type="text" class="form-control" id="newCompanyName" name="company_name" required maxlength="255"></div>
                    <div class="form-group mb-0"><label for="companyNotes">รายละเอียดเพิ่มเติม</label><textarea class="form-control" id="companyNotes" name="notes" rows="3"></textarea></div>
                </div>
                <div class="modal-footer"><button type="button" class="pf-btn" data-dismiss="modal">ยกเลิก</button><button type="submit" class="pf-btn pf-btn-primary"><i class="fas fa-paper-plane"></i> ส่งคำขอ</button></div>
            </form>
        </div>
    </div>
</div>
