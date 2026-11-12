@extends('layouts.proprietor')

@section('title', 'Announcements / FAQs')

@section('content')
    <nav class="d-flex flex-wrap gap-2 mb-4" aria-label="Announcements and FAQs">
        @foreach (['announcements' => 'Announcements', 'faqs' => 'FAQs'] as $value => $label)
            <a href="{{ route('proprietor.announcements-faqs.index', ['tab' => $value]) }}"
                @class(['btn', 'btn-primary' => $tab === $value, 'btn-outline-primary' => $tab !== $value])
                @if ($tab === $value) aria-current="page" @endif>{{ $label }}</a>
        @endforeach
    </nav>

    @if ($tab === 'faqs')
        @include('proprietor.faqs.index')
    @else
        @include('proprietor.announcements.index')
    @endif
@endsection
