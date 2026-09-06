@extends('layouts.proprietor')

@section('title', 'Add Utility Bill')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0">Add Utility Bill</h1>
        <a href="{{ route('proprietor.utility-bills.index') }}" class="btn btn-outline-secondary">Back</a>
    </div>

    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('proprietor.utility-bills.store') }}">
                @include('proprietor.utility-bills._form', ['submitLabel' => 'Create Utility Bill'])
            </form>
        </div>
    </div>
@endsection