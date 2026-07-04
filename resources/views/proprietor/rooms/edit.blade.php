@extends('layouts.proprietor')

@section('title', 'Edit Room')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0">Edit Room</h1>
        <a href="{{ route('proprietor.rooms.index') }}" class="btn btn-outline-secondary">Back</a>
    </div>

    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('proprietor.rooms.update', $room) }}">
                @method('PUT')
                @include('proprietor.rooms._form', ['submitLabel' => 'Update Room'])
            </form>
        </div>
    </div>
@endsection
