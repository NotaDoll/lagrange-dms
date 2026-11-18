@extends('layouts.proprietor')

@section('title', 'Edit Tenant')

@section('content')
    <x-breadcrumbs :items="['Tenants' => route('proprietor.tenants.index'), $tenant->name => route('proprietor.tenants.show', $tenant), 'Edit' => null]" />
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0">Edit Tenant</h1>
        <a href="{{ route('proprietor.tenants.show', $tenant) }}" class="btn btn-outline-secondary">Back</a>
    </div>

    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('proprietor.tenants.update', $tenant) }}">
                @method('PUT')
                @include('proprietor.tenants._form', ['submitLabel' => 'Update Tenant'])
            </form>
        </div>
    </div>
@endsection
