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

@push('scripts')
<script>
    (() => {
        const type = document.getElementById('type');
        const field = document.getElementById('new-type-field');
        const newType = document.getElementById('new_type');

        function syncNewType() {
            const addingType = type.value === type.dataset.addType;
            field.hidden = !addingType;
            newType.disabled = !addingType;
            newType.required = addingType;
        }

        type.addEventListener('change', () => {
            syncNewType();
            if (!newType.disabled) {
                newType.focus();
            }
        });
        syncNewType();
    })();
</script>
@endpush
