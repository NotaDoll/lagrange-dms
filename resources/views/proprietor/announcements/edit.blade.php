@extends('layouts.proprietor')

@section('title', 'Edit Announcement')

@section('content')
    <x-breadcrumbs :items="['Announcements & FAQs' => route('proprietor.announcements-faqs.index'), 'Edit announcement' => null]" />
    <h1 class="mb-4">Edit Announcement</h1>

    <form action="{{ route('proprietor.announcements.update', $announcement) }}" method="POST">
        @csrf
        @method('PUT')
        <div class="mb-3">
            <label for="title" class="form-label">Title</label>
            <input id="title" name="title" type="text" class="form-control @error('title') is-invalid @enderror"
                   value="{{ old('title', $announcement->title) }}" required>
            @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="mb-3">
            <label for="body" class="form-label">Body</label>
            <textarea id="body" name="body" rows="6" class="form-control @error('body') is-invalid @enderror" required>{{ old('body', $announcement->body) }}</textarea>
            @error('body')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg" aria-hidden="true"></i> Save Changes</button>
        <a href="{{ route('proprietor.announcements-faqs.index', ['tab' => 'announcements']) }}" class="btn btn-outline-secondary"><i class="bi bi-x-lg" aria-hidden="true"></i> Cancel</a>
    </form>
@endsection