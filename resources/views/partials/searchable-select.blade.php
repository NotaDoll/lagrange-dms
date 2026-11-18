{{-- Turns every <select data-searchable> on the page into a type-to-search dropdown.
     If the library fails to load (e.g. offline), the plain <select> keeps working. --}}
@once
    @push('scripts')
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tom-select@2.4.3/dist/css/tom-select.bootstrap5.min.css">
        <style>
            .ts-wrapper.form-select { padding: 0; }
            .ts-wrapper .ts-control { min-height: calc(1.5em + .75rem + 2px); }
            .ts-wrapper.is-invalid .ts-control { border-color: var(--bs-form-invalid-border-color, #dc3545); }
            body > .ts-dropdown { z-index: 1070; } /* above Bootstrap modals */
        </style>
        <script src="https://cdn.jsdelivr.net/npm/tom-select@2.4.3/dist/js/tom-select.complete.min.js"></script>
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                if (typeof TomSelect === 'undefined') return;
                document.querySelectorAll('select[data-searchable]').forEach((select) => {
                    const ts = new TomSelect(select, {
                        // Optional filters ("All tenants") keep the empty choice; required fields show it as a placeholder.
                        allowEmptyOption: !select.required,
                        maxOptions: null,
                        placeholder: select.dataset.placeholder || 'Type to search…',
                        searchField: ['text'],
                        // Inside a modal, render the list on <body> so the modal doesn't clip it.
                        dropdownParent: select.closest('.modal') ? 'body' : null,
                        render: { no_results: () => '<div class="no-results">No matching tenant</div>' },
                    });
                    if (select.classList.contains('is-invalid')) ts.wrapper.classList.add('is-invalid');
                    // Let other scripts react to the change the same way as a native select.
                    ts.on('change', () => select.dispatchEvent(new Event('tom-change')));
                });
            });
        </script>
    @endpush
@endonce
