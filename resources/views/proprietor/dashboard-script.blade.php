@push('scripts')
<script>
(() => {
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    const show = (id, message, error = false) => {
        const target = document.getElementById(id);
        target.className = 'alert ' + (error ? 'alert-danger' : 'alert-success');
        target.textContent = message;
    };
    async function post(url) {
        const response = await fetch(url, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf, Accept: 'application/json', 'Content-Type': 'application/json' }, body: '{}' });
        const data = await response.json().catch(() => ({}));
        if (!response.ok) throw new Error(data.message || 'The request failed. Please try again.');
        return data;
    }
    document.querySelectorAll('.pd-send-sms').forEach(button => button.addEventListener('click', async () => {
        button.disabled = true;
        button.textContent = 'Sending…';
        try {
            const data = await post(button.dataset.url);
            show('pd-sms-message', data.message);
            button.textContent = 'SMS sent';
        } catch (error) {
            show('pd-sms-message', error.message, true);
            button.textContent = 'Send SMS';
            button.disabled = false;
        }
    }));
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
})();
</script>
@endpush
