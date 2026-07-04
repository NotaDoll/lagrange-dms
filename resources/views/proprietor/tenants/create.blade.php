@extends('layouts.proprietor')

@section('title', 'Add Tenant')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0">Add Tenant</h1>
        <a href="{{ route('proprietor.tenants.index') }}" class="btn btn-outline-secondary">Back</a>
    </div>

    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('proprietor.tenants.store') }}">
                @include('proprietor.tenants._form', ['submitLabel' => 'Create Tenant'])
            </form>
        </div>
    </div>
@endsection
