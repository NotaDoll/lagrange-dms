@extends('layouts.proprietor')

@section('title', 'New Announcement')

@section('content')
    <x-breadcrumbs :items="['Announcements & FAQs' => route('proprietor.announcements-faqs.index'), 'New announcement' => null]" />
    <h1 class="mb-4">New Announcement</h1>

    <form action="{{ route('proprietor.announcements.store') }}" method="POST">
        @csrf
        <div class="mb-3">
            <label for="title" class="form-label">Title</label>
            <input id="title" name="title" type="text" class="form-control @error('title') is-invalid @enderror"
                   value="{{ old('title') }}" required>
            @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="mb-3">
            <label for="body" class="form-label">Body</label>
            <textarea id="body" name="body" rows="6" class="form-control @error('body') is-invalid @enderror" required>{{ old('body') }}</textarea>
            @error('body')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <button type="submit" class="btn btn-primary"><i class="bi bi-send" aria-hidden="true"></i> Post Announcement</button>
        <a href="{{ route('proprietor.announcements-faqs.index', ['tab' => 'announcements']) }}" class="btn btn-outline-secondary"><i class="bi bi-x-lg" aria-hidden="true"></i> Cancel</a>
    </form>
@endsection