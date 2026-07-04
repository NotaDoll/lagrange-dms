@extends('layouts.tenant')

@section('title', 'FAQs')

@section('content')
    <h1 class="mb-4">FAQs</h1>

    <div class="accordion" id="faqAccordion">
        @forelse ($faqs as $faq)
            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq{{ $faq->id }}">
                        {{ $faq->question }}
                    </button>
                </h2>
                <div id="faq{{ $faq->id }}" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                    <div class="accordion-body">{{ $faq->answer }}</div>
                </div>
            </div>
        @empty
            <p class="text-muted">No FAQs yet.</p>
        @endforelse
    </div>
@endsection