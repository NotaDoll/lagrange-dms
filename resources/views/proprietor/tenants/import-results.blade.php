@extends('layouts.proprietor')

@section('title', 'Import Results')

@section('content')
    <h1 class="mb-4">Import Results</h1>

    <div class="alert alert-warning">
        <strong>Temporary passwords are shown only once, below.</strong> Copy them now — reloading this page will lose them.
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <h5 class="card-title text-success">Created ({{ count($created) }})</h5>
            @if (count($created))
                <table class="table table-sm">
                    <thead>
                        <tr><th>Row</th><th>Name</th><th>Email</th><th>Temp Password</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($created as $row)
                            <tr>
                                <td>{{ $row['row'] }}</td>
                                <td>{{ $row['name'] }}</td>
                                <td>{{ $row['email'] }}</td>
                                <td><code class="text-uppercase">{{ $row['password'] }}</code></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <p class="text-muted">No tenants were created.</p>
            @endif
        </div>
    </div>

    @if (count($skipped) || count($failures))
        <div class="card border-warning">
            <div class="card-body">
                <h5 class="card-title text-warning">Skipped ({{ count($skipped) + count($failures) }})</h5>
                <table class="table table-sm">
                    <thead>
                        <tr><th>Row</th><th>Reason</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($skipped as $row)
                            <tr><td>{{ $row['row'] }}</td><td>{{ $row['reason'] }}</td></tr>
                        @endforeach
                        @foreach ($failures as $row)
                            <tr><td>{{ $row['row'] }}</td><td>{{ $row['attribute'] }}: {{ $row['reason'] }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <a href="{{ route('proprietor.tenants.index') }}" class="btn btn-primary mt-4">Back to Tenants</a>
@endsection