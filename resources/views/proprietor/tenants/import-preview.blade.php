@extends('layouts.proprietor')

@section('title', 'Preview Tenant Import')

@section('content')
    <h1 class="h3 mb-4">Preview Tenant Import</h1>

    <div class="card mb-3">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm mb-0" aria-label="Import totals">
                    <thead>
                        <tr><th scope="col">Total rows</th><th scope="col">Valid rows</th><th scope="col">Rows with errors</th></tr>
                    </thead>
                    <tbody>
                        <tr><td>{{ $total }}</td><td>{{ $valid }}</td><td>{{ $errorsByRow->count() }}</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @if ($errorsByRow->isNotEmpty())
        <div class="card mb-3">
            <div class="card-body">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                    <h2 class="h5 mb-0">Row errors</h2>
                    <a href="{{ route('proprietor.tenants.import.errors', $token) }}" class="btn btn-outline-secondary btn-sm">Download error report</a>
                </div>
                @include('proprietor.tenants._import-errors', ['errors' => $errorsByRow->flatten(1)])
            </div>
        </div>
    @endif

    <div class="d-flex flex-wrap gap-2">
        <form method="POST" action="{{ route('proprietor.tenants.import.confirm', $token) }}">
            @csrf
            <button type="submit" class="btn btn-primary" @disabled($valid === 0)>Confirm import ({{ $valid }})</button>
        </form>
        <form method="POST" action="{{ route('proprietor.tenants.import.cancel', $token) }}">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-outline-secondary">Cancel</button>
        </form>
    </div>
@endsection
