@php
    $record = $transaction ?? null;
    $selectedSteps = $transactionSteps ?? collect();
    $fieldValue = fn ($name, $fallback = '') => old($name, $record->{$name} ?? $fallback);
@endphp

<section class="pf-section" id="project-information">
    <div class="pf-section-title"><i class="fas fa-briefcase"></i><div><h2>ข้อมูลโครงการ</h2><p>ชื่อ ลูกค้า มูลค่า และประเภทของโครงการ</p></div></div>
    <div class="pf-field-grid">
        <div class="pf-field pf-field-full">
            <label for="Product_detail">ชื่อโครงการ <b>*</b></label>
            <input type="text" id="Product_detail" name="Product_detail" value="{{ $fieldValue('Product_detail') }}" maxlength="255" required class="@error('Product_detail') is-invalid @enderror" placeholder="เช่น โครงการพัฒนาระบบ Data Center">
            @error('Product_detail')<div class="pf-error">{{ $message }}</div>@enderror
        </div>
        <div class="pf-field">
            <label for="company_id">หน่วยงาน / บริษัท <b>*</b></label>
            <select id="company_id" name="company_id" required class="@error('company_id') is-invalid @enderror">
                <option value="">เลือกหน่วยงานหรือบริษัท</option>
                @foreach($companies as $company)
                    <option value="{{ $company->company_id }}" {{ (string) $fieldValue('company_id') === (string) $company->company_id ? 'selected' : '' }}>{{ $company->company }}</option>
                @endforeach
            </select>
            @error('company_id')<div class="pf-error">{{ $message }}</div>@enderror
            <button type="button" class="pf-btn pf-btn-link mt-1" data-toggle="modal" data-target="#requestCompanyModal"><i class="fas fa-plus"></i> ขอเพิ่มบริษัทใหม่</button>
        </div>
        <div class="pf-field">
            <label for="product_value">มูลค่าโครงการ <b>*</b></label>
            <div class="pf-money"><input type="text" inputmode="decimal" id="product_value" name="product_value" data-pf-money value="{{ $fieldValue('product_value', $record ? number_format((float) $record->product_value) : '') }}" required class="@error('product_value') is-invalid @enderror" placeholder="0"><span>บาท</span></div>
            @error('product_value')<div class="pf-error">{{ $message }}</div>@enderror
            <small class="pf-hint">กรอกเฉพาะตัวเลข ระบบจะจัดรูปแบบจำนวนเงินให้</small>
        </div>
        <div class="pf-field">
            <label for="Product_id">กลุ่มสินค้า <b>*</b></label>
            <select id="Product_id" name="Product_id" required class="@error('Product_id') is-invalid @enderror">
                <option value="">เลือกกลุ่มสินค้า</option>
                @foreach($products as $product)
                    <option value="{{ $product->product_id }}" {{ (string) $fieldValue('Product_id') === (string) $product->product_id ? 'selected' : '' }}>{{ $product->product }}</option>
                @endforeach
            </select>
            @error('Product_id')<div class="pf-error">{{ $message }}</div>@enderror
        </div>
        <div class="pf-field">
            <label for="Source_budget_id">แหล่งงบประมาณ <b>*</b></label>
            <select id="Source_budget_id" name="Source_budget_id" required class="@error('Source_budget_id') is-invalid @enderror">
                <option value="">เลือกแหล่งงบประมาณ</option>
                @foreach($sources as $source)
                    <option value="{{ $source->Source_budget_id }}" {{ (string) $fieldValue('Source_budget_id') === (string) $source->Source_budget_id ? 'selected' : '' }}>{{ $source->Source_budge }}</option>
                @endforeach
            </select>
            @error('Source_budget_id')<div class="pf-error">{{ $message }}</div>@enderror
        </div>
        <div class="pf-field">
            <label for="fiscalyear">ปีงบประมาณ <b>*</b></label>
            <select id="fiscalyear" name="fiscalyear" required class="@error('fiscalyear') is-invalid @enderror">
                @foreach(range((int) date('Y') - 2, (int) date('Y') + 5) as $year)
                    <option value="{{ $year }}" {{ (string) $fieldValue('fiscalyear', date('Y')) === (string) $year ? 'selected' : '' }}>{{ $year + 543 }}</option>
                @endforeach
            </select>
            @error('fiscalyear')<div class="pf-error">{{ $message }}</div>@enderror
        </div>
        <div class="pf-field">
            <label for="team_id">ทีมขาย <b>*</b></label>
            <select id="team_id" name="team_id" required class="@error('team_id') is-invalid @enderror">
                <option value="">เลือกทีมขาย</option>
                @foreach($teams as $team)
                    <option value="{{ $team->team_id }}" {{ (string) $fieldValue('team_id') === (string) $team->team_id ? 'selected' : '' }}>{{ $team->team }}</option>
                @endforeach
            </select>
            @error('team_id')<div class="pf-error">{{ $message }}</div>@enderror
        </div>
        <div class="pf-field">
            <label for="priority_id">โอกาสชนะ</label>
            <select id="priority_id" name="priority_id" class="@error('priority_id') is-invalid @enderror">
                <option value="">ยังไม่ระบุ</option>
                @foreach($priorities as $priority)
                    <option value="{{ $priority->priority_id }}" {{ (string) $fieldValue('priority_id') === (string) $priority->priority_id ? 'selected' : '' }}>{{ $priority->priority }}</option>
                @endforeach
            </select>
            @error('priority_id')<div class="pf-error">{{ $message }}</div>@enderror
        </div>
    </div>
