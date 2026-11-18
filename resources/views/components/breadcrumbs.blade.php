{{--
    Breadcrumb trail, e.g. Tenants › Juan Dela Cruz › Edit.
    Usage: <x-breadcrumbs :items="['Tenants' => route('proprietor.tenants.index'), $tenant->name => null]" />
    Each key is the label; the value is its link, or null for the current page (always the last one).
--}}
@props(['items' => []])

<nav aria-label="Breadcrumb" class="mb-2">
    <ol class="breadcrumb small mb-0">
        @foreach ($items as $label => $url)
            @if ($loop->last || $url === null)
                <li class="breadcrumb-item active text-truncate" style="max-width: 22rem;" aria-current="page">{{ $label }}</li>
            @else
                <li class="breadcrumb-item"><a href="{{ $url }}">{{ $label }}</a></li>
            @endif
        @endforeach
    </ol>
</nav>
