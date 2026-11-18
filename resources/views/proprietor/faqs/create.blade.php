@extends('layouts.proprietor')

@section('title', 'New FAQ')

@section('content')
    <x-breadcrumbs :items="['Announcements & FAQs' => route('proprietor.announcements-faqs.index', ['tab' => 'faqs']), 'New FAQ' => null]" />
    <h1 class="mb-4">New FAQ</h1>

    <form action="{{ route('proprietor.faqs.store') }}" method="POST">
        @csrf
        <div class="mb-3">
            <label for="question" class="form-label">Question</label>
            <input id="question" name="question" type="text" class="form-control @error('question') is-invalid @enderror"
                   value="{{ old('question') }}" required>
            @error('question')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="mb-3">
            <label for="answer" class="form-label">Answer</label>
            <textarea id="answer" name="answer" rows="6" class="form-control @error('answer') is-invalid @enderror" required>{{ old('answer') }}</textarea>
            @error('answer')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg" aria-hidden="true"></i> Save FAQ</button>
        <a href="{{ route('proprietor.announcements-faqs.index', ['tab' => 'faqs']) }}" class="btn btn-outline-secondary"><i class="bi bi-x-lg" aria-hidden="true"></i> Cancel</a>
    </form>
@endsection