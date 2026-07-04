@extends('layouts.proprietor')

@section('title', 'Edit FAQ')

@section('content')
    <h1 class="mb-4">Edit FAQ</h1>

    <form action="{{ route('proprietor.faqs.update', $faq) }}" method="POST">
        @csrf
        @method('PUT')
        <div class="mb-3">
            <label for="question" class="form-label">Question</label>
            <input id="question" name="question" type="text" class="form-control @error('question') is-invalid @enderror"
                   value="{{ old('question', $faq->question) }}" required>
            @error('question')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="mb-3">
            <label for="answer" class="form-label">Answer</label>
            <textarea id="answer" name="answer" rows="6" class="form-control @error('answer') is-invalid @enderror" required>{{ old('answer', $faq->answer) }}</textarea>
            @error('answer')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <button type="submit" class="btn btn-primary">Save Changes</button>
        <a href="{{ route('proprietor.faqs.index') }}" class="btn btn-outline-secondary">Cancel</a>
    </form>
@endsection