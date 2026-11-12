@extends('layouts.proprietor')

@section('title', 'Tenant Import Results')

@section('content')
    <h1 class="h3 mb-4">Tenant Import Results</h1>

    <div class="card mb-3">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm mb-0" aria-label="Import results">
                    <thead>
                        <tr>
                            <th scope="col">Imported</th>
                            <th scope="col">Skipped</th>
                            <th scope="col">Logins created</th>
                            <th scope="col">Tenants without a new login</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>{{ $result['imported'] }}</td>
                            <td>{{ $result['total'] - $result['imported'] }}</td>
                            <td>{{ $result['logins_created'] }}</td>
                            <td>{{ count($result['without_login']) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @if ($result['without_login'] !== [])
        <div class="card mb-3">
            <div class="card-body">
                <h2 class="h5">Tenants without a new login</h2>
                <p class="small text-muted">An account already used each of these email addresses.</p>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead>
                            <tr><th scope="col">Row</th><th scope="col">Tenant</th><th scope="col">Email</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($result['without_login'] as $row)
                                <tr><th scope="row">{{ $row['row'] }}</th><td>{{ $row['name'] }}</td><td>{{ $row['email'] }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif

    @if ($result['errors'] !== [])
        <div class="card mb-3">
            <div class="card-body">
                <h2 class="h5">Skipped rows</h2>
                @include('proprietor.tenants._import-errors', ['errors' => $result['errors']])
            </div>
        </div>
    @endif

    <a href="{{ route('proprietor.tenants.index') }}" class="btn btn-primary">Back to Tenants</a>
    <a href="{{ route('proprietor.tenants.import.create') }}" class="btn btn-outline-secondary">Import another file</a>
@endsection
