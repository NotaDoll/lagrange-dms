@extends('layouts.proprietor')

@section('title', 'Bulk Import Tenants')

@section('content')
    <x-breadcrumbs :items="['Tenants' => route('proprietor.tenants.index'), 'Bulk import' => null]" />
    <h1 class="h3 mb-4">Bulk Import Tenants</h1>

    <div class="card mb-4">
        <div class="card-body">
            <a href="{{ route('proprietor.tenants.import.template') }}" class="btn btn-outline-secondary btn-sm mb-3">
                Download CSV Template
            </a>

            <ul class="small mb-4">
                <li>Use one row per tenant. The first row contains the template headers.</li>
                <li>Enter the tenant's first name, last name, email, contact number, home address, room type, room number, bed label, status, and emergency contact name, number, and relationship. Middle name and guardian details are optional.</li>
                <li>Use CABIN, SUITE, DELUXE, or SUPREME for room type. Enter the number and bed letter in their own columns (for example, CABIN, 2, A).</li>
                <li>Use <code>reserved</code> with a blank move-in date, or <code>active</code> with a move-in date in <code>YYYY-MM-DD</code> format, today or earlier.</li>
                <li>Use <code>mother</code>, <code>father</code>, <code>sibling</code>, <code>guardian</code>, or <code>other</code> for the emergency relationship. Blank deposit and advance payment amounts default to ₱0.</li>
                <li>In Excel, format contact numbers as <strong>Text</strong> to keep leading zeros. Format move-in dates as Text before entering them.</li>
            </ul>

            <form action="{{ route('proprietor.tenants.import.store') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="mb-3">
                    <label for="file" class="form-label">CSV or Excel file</label>
                    <input type="file" id="file" name="file" class="form-control @error('file') is-invalid @enderror" accept=".csv,.xlsx,.xls" required
                        aria-describedby="file-help @error('file') file-error @enderror" @error('file') aria-invalid="true" @enderror>
                    <div class="form-text" id="file-help">Up to 200 tenant rows and 2 MB per file. Excel imports use the first worksheet.</div>
                    @error('file') <div class="invalid-feedback" id="file-error">{{ $message }}</div> @enderror
                </div>
                <div class="form-check mb-3">
                    <input type="checkbox" id="consent" name="consent" value="1" class="form-check-input @error('consent') is-invalid @enderror"
                        @checked(old('consent')) required @error('consent') aria-invalid="true" aria-describedby="consent-error" @enderror>
                    <label class="form-check-label" for="consent">I confirm the tenants in this file (or their guardians) have given consent to the collection and processing of their personal information (Data Privacy Act).</label>
                    @error('consent') <div class="invalid-feedback" id="consent-error">{{ $message }}</div> @enderror
                </div>
                <button type="submit" class="btn btn-primary">Preview import</button>
                <a href="{{ route('proprietor.tenants.index') }}" class="btn btn-outline-secondary">Cancel</a>
            </form>
        </div>
    </div>
@endsection
