@extends('layouts.proprietor')

@section('title', 'Settings')

@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1">Settings</h1>
            <p class="text-muted mb-0">Manage dormitory details, billing, SMS, and payment risk rules.</p>
        </div>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger" role="alert">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card mb-3">
        <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div>
                <h2 class="h5 mb-1">Proprietor account</h2>
                <p class="text-muted small mb-0">Change your account name, email, or password.</p>
            </div>
            <a class="btn btn-outline-primary" href="{{ route('profile.edit') }}">Manage account</a>
        </div>
    </div>

    <form method="POST" action="{{ route('proprietor.settings.update') }}" class="d-grid gap-3">
        @csrf
        @method('PUT')

        <section class="card" aria-labelledby="dormitory-details-heading">
            <div class="card-body">
                <h2 class="h5" id="dormitory-details-heading">Dormitory details</h2>
                <p class="text-muted small">Contact information shown in reports and resident communications.</p>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="dormitory_name" class="form-label">Dormitory name</label>
                        <input type="text" class="form-control @error('dormitory_name') is-invalid @enderror"
                               id="dormitory_name" name="dormitory_name"
                               value="{{ old('dormitory_name', $general['dormitory_name'] ?? '') }}" required>
                        @error('dormitory_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label for="address" class="form-label">Address</label>
                        <input type="text" class="form-control @error('address') is-invalid @enderror"
                               id="address" name="address" value="{{ old('address', $general['address'] ?? '') }}">
                        @error('address')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label for="contact_email" class="form-label">Contact email</label>
                        <input type="email" class="form-control @error('contact_email') is-invalid @enderror"
                               id="contact_email" name="contact_email" value="{{ old('contact_email', $general['contact_email'] ?? '') }}">
                        @error('contact_email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label for="contact_number" class="form-label">Contact number</label>
                        <input type="text" class="form-control @error('contact_number') is-invalid @enderror"
                               id="contact_number" name="contact_number" value="{{ old('contact_number', $general['contact_number'] ?? '') }}">
                        @error('contact_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>
        </section>

        <section class="card" aria-labelledby="billing-settings-heading">
            <div class="card-body">
                <h2 class="h5" id="billing-settings-heading">Billing settings</h2>
                <p class="text-muted small">Controls when the system begins generating new rent and charge entries.</p>
                <div class="col-md-6">
                    <label for="billing_start_date" class="form-label">Billing start date</label>
                    <input type="date" class="form-control @error('billing_start_date') is-invalid @enderror"
                           id="billing_start_date" name="billing_start_date" required aria-describedby="billing-start-help"
                           value="{{ old('billing_start_date', $general['billing_start_date'] ?? config('dorm.billing_start_date')) }}">
                    <div class="form-text" id="billing-start-help">New accruals start from this date. Recorded charges and payments remain in the ledger.</div>
                    @error('billing_start_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
        </section>

        <section class="card" aria-labelledby="sms-settings-heading">
            <div class="card-body">
                <h2 class="h5" id="sms-settings-heading">SMS settings</h2>
                <p class="text-muted small">Edit the guardian reminder wording here. Manage the sending number and SMS line settings in the TextBee app.</p>
                @include('proprietor.settings.guardian-sms')
            </div>
        </section>

        <section class="card" aria-labelledby="risk-settings-heading">
            <div class="card-body">
                <h2 class="h5" id="risk-settings-heading">Payment risk settings</h2>
                @include('proprietor.settings.risk-thresholds')
            </div>
        </section>

        <div>
            <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg" aria-hidden="true"></i> Save settings</button>
        </div>
    </form>
@endsection
