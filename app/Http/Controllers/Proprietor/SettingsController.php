<?php

namespace App\Http\Controllers\Proprietor;

use App\Http\Controllers\Controller;
use App\Services\SettingsService;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function __construct(private readonly SettingsService $settings)
    {
    }

    public function edit()
    {
        $general = $this->settings->getGroup('general');

        return view('proprietor.settings.index', compact('general'));
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'dormitory_name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'contact_number' => ['nullable', 'string', 'max:20'],
        ]);

        foreach ($validated as $key => $value) {
            $this->settings->set('general', $key, $value);
        }

        return redirect()
            ->route('proprietor.settings.edit')
            ->with('success', 'Settings updated.');
    }
}