(function () {
    'use strict';

    const form = document.querySelector('[data-pf-sales-form]');
    const moneyInput = document.querySelector('[data-pf-money]');

    function formatMoney(value) {
        const cleaned = String(value || '').replace(/[^0-9.]/g, '');
        if (!cleaned) return '';
        const parts = cleaned.split('.');
        return Number(parts[0] || 0).toLocaleString('en-US') + (parts[1] ? '.' + parts[1].slice(0, 2) : '');
    }

    function numericValue(value) {
        return Number(String(value || '').replace(/,/g, '')) || 0;
    }

    function updateFormSummary() {
        const title = document.querySelector('[name="Product_detail"]');
        const company = document.querySelector('[name="company_id"]');
        const product = document.querySelector('[name="Product_id"]');
        const fiscalYear = document.querySelector('[name="fiscalyear"]');
        const value = document.querySelector('[name="product_value"]');
        const required = Array.from(document.querySelectorAll('[data-pf-sales-form] [required]'));
        const complete = required.filter(function (input) { return String(input.value || '').trim() !== ''; }).length;
        const percent = required.length ? Math.round((complete / required.length) * 100) : 0;

        document.querySelectorAll('[data-summary-title]').forEach(function (node) { node.textContent = title && title.value ? title.value : 'โครงการใหม่'; });
        document.querySelectorAll('[data-summary-company]').forEach(function (node) { node.textContent = company && company.selectedIndex >= 0 ? company.options[company.selectedIndex].text : '-'; });
        document.querySelectorAll('[data-summary-product]').forEach(function (node) { node.textContent = product && product.selectedIndex >= 0 ? product.options[product.selectedIndex].text : '-'; });
        document.querySelectorAll('[data-summary-year]').forEach(function (node) { node.textContent = fiscalYear && fiscalYear.selectedIndex >= 0 ? fiscalYear.options[fiscalYear.selectedIndex].text : '-'; });
        document.querySelectorAll('[data-summary-value]').forEach(function (node) { node.textContent = '฿' + numericValue(value && value.value).toLocaleString('th-TH'); });
        document.querySelectorAll('[data-completion-value]').forEach(function (node) { node.textContent = percent + '%'; });
        document.querySelectorAll('[data-completion-bar]').forEach(function (node) { node.style.width = percent + '%'; });
    }

    if (moneyInput) {
        moneyInput.value = formatMoney(moneyInput.value);
        moneyInput.addEventListener('input', function () {
            const cursor = moneyInput.selectionStart;
            const oldLength = moneyInput.value.length;
            moneyInput.value = formatMoney(moneyInput.value);
            const nextCursor = cursor + (moneyInput.value.length - oldLength);
            moneyInput.setSelectionRange(nextCursor, nextCursor);
            updateFormSummary();
        });
    }

    document.querySelectorAll('[data-step-checkbox]').forEach(function (checkbox) {
        const date = document.getElementById(checkbox.getAttribute('data-date-target'));
        const sync = function () {
            if (!date) return;
            date.disabled = !checkbox.checked;
            if (date._flatpickr && date._flatpickr.altInput) {
                date._flatpickr.altInput.disabled = !checkbox.checked;
            }
            if (!checkbox.checked) {
                if (date._flatpickr) date._flatpickr.clear();
                else date.value = '';
            }
        };
        checkbox.addEventListener('change', sync);
        sync();
    });

    document.querySelectorAll('[data-section-link]').forEach(function (link) {
        link.addEventListener('click', function (event) {
            const target = document.querySelector(link.getAttribute('href'));
            if (!target) return;
            event.preventDefault();
            document.querySelectorAll('[data-section-link]').forEach(function (item) { item.classList.remove('active'); });
            link.classList.add('active');
            target.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    });

    if (form) {
        const dirty = document.querySelector('[data-dirty-indicator]');
        form.addEventListener('input', function () {
            if (dirty) dirty.classList.add('is-visible');
            updateFormSummary();
        });
        form.addEventListener('change', updateFormSummary);
        form.addEventListener('submit', function () {
            if (moneyInput) moneyInput.value = moneyInput.value.replace(/,/g, '');
            document.querySelectorAll('[data-pf-date]').forEach(function (input) {
                const value = input.value;
                if (!value || !value.includes('/')) return;
                const parts = value.split('/');
                if (parts.length !== 3) return;
                const year = Number(parts[2]) > 2400 ? Number(parts[2]) - 543 : Number(parts[2]);
                input.value = [year, parts[1], parts[0]].join('-');
            });
        });
        updateFormSummary();
    }
})();
