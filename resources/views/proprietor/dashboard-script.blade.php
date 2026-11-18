@push('scripts')
<script>
(() => {
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    const show = (id, message, error = false) => {
        const target = document.getElementById(id);
        target.className = 'alert ' + (error ? 'alert-danger' : 'alert-success');
        target.textContent = message;
    };
    async function post(url, payload = {}) {
        const response = await fetch(url, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf, Accept: 'application/json', 'Content-Type': 'application/json' }, body: JSON.stringify(payload) });
        const data = await response.json().catch(() => ({}));
        if (!response.ok) throw new Error(data.message || 'The request failed. Please try again.');
        return data;
    }
    async function getJson(url) {
        const response = await fetch(url, { headers: { Accept: 'application/json' } });
        const data = await response.json().catch(() => ({}));
        if (!response.ok) throw new Error(data.message || 'Could not load the message. Please try again.');
        return data;
    }

    // Same billing rule as GuardianSmsMessage::stats(): 160 characters per text, or 70 if any
    // character (₱, curly quotes, emoji) is outside the basic SMS alphabet.
    const gsm = "@£$¥èéùìòÇ\nØø\rÅåΔ_ΦΓΛΩΠΨΣΘΞÆæßÉ !\"#¤%&'()*+,-./0123456789:;<=>?¡ABCDEFGHIJKLMNOPQRSTUVWXYZÄÖÑÜ§¿abcdefghijklmnopqrstuvwxyzäöñüà";
    const ext = '^{}\\[~]|€';
    const countText = (text) => {
        const chars = [...text];
        const unicode = chars.some((c) => !gsm.includes(c) && !ext.includes(c));
        const length = unicode ? chars.length : chars.reduce((n, c) => n + (ext.includes(c) ? 2 : 1), 0);
        const texts = unicode ? (length <= 70 ? 1 : Math.ceil(length / 67)) : (length <= 160 ? 1 : Math.ceil(length / 153));
        return `${length} characters · ${texts} ${texts === 1 ? 'text' : 'texts'}${unicode ? ' (₱ or special characters use 70 per text)' : ''}`;
    };
    const watchCount = (textarea, label) => {
        const update = () => { label.textContent = countText(textarea.value); };
        textarea.addEventListener('input', update);
        update();
    };
    const statusLabels = {queued: 'SMS queued', unknown: 'SMS unconfirmed', delivered: 'SMS delivered', sent: 'SMS sent'};
    const escapeHtml = (text) => String(text ?? '').replace(/[&<>"']/g, (c) => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c]));

    // One guardian: open a preview the proprietor can edit, then send.
    const smsModalEl = document.getElementById('pd-sms-modal');
    const smsText = document.getElementById('pd-sms-text');
    const smsSend = document.getElementById('pd-sms-send');
    const smsError = document.getElementById('pd-sms-error');
    let smsButton = null;
    watchCount(smsText, smsModalEl.querySelector('.pd-sms-count'));

    document.querySelectorAll('.pd-send-sms').forEach(button => button.addEventListener('click', async () => {
        smsButton = button;
        smsText.value = '';
        smsText.dispatchEvent(new Event('input'));
        smsError.classList.add('d-none');
        document.getElementById('pd-sms-already').classList.toggle('d-none', !button.dataset.logId);
        document.getElementById('pd-sms-to').textContent = 'Loading…';
        smsSend.disabled = true;
        bootstrap.Modal.getOrCreateInstance(smsModalEl).show();
        try {
            const data = await getJson(button.dataset.previewUrl);
            document.getElementById('pd-sms-to').innerHTML = `To <strong>${escapeHtml(data.guardian_name)}</strong>${data.number_hint ? ` (${escapeHtml(data.number_hint)})` : ''}, guardian of ${escapeHtml(data.tenant_name)}`;
            smsText.value = data.message;
            smsText.dispatchEvent(new Event('input'));
            smsSend.disabled = false;
            smsText.focus();
        } catch (error) {
            smsError.textContent = error.message;
            smsError.classList.remove('d-none');
        }
    }));

    smsSend.addEventListener('click', async () => {
        if (!smsButton) return;
        if (!smsText.value.trim()) {
            smsError.textContent = 'Write a message before sending.';
            smsError.classList.remove('d-none');
            return;
        }
        const previousId = smsButton.dataset.logId;
        const statusLabel = smsButton.parentElement.querySelector('.pd-sms-status');
        smsSend.disabled = true;
        smsSend.textContent = 'Sending…';
        try {
            const payload = { message: smsText.value };
            if (previousId) payload.resend_after = previousId;
            const data = await post(smsButton.dataset.url, payload);
            show('pd-sms-message', data.message);
            statusLabel.textContent = statusLabels[data.status] || '';
            smsButton.dataset.logId = data.log_id;
            smsButton.textContent = 'Send SMS again';
            bootstrap.Modal.getOrCreateInstance(smsModalEl).hide();
        } catch (error) {
            smsError.textContent = error.message;
            smsError.classList.remove('d-none');
        } finally {
            smsSend.disabled = false;
            smsSend.textContent = 'Send SMS';
        }
    });

    // Every high-risk guardian: one personalized, editable message each.
    const allButton = document.getElementById('pd-sms-all');
    const allModalEl = document.getElementById('pd-sms-all-modal');
    const allList = document.getElementById('pd-sms-all-list');
    const allSend = document.getElementById('pd-sms-all-send');
    const allResult = document.getElementById('pd-sms-all-result');
    const refreshAllSend = () => {
        const n = allList.querySelectorAll('.pd-sms-pick:checked').length;
        allSend.disabled = n === 0;
        allSend.textContent = n === 0 ? 'Send' : `Send ${n} ${n === 1 ? 'message' : 'messages'}`;
    };
    allButton?.addEventListener('click', async () => {
        allResult.classList.add('d-none');
        allList.innerHTML = '<p class="text-muted">Loading…</p>';
        allSend.disabled = true;
        bootstrap.Modal.getOrCreateInstance(allModalEl).show();
        try {
            const data = await getJson(allButton.dataset.previewUrl);
            if (!data.recipients.length) {
                allList.innerHTML = '<p class="text-muted mb-0">No high-risk tenant has a guardian number right now.</p>';
                return;
            }
            allList.innerHTML = data.recipients.map((r) => `
                <div class="border rounded-3 p-3 mb-3" data-tenant="${r.tenant_id}">
                    <div class="form-check mb-2">
                        <input class="form-check-input pd-sms-pick" type="checkbox" id="pd-pick-${r.tenant_id}" ${r.sent_today ? '' : 'checked'}>
                        <label class="form-check-label" for="pd-pick-${r.tenant_id}">
                            <strong>${escapeHtml(r.guardian_name)}</strong>${r.number_hint ? ` <span class="text-muted">(${escapeHtml(r.number_hint)})</span>` : ''}, guardian of ${escapeHtml(r.tenant_name)}
                            ${r.sent_today ? '<span class="badge text-bg-warning ms-1">Texted today</span>' : ''}
                        </label>
                    </div>
                    <label class="visually-hidden" for="pd-msg-${r.tenant_id}">Message for ${escapeHtml(r.guardian_name)}</label>
                    <textarea class="form-control form-control-sm pd-sms-body" id="pd-msg-${r.tenant_id}" rows="4" maxlength="{{ \App\Services\GuardianSmsMessage::MAX_LENGTH }}">${escapeHtml(r.message)}</textarea>
                    <div class="small text-muted mt-1 pd-sms-count"></div>
                    <div class="small mt-1 pd-sms-row-result"></div>
                </div>`).join('');
            allList.querySelectorAll('[data-tenant]').forEach((row) => watchCount(row.querySelector('.pd-sms-body'), row.querySelector('.pd-sms-count')));
            allList.querySelectorAll('.pd-sms-pick').forEach((box) => box.addEventListener('change', refreshAllSend));
            refreshAllSend();
        } catch (error) {
            allList.innerHTML = `<div class="alert alert-danger mb-0">${escapeHtml(error.message)}</div>`;
        }
    });
    allSend.addEventListener('click', async () => {
        const rows = [...allList.querySelectorAll('[data-tenant]')].filter((row) => row.querySelector('.pd-sms-pick').checked);
        const empty = rows.find((row) => !row.querySelector('.pd-sms-body').value.trim());
        if (empty) {
            empty.querySelector('.pd-sms-row-result').innerHTML = '<span class="text-danger">Write a message or untick this guardian.</span>';
            empty.querySelector('.pd-sms-body').focus();
            return;
        }
        if (!window.confirm(`Send ${rows.length} ${rows.length === 1 ? 'message' : 'messages'} now?`)) return;
        allSend.disabled = true;
        allSend.textContent = 'Sending…';
        try {
            const data = await post(allSend.dataset.url ?? allButton.dataset.url, {
                recipients: rows.map((row) => ({ tenant_id: Number(row.dataset.tenant), message: row.querySelector('.pd-sms-body').value })),
            });
            const labels = { sent: '<span class="text-success">Sent</span>', skipped: '<span class="text-warning-emphasis">Skipped</span>', failed: '<span class="text-danger">Failed</span>' };
            data.rows.forEach((r) => {
                const row = allList.querySelector(`[data-tenant="${r.tenant_id}"]`);
                if (!row) return;
                row.querySelector('.pd-sms-row-result').innerHTML = labels[r.status] + (r.reason ? ` · ${escapeHtml(r.reason)}` : '');
                if (r.status === 'sent') row.querySelector('.pd-sms-pick').checked = false;
            });
            allResult.className = 'alert small mt-3 mb-0 ' + (data.failed ? 'alert-warning' : 'alert-success');
            allResult.textContent = data.message;
        } catch (error) {
            allResult.className = 'alert alert-danger small mt-3 mb-0';
            allResult.textContent = error.message;
        } finally {
            refreshAllSend();
        }
    });
    allModalEl.addEventListener('hidden.bs.modal', () => { if (allResult.textContent && !allResult.classList.contains('d-none')) window.location.reload(); });
    const generate = document.getElementById('pd-generate-summary');
    generate.addEventListener('click', async () => {
        generate.disabled = true;
        generate.setAttribute('aria-busy', 'true');
        generate.textContent = 'Generating summary…';
        try {
            await post(generate.dataset.url);
            window.location.reload();
        } catch (error) {
            show('pd-summary-message', error.message, true);
            generate.disabled = false;
            generate.removeAttribute('aria-busy');
            generate.textContent = 'Generate new summary';
        }
    });

    // Follow-up table: High/Medium filter and name/room search, all in the browser.
    const filters = document.getElementById('pd-follow-filters');
    if (filters) {
        const rows = [...document.querySelectorAll('.pd-follow-table tbody tr[data-level]')];
        const none = document.getElementById('pd-follow-none');
        const search = document.getElementById('pd-follow-search');
        let level = 'all';
        const apply = () => {
            const term = search.value.trim().toLowerCase();
            let shown = 0;
            rows.forEach((row) => {
                const visible = (level === 'all' || row.dataset.level === level) && (!term || row.dataset.search.includes(term));
                row.classList.toggle('d-none', !visible);
                shown += visible ? 1 : 0;
            });
            none.classList.toggle('d-none', shown > 0);
        };
        filters.querySelectorAll('button[data-level]').forEach((button) => button.addEventListener('click', () => {
            level = button.dataset.level;
            filters.querySelectorAll('button[data-level]').forEach((other) => {
                const active = other === button;
                other.classList.toggle('btn-primary', active);
                other.classList.toggle('btn-outline-primary', !active);
                other.setAttribute('aria-pressed', active ? 'true' : 'false');
            });
            apply();
        }));
        search.addEventListener('input', apply);
    }

    // Reminders TextBee hasn't confirmed yet: ask for their latest status once the page has loaded.
    const smsLabels = [...document.querySelectorAll('.pd-sms-status[data-tenant-id]')];
    const refreshUrl = document.getElementById('pd-sms-message')?.dataset.refreshUrl;
    if (refreshUrl && smsLabels.some((label) => ['SMS queued', 'SMS unconfirmed'].includes(label.textContent.trim()))) {
        post(refreshUrl).then((data) => {
            const failedLabel = 'SMS failed';
            smsLabels.forEach((label) => {
                const status = data.statuses?.[label.dataset.tenantId];
                if (status) label.textContent = status === 'failed' ? failedLabel : (statusLabels[status] || label.textContent);
            });
        }).catch(() => { /* leave the labels as they are; the scheduler will catch up */ });
    }

    // The page scrolls inside the layout's content panel, so jump to #pd-trends etc. ourselves.
    if (location.hash) {
        window.addEventListener('load', () => document.getElementById(location.hash.slice(1))?.scrollIntoView({ block: 'start' }));
    }
})();
</script>
@endpush
