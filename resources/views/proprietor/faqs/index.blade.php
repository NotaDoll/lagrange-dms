<div class="d-flex justify-content-between align-items-center mb-4">
    <h1>FAQs</h1>
    <a href="{{ route('proprietor.faqs.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg" aria-hidden="true"></i> New FAQ</a>
</div>

<table class="table table-hover align-middle">
    <thead>
        <tr>
            <th>Question</th>
            <th class="text-end">Actions</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($faqs as $faq)
            <tr>
                <td>{{ $faq->question }}</td>
                <td class="text-end">
                    <a href="{{ route('proprietor.faqs.edit', $faq) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil" aria-hidden="true"></i> Edit</a>
                    <form action="{{ route('proprietor.faqs.destroy', $faq) }}" method="POST" class="d-inline"
                          onsubmit="return confirm('Delete this FAQ?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash" aria-hidden="true"></i> Delete</button>
                    </form>
                </td>
            </tr>
        @empty
            <tr><td colspan="2" class="text-muted">No FAQs yet.</td></tr>
        @endforelse
    </tbody>
</table>

{{ $faqs->links() }}
