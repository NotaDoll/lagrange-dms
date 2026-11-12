<div class="table-responsive">
    <table class="table table-sm mb-0" aria-label="Import row errors">
        <thead>
            <tr><th scope="col">Row</th><th scope="col">Column</th><th scope="col">Reason</th></tr>
        </thead>
        <tbody>
            @foreach ($errors as $error)
                <tr>
                    <th scope="row">{{ $error['row'] }}</th>
                    <td>{{ $error['column'] }}</td>
                    <td>{{ $error['reason'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
