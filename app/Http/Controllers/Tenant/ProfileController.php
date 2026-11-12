<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ProfileController extends Controller
{
    public function show(Request $request): Response
    {
        $user = $request->user();
        $tenant = $user->tenant()->with(['currentAssignment.bed.room', 'monthModes' => fn ($query) => $query
            ->where('mode', \App\Models\TenantMonthMode::STORAGE)->orderByDesc('month')])->first();
        $tenant?->setRelation('user', $user);

        return response()->view('tenant.profile', compact('tenant'))
            ->header('Cache-Control', 'private, no-store');
    }
}
