<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class PasswordController extends Controller
{
    /**
     * Update the user's password.
     */
    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        $mustChange = $user->isTenant() && $user->must_change_password;
        $validated = $request->validateWithBag('updatePassword', [
            'current_password' => ['required', 'current_password'],
            'password' => ['required', Password::defaults(), 'confirmed', ...($mustChange ? ['different:current_password'] : [])],
        ]);

        $user->update([
            'password' => Hash::make($validated['password']),
            ...($mustChange ? ['must_change_password' => false] : []),
        ]);

        if ($mustChange) {
            return redirect()->route('tenant.dashboard')->with('status', 'password-updated');
        }

        return back()->with('status', 'password-updated');
    }
}
