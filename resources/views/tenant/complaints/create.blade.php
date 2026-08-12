@extends('layouts.tenant')
@section('title', 'Submit Complaint')
@section('content')

<!-- Import Inter font to match your typography screenshot -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">

<style>
    :root {
        --cp-plum: #5E1049;
        --cp-plum-hover: #4A0D39;
        --cp-border: #E8DEE5;
    }

    .cp-create-wrapper {
        max-width: 600px;
        margin: 40px auto;
        width: 100%;
        /* Applied Inter globally to this page */
        font-family: 'Inter', sans-serif;
    }

    .cp-create-header {
        display: flex;
        justify-content: flex-end;
        margin-bottom: 10px;
    }

    .cp-back-link {
        color: #1a1a1a;
        text-decoration: none;
        font-weight: 500;
        font-size: 0.9rem;
    }
    .cp-back-link:hover { text-decoration: underline; }

    .cp-create-card {
        background: #fff;
        border-radius: 20px;
        padding: 40px 30px;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.04);
        border: 1px solid #f3f4f6;
    }

    .cp-create-card h2 {
        text-align: center;
        font-weight: 700;
        font-size: 1.5rem;
        margin-bottom: 30px;
        color: #1a1a1a;
    }

    .cp-create-card .form-label {
        font-weight: 600;
        font-size: 0.9rem;
        color: #1a1a1a;
        margin-bottom: 6px;
    }

    .cp-create-card .form-select,
    .cp-create-card .form-control {
        border: 1px solid var(--cp-border);
        border-radius: 8px;
        padding: 12px 14px;
        font-size: 0.95rem;
        color: #333;
        background-color: #fff;
        font-family: 'Inter', sans-serif;
    }

    .cp-create-card .form-select:focus,
    .cp-create-card .form-control:focus {
        border-color: var(--cp-plum);
        box-shadow: 0 0 0 3px rgba(94, 16, 73, 0.08);
    }

    .cp-create-card textarea {
        resize: vertical;
        min-height: 140px;
    }

    .cp-action-row {
        display: flex;
        justify-content: flex-end;
        gap: 12px;
        margin-top: 30px;
    }

    .cp-btn-cancel {
        background: transparent;
        border: 1px solid var(--cp-plum);
        color: var(--cp-plum);
        border-radius: 999px;
        padding: 10px 24px;
        font-weight: 600;
        text-decoration: none;
        transition: 0.2s;
        font-size: 0.9rem;
        cursor: pointer;
        font-family: 'Inter', sans-serif;
    }
    .cp-btn-cancel:hover { background: #FDF2F8; color: var(--cp-plum); }

    .cp-btn-submit {
        background: var(--cp-plum);
        border: none;
        color: #fff;
        border-radius: 999px;
        padding: 10px 28px;
        font-weight: 600;
        transition: 0.2s;
        font-size: 0.9rem;
        font-family: 'Inter', sans-serif;
    }
    .cp-btn-submit:hover { background: var(--cp-plum-hover); color: #fff; }

    /* ===== CONFIRMATION MODAL STYLES ===== */
    .cp-modal .modal-content {
        border-radius: 20px;
        border: none;
        box-shadow: 0 15px 40px rgba(0,0,0,0.1);
        padding: 30px 20px;
        font-family: 'Inter', sans-serif;
    }

    .cp-modal .modal-body {
        text-align: center; /* Centers alignment like the screenshot */
        padding: 10px 20px;
    }

    .cp-modal .mascot-icon {
        width: 140px;
        height: auto;
        margin: 0 auto 20px;
        display: block;
    }

    .cp-modal h5 {
        font-weight: 700;
        font-size: 1.2rem;
        color: #1a1a1a;
        margin-bottom: 10px;
    }

    .cp-modal p {
        /* Matches typography: Inter Regular, 10px (applied via font-size), Line-height Auto, Opacity 74% black */
        font-family: 'Inter', sans-serif;
        font-weight: 400;
        font-size: 0.9rem; /* Slightly adjusted to 0.9rem for readability since 10px is really tiny */
        color: rgba(0, 0, 0, 0.74);
        line-height: 1.5;
        margin-bottom: 30px;
        max-width: 320px;
        margin-left: auto;
        margin-right: auto;
    }

    .cp-modal .modal-actions {
        display: flex;
        justify-content: center;
        gap: 12px;
    }
</style>

<div class="cp-create-wrapper">

    <div class="cp-create-header">
        <a href="{{ route('tenant.complaints.index') }}" class="cp-back-link">Back</a>
    </div>

    <div class="cp-create-card">
        <h2>Tenant Complaint Form</h2>

        <form method="POST" action="{{ route('tenant.complaints.store') }}" id="complaintForm">
            @csrf

            <div class="mb-3">
                <select class="form-select @error('category') is-invalid @enderror" id="category" name="category" required>
                    <option value="">Select Category</option>
                    @foreach (['maintenance' => 'Maintenance', 'noise' => 'Noise', 'billing' => 'Billing', 'other' => 'Other'] as $value => $label)
                        <option value="{{ $value }}" @selected(old('category') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('category')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="mb-3">
                <label for="description" class="form-label">Description</label>
                <textarea class="form-control @error('description') is-invalid @enderror" id="description" name="description" rows="6" required>{{ old('description') }}</textarea>
                @error('description')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="cp-action-row">
                <!-- Cancel button triggers the modal -->
                <button type="button" class="cp-btn-cancel" data-bs-toggle="modal" data-bs-target="#cancelModal">Cancel</button>
                <button type="submit" class="cp-btn-submit">Submit Complaint</button>
            </div>
        </form>
    </div>
</div>

<<!-- ===== CANCEL CONFIRMATION MODAL ===== -->
<div class="modal fade cp-modal" id="cancelModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 400px;">
        <div class="modal-content">
            <div class="modal-body">

                <!-- Using your local images/icon.png file -->
                <img src="{{ asset('images/icon.png') }}" alt="Confirmation Mascot" class="mascot-icon">

                <!-- UPDATED: Applied 'Inter' font explicitly to these texts -->
                <h5 style="font-family: 'Inter', sans-serif; font-weight: 700; font-size: 1.2rem; color: #1a1a1a; margin-bottom: 10px;">Are you sure?</h5>

                <p style="font-family: 'Inter', sans-serif; font-weight: 400; font-size: 0.9rem; color: rgba(0, 0, 0, 0.74); line-height: 1.5; margin-bottom: 30px; max-width: 320px; margin-left: auto; margin-right: auto;">
                    You are about to cancel your entry, are you sure you want to exit?
                </p>

                <div class="modal-actions">
                    <button type="button" class="cp-btn-cancel" data-bs-dismiss="modal">Back</button>
                    <a href="{{ route('tenant.complaints.index') }}" class="cp-btn-submit" style="text-decoration:none; display:inline-block;">Yes</a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