</section>

<section class="pf-section" id="sales-plan">
    <div class="pf-section-title"><i class="fas fa-calendar-alt"></i><div><h2>แผนการขายและวันสำคัญ</h2><p>ข้อมูลส่วนนี้ช่วยให้ Forecast และการแจ้งเตือนแม่นยำขึ้น</p></div></div>
    <div class="pf-field-grid">
        <div class="pf-field">
            <label for="contact_start_date">วันที่เริ่มโครงการ <b>*</b></label>
            <input type="text" id="contact_start_date" name="contact_start_date" data-pf-date value="{{ $fieldValue('contact_start_date') }}" required readonly class="@error('contact_start_date') is-invalid @enderror" placeholder="เลือกวันที่">
            @error('contact_start_date')<div class="pf-error">{{ $message }}</div>@enderror
        </div>
        <div class="pf-field">
            <label for="date_of_closing_of_sale">วันที่ Bidding</label>
            <input type="text" id="date_of_closing_of_sale" name="date_of_closing_of_sale" data-pf-date value="{{ $fieldValue('date_of_closing_of_sale') }}" readonly placeholder="เลือกวันที่">
        </div>
        <div class="pf-field">
            <label for="sales_can_be_close">วันที่คาดว่าจะปิดการขาย</label>
            <input type="text" id="sales_can_be_close" name="sales_can_be_close" data-pf-date value="{{ $fieldValue('sales_can_be_close') }}" readonly placeholder="เลือกวันที่">
        </div>
        <div class="pf-field pf-field-full">
            <label for="remark">หมายเหตุโครงการ</label>
            <textarea id="remark" name="remark" placeholder="ข้อมูลสำคัญ เงื่อนไข หรือสิ่งที่ต้องติดตาม">{{ $fieldValue('remark') }}</textarea>
        </div>
    </div>
</section>

<section class="pf-section" id="status-timeline">
    <div class="pf-section-title"><i class="fas fa-stream"></i><div><h2>สถานะและ Timeline</h2><p>เลือกขั้นตอนที่ดำเนินการแล้วและระบุวันที่ของแต่ละสถานะ</p></div></div>
    <div class="pf-step-list">
        @foreach($steps as $step)
            @php
                $hasSavedStep = isset($selectedSteps[$step->level_id]);
                $isChecked = (bool) old('step.'.$step->level_id, $hasSavedStep ? 1 : 0);
                $savedDate = $hasSavedStep && $selectedSteps[$step->level_id]->date
                    ? \Carbon\Carbon::parse($selectedSteps[$step->level_id]->date)->format('Y-m-d')
                    : '';
                $stepDate = old('step_date.'.$step->level_id, $savedDate);
            @endphp
            <div class="pf-step">
                <div class="pf-step-check">
                    <input type="hidden" name="step[{{ $step->level_id }}]" value="0">
                    <input type="checkbox" id="step_{{ $step->level_id }}" name="step[{{ $step->level_id }}]" value="1" data-step-checkbox data-date-target="step_date_{{ $step->level_id }}" {{ $isChecked ? 'checked' : '' }}>
                    <label for="step_{{ $step->level_id }}">{{ $step->level }}</label>
                </div>
                <input type="text" id="step_date_{{ $step->level_id }}" name="step_date[{{ $step->level_id }}]" data-pf-date value="{{ $stepDate }}" readonly placeholder="เลือกวันที่" {{ $isChecked ? '' : 'disabled' }}>
            </div>
        @endforeach
    </div>
</section>

<section class="pf-section" id="contact-information">
    <div class="pf-section-title"><i class="fas fa-address-card"></i><div><h2>ข้อมูลผู้ติดต่อ</h2><p>ข้อมูลเพิ่มเติมสำหรับประสานงานกับลูกค้า</p></div></div>
    <div class="pf-field-grid">
        <div class="pf-field"><label for="contact_person">ชื่อผู้ติดต่อ</label><input type="text" id="contact_person" name="contact_person" value="{{ $fieldValue('contact_person') }}" placeholder="ชื่อ-นามสกุล"></div>
        <div class="pf-field"><label for="contact_phone">เบอร์โทรศัพท์</label><input type="tel" id="contact_phone" name="contact_phone" value="{{ $fieldValue('contact_phone') }}" placeholder="0xx-xxx-xxxx"></div>
        <div class="pf-field"><label for="contact_email">อีเมล</label><input type="email" id="contact_email" name="contact_email" value="{{ $fieldValue('contact_email') }}" placeholder="email@example.com"></div>
        <div class="pf-field pf-field-full"><label for="contact_note">รายละเอียดเพิ่มเติม</label><textarea id="contact_note" name="contact_note" placeholder="ตำแหน่ง ช่องทางติดต่อ หรือข้อมูลอื่น ๆ">{{ $fieldValue('contact_note') }}</textarea></div>
    </div>
</section>
