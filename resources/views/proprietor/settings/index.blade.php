@extends('layouts.proprietor')

@section('title', 'Settings')

@section('content')
    <h1 class="h3 mb-3">Settings</h1>

    <div class="card" style="max-width: 560px;">
        <div class="card-body">
            @if (session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif

            <form method="POST" action="{{ route('proprietor.settings.update') }}">
                @csrf
                @method('PUT')

                <div class="mb-3">
                    <label for="dormitory_name" class="form-label">Dormitory Name</label>
                    <input type="text" class="form-control @error('dormitory_name') is-invalid @enderror"
                           id="dormitory_name" name="dormitory_name"
                           value="{{ old('dormitory_name', $general['dormitory_name'] ?? '') }}" required>
                    @error('dormitory_name')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label for="address" class="form-label">Address</label>
                    <textarea class="form-control @error('address') is-invalid @enderror"
                              id="address" name="address" rows="2">{{ old('address', $general['address'] ?? '') }}</textarea>
                    @error('address')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label for="contact_email" class="form-label">Contact Email</label>
                    <input type="email" class="form-control @error('contact_email') is-invalid @enderror"
                           id="contact_email" name="contact_email"
                           value="{{ old('contact_email', $general['contact_email'] ?? '') }}">
                    @error('contact_email')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label for="contact_number" class="form-label">Contact Number</label>
                    <input type="text" class="form-control @error('contact_number') is-invalid @enderror"
                           id="contact_number" name="contact_number"
                           value="{{ old('contact_number', $general['contact_number'] ?? '') }}">
                    @error('contact_number')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label for="billing_start_date" class="form-label">Billing start date</label>
                    <input type="date" class="form-control @error('billing_start_date') is-invalid @enderror"
                           id="billing_start_date" name="billing_start_date" required aria-describedby="billing-start-help"
                           value="{{ old('billing_start_date', $general['billing_start_date'] ?? config('dorm.billing_start_date')) }}">
                    <div class="form-text" id="billing-start-help">New accruals start from this date. Recorded charges and payments remain in the ledger.</div>
                    @error('billing_start_date')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                @include('proprietor.settings.risk-thresholds')
                @include('proprietor.settings.guardian-sms')

                <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg" aria-hidden="true"></i> Save</button>
            </form>
        </div>
    </div>
@endsection