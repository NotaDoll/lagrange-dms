<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;

class ProfileController extends Controller
{
    public function show()
    {
        $tenant = auth()->user()
            ->tenant()
            ->with(['currentAssignment.bed.room'])
            ->firstOrFail();

        return view('tenant.profile', compact('tenant'));
    }
}
