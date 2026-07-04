@extends('layouts.proprietor')

@section('title', 'Add Room')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0">Add Room</h1>
        <a href="{{ route('proprietor.rooms.index') }}" class="btn btn-outline-secondary">Back</a>
    </div>

    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('proprietor.rooms.store') }}">
                @include('proprietor.rooms._form', ['submitLabel' => 'Create Room'])
            </form>
        </div>
    </div>
@endsection
