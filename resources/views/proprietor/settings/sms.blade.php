@extends('layouts.proprietor')

@section('title', 'SMS Configuration')

@section('content')
    <h1 class="h3 mb-3">SMS Configuration</h1>

    @if ($usesTextBee)
        <div class="card mb-3" style="max-width: 560px;">
            <div class="card-body">
                <h2 class="h5">TextBee</h2>
                <p class="small text-muted">
                    {{ $hasApiKey ? 'API key configured on the server.' : 'Set TEXTBEE_API_KEY in the server environment.' }}
                    Messages use the phone number of the sending SIM.
                </p>

                <form method="POST" action="{{ route('proprietor.settings.sms.update') }}">
                    @csrf
                    @method('PUT')

                    <div class="mb-3">
                        <label for="device_id" class="form-label">Device ID</label>
                        <input type="text" class="form-control" id="device_id" name="device_id" value="{{ old('device_id', $deviceId) }}" aria-describedby="device-help">
                        <div id="device-help" class="form-text">Leave blank to use the default device in TextBee.</div>
                        @error('device_id') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                    </div>

                    <div class="mb-3">
                        <label for="sim_subscription_id" class="form-label">SIM subscription ID</label>
                        <input type="number" min="0" step="1" class="form-control" id="sim_subscription_id" name="sim_subscription_id" value="{{ old('sim_subscription_id', $simId) }}" aria-describedby="sim-help">
                        <div id="sim-help" class="form-text">Leave blank to use the phone's default SIM. Find the ID under SIM Cards in the TextBee app.</div>
                        @error('sim_subscription_id') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                    </div>

                    <button type="submit" class="btn btn-primary">Save</button>
                </form>
            </div>
        </div>

        <div class="card" style="max-width: 560px;">
            <div class="card-body">
                <h2 class="h5">Test SMS</h2>
                <form method="POST" action="{{ route('proprietor.settings.sms.test') }}">
                    @csrf
                    <div class="mb-3">
                        <label for="test_number" class="form-label">Test recipient number</label>
                        <input type="tel" class="form-control" id="test_number" name="test_number" value="{{ old('test_number') }}" placeholder="09XXXXXXXXX or +639XXXXXXXXX" maxlength="32" required>
                        @error('test_number') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                    </div>
                    <button type="submit" class="btn btn-primary" @disabled(!$hasApiKey)>Send test SMS</button>
                </form>
            </div>
        </div>
    @else
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
    @endif
@endsection
