@extends('layouts.proprietor')

@section('title', 'Bulk Import Tenants')

@section('content')
    <h1 class="mb-4">Bulk Import Tenants</h1>

    <div class="card mb-4">
        <div class="card-body">
            <p>Upload an Excel (.xlsx) or CSV file with one row per tenant.</p>
            <a href="{{ route('proprietor.tenants.import.template') }}" class="btn btn-outline-secondary btn-sm mb-3">
                Download Template
            </a>

            @if ($errors->any())
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form action="{{ route('proprietor.tenants.import.store') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="mb-3">
                    <label for="file" class="form-label">Excel or CSV file</label>
                    <input type="file" id="file" name="file" class="form-control" accept=".xlsx,.xls,.csv" required>
                </div>
                <button type="submit" class="btn btn-primary">Upload &amp; Import</button>
                <a href="{{ route('proprietor.tenants.index') }}" class="btn btn-outline-secondary">Cancel</a>
            </form>
        </div>
    </div>
@endsection