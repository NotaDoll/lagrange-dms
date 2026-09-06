<?php

namespace App\Http\Controllers\Proprietor;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateAiSettingsRequest;
use App\Services\SettingsService;

class AiSettingsController extends Controller
{
    public function __construct(private readonly SettingsService $settings)
    {
    }

    public function edit()
    {
        $hasApiKey = filled($this->settings->get('ai', 'api_key'));
        $model = $this->settings->get('ai', 'model', config('services.groq.model'));

        return view('proprietor.settings.ai', compact('hasApiKey', 'model'));
    }

    public function update(UpdateAiSettingsRequest $request)
    {
        if ($request->filled('api_key')) {
            $this->settings->set('ai', 'api_key', $request->string('api_key'), secret: true);
        }

        $this->settings->set('ai', 'model', $request->input('model'));

        return redirect()
            ->route('proprietor.settings.ai.edit')
            ->with('success', 'AI configuration updated.');
    }
}