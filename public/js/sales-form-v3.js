(function () {
    'use strict';

    const thaiLocale = {
        firstDayOfWeek: 1,
        weekdays: {
            shorthand: ['อา', 'จ', 'อ', 'พ', 'พฤ', 'ศ', 'ส'],
            longhand: ['อาทิตย์', 'จันทร์', 'อังคาร', 'พุธ', 'พฤหัสบดี', 'ศุกร์', 'เสาร์']
        },
        months: {
            shorthand: ['ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'],
            longhand: ['มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน', 'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม']
        }
    };

    function buddhistDate(date) {
        if (!(date instanceof Date) || Number.isNaN(date.getTime())) return '';
        return String(date.getDate()).padStart(2, '0') + '/' + String(date.getMonth() + 1).padStart(2, '0') + '/' + (date.getFullYear() + 543);
    }

    if (window.flatpickr) {
        document.querySelectorAll('[data-pf-date]').forEach(function (input) {
            const initial = input.value || null;
            window.flatpickr(input, {
                dateFormat: 'Y-m-d',
                defaultDate: initial,
                altInput: true,
                altFormat: 'd/m/Y',
                allowInput: false,
                locale: thaiLocale,
                onReady: function (dates, value, instance) { if (dates[0] && instance.altInput) instance.altInput.value = buddhistDate(dates[0]); },
                onChange: function (dates, value, instance) { if (dates[0] && instance.altInput) instance.altInput.value = buddhistDate(dates[0]); }
            });
        });
    }

    const requestForm = document.getElementById('companyRequestForm');
    if (requestForm) {
        requestForm.addEventListener('submit', function (event) {
            event.preventDefault();
            const submit = requestForm.querySelector('[type="submit"]');
            const status = document.getElementById('requestStatus');
            submit.disabled = true;
            status.innerHTML = '<div class="alert alert-info">กำลังส่งคำขอ...</div>';
            fetch(requestForm.action, {
                method: 'POST', body: new FormData(requestForm), headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            }).then(function (response) { return response.json().then(function (data) { return { ok: response.ok, data: data }; }); })
                .then(function (result) {
                    if (!result.ok || !result.data.success) throw new Error(result.data.message || 'ส่งคำขอไม่สำเร็จ');
                    status.innerHTML = '<div class="alert alert-success"></div>';
                    status.firstElementChild.textContent = result.data.message;
                    requestForm.reset();
                    window.setTimeout(function () { if (window.jQuery) window.jQuery('#requestCompanyModal').modal('hide'); status.innerHTML = ''; }, 1200);
                })
                .catch(function (error) { status.innerHTML = '<div class="alert alert-danger"></div>'; status.firstElementChild.textContent = error.message; })
                .finally(function () { submit.disabled = false; });
        });
    }
})();
