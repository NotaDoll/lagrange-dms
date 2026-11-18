<fieldset id="guardian-sms" class="border rounded-3 p-3 mb-4">
    <legend class="float-none w-auto px-2 h6">Guardian payment reminder (SMS)</legend>
    <p class="small text-muted">
        Sent to a high-risk tenant's guardian from the Dashboard. The words in brackets are filled in for each tenant.
        You can still change the message for one tenant before sending.
    </p>

    <label for="sms_guardian_template" class="form-label">Message</label>
    <textarea class="form-control @error('sms_guardian_template') is-invalid @enderror" id="sms_guardian_template" name="sms_guardian_template"
              rows="5" maxlength="{{ \App\Services\GuardianSmsMessage::MAX_LENGTH }}" data-default="{{ \App\Services\GuardianSmsMessage::DEFAULT_TEMPLATE }}"
              aria-describedby="sms-template-count sms-template-help">{{ old('sms_guardian_template', $guardianTemplate) }}</textarea>
    @error('sms_guardian_template')<div class="invalid-feedback">{{ $message }}</div>@enderror

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-2">
        <div class="d-flex flex-wrap gap-1" role="group" aria-label="Insert a placeholder">
            @foreach (\App\Services\GuardianSmsMessage::PLACEHOLDERS as $placeholder => $meaning)
                <button type="button" class="btn btn-sm btn-outline-secondary sms-insert" data-insert="{{ $placeholder }}" title="{{ $meaning }}">{{ $placeholder }}</button>
            @endforeach
        </div>
        <button type="button" class="btn btn-sm btn-link" id="sms-template-reset"><i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i> Reset to default</button>
    </div>
    <div id="sms-template-help" class="form-text">Click a placeholder to insert it where your cursor is. Press Enter for a new line.</div>

    <div class="mt-3">
        <div class="small fw-semibold mb-1">Preview (sample tenant)</div>
        <div class="border rounded-3 p-3 bg-light small" id="sms-template-preview" style="white-space: pre-line;">{{ $guardianSample }}</div>
        <div class="small mt-1" id="sms-template-count" aria-live="polite"></div>
    </div>
</fieldset>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const box = document.getElementById('sms_guardian_template');
    if (!box) return;
    const preview = document.getElementById('sms-template-preview');
    const count = document.getElementById('sms-template-count');
    const sample = { '[guardian name]': 'Maria Dela Cruz', '[tenant name]': 'Juan Dela Cruz', '[amount]': '3,000.00', '[due date]': 'October 12, 2026' };
    // Same rules as GuardianSmsMessage::stats(): one GSM-7 text holds 160 characters, but any
    // other character (₱, curly quotes, emoji) switches the whole message to 70 per text.
    const gsm = "@£$¥èéùìòÇ\nØø\rÅåΔ_ΦΓΛΩΠΨΣΘΞÆæßÉ !\"#¤%&'()*+,-./0123456789:;<=>?¡ABCDEFGHIJKLMNOPQRSTUVWXYZÄÖÑÜ§¿abcdefghijklmnopqrstuvwxyzäöñüà";
    const ext = '^{}\\[~]|€';
    const fill = (text) => text
        .replace(/\[(guardian|tenant)['’]s name\]/gi, (m, who) => `[${who} name]`)
        .replace(/\[(guardian name|tenant name|amount|due date)\]/gi, (m) => sample[m.toLowerCase()]);
    const update = () => {
        const message = fill(box.value);
        preview.textContent = message;
        const chars = [...message];
        const odd = [...new Set(chars.filter((c) => !gsm.includes(c) && !ext.includes(c)))];
        let texts, length;
        if (odd.length) {
            length = chars.length;
            texts = length <= 70 ? 1 : Math.ceil(length / 67);
        } else {
            length = chars.reduce((n, c) => n + (ext.includes(c) ? 2 : 1), 0);
            texts = length <= 160 ? 1 : Math.ceil(length / 153);
        }
        count.className = 'small mt-1 ' + (texts > 1 ? 'text-warning-emphasis' : 'text-muted');
        count.textContent = `${length} characters · sent as ${texts} ${texts === 1 ? 'text' : 'texts'} per guardian`
            + (odd.length ? `. "${odd.join('" "')}" makes the phone send it in Unicode (70 characters per text); write PHP instead of ₱ and use a plain ' to fit more per text.` : '.');
    };
    const insert = (text) => {
        const start = box.selectionStart ?? box.value.length;
        const end = box.selectionEnd ?? box.value.length;
        box.setRangeText(text, start, end, 'end');
        box.focus();
        update();
    };
    document.querySelectorAll('.sms-insert').forEach((button) => button.addEventListener('click', () => insert(button.dataset.insert)));
    document.getElementById('sms-template-reset').addEventListener('click', () => { box.value = box.dataset.default; update(); });
    box.addEventListener('input', update);
    update();
});
</script>
@endpush
