@extends('layouts.proprietor')

@section('title', 'SMS Configuration')

@section('content')
    <h1 class="h3 mb-3">SMS Configuration</h1>

    <div class="card" style="max-width: 560px;">
        <div class="card-body">
            <form method="POST" action="{{ route('proprietor.settings.sms.update') }}">
                @csrf
                @method('PUT')

                <div class="mb-3">
                    <label for="api_key" class="form-label">Semaphore API Key</label>
                    <input type="password" class="form-control" id="api_key" name="api_key"
                           placeholder="{{ $hasApiKey ? '•••••••••••••• (saved — leave blank to keep)' : 'Enter API key' }}">
                    @error('api_key') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                </div>

                <div class="mb-3">
                    <label for="sender_name" class="form-label">Sender Name</label>
                    <input type="text" class="form-control" id="sender_name" name="sender_name" value="{{ old('sender_name', $senderName) }}">
                </div>

                <button type="submit" class="btn btn-primary">Save</button>
            </form>
        </div>
    </div>
@endsection