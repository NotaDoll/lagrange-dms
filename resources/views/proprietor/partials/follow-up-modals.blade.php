    {{-- Review and edit a guardian reminder before it is sent. --}}
    <div class="modal fade" id="pd-sms-modal" tabindex="-1" aria-labelledby="pd-sms-modal-title" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title h5" id="pd-sms-modal-title">Text the guardian</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="small mb-2" id="pd-sms-to">Loading…</p>
                <label for="pd-sms-text" class="form-label">Message</label>
                <textarea class="form-control" id="pd-sms-text" rows="6" maxlength="{{ \App\Services\GuardianSmsMessage::MAX_LENGTH }}"></textarea>
                <div class="small text-muted mt-1 pd-sms-count" data-for="pd-sms-text" aria-live="polite"></div>
                <div class="alert alert-warning small mt-2 mb-0 d-none" id="pd-sms-already">This guardian was already texted today. Sending again will reach them twice.</div>
                <div class="alert alert-danger small mt-2 mb-0 d-none" id="pd-sms-error" role="alert"></div>
            </div>
            <div class="modal-footer justify-content-between">
                <a class="small" href="{{ route('proprietor.settings.edit') }}#guardian-sms">Change the default message</a>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><i class="bi bi-x-lg" aria-hidden="true"></i> Cancel</button>
                    <button type="button" class="btn btn-primary" id="pd-sms-send">Send SMS</button>
                </div>
            </div>
        </div></div>
    </div>

    {{-- Send a personalized reminder to every high-risk tenant's guardian. --}}
    <div class="modal fade" id="pd-sms-all-modal" tabindex="-1" aria-labelledby="pd-sms-all-title" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title h5" id="pd-sms-all-title">Text all high-risk guardians</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted">Each message is filled in for that tenant. Edit any of them, or untick a guardian to leave them out. Guardians already texted today are unticked.</p>
                <div id="pd-sms-all-list"><p class="text-muted">Loading…</p></div>
                <div class="alert small mt-3 mb-0 d-none" id="pd-sms-all-result" role="status"></div>
            </div>
            <div class="modal-footer justify-content-between">
                <a class="small" href="{{ route('proprietor.settings.edit') }}#guardian-sms">Change the default message</a>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><i class="bi bi-x-lg" aria-hidden="true"></i> Close</button>
                    <button type="button" class="btn btn-primary" id="pd-sms-all-send" disabled>Send</button>
                </div>
            </div>
        </div></div>
    </div>
