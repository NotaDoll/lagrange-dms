<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Rules\TenantPassword;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class PasswordController extends Controller
{
    /**
     * Update the user's password.
     */
    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        $isTenant = $user->isTenant();
        $mustChange = $isTenant && $user->must_change_password;

        $rules = [
            'current_password' => ['required', 'current_password'],
            'password' => ['required', Password::defaults()],
        ];
        $messages = [];
        $attributes = [];

        if ($isTenant) {
            $rules['current_password'] = ['bail', 'required', 'string', 'current_password'];
            $rules['password'] = ['required', 'string', new TenantPassword];
            $rules['password_confirmation'] = ['bail', 'required', 'same:password'];
            $messages = [
                'current_password.current_password' => 'Your current password is incorrect.',
                'password.different' => 'Choose a new password that differs from your initial password.',
                'password_confirmation.required' => 'Confirm your new password.',
                'password_confirmation.same' => 'The passwords do not match.',
            ];
            $attributes['password'] = 'new password';
        } else {
            $rules['password'][] = 'confirmed';
        }

        if ($mustChange) {
            $rules['password'][] = 'different:current_password';
        }

        try {
            $validated = $request->validateWithBag('updatePassword', $rules, $messages, $attributes);
        } catch (ValidationException $exception) {
            if ($isTenant) {
                $exception->redirectTo(route('tenant.password.edit'));
            }

            throw $exception;
        }

        $user->update([
            'password' => Hash::make($validated['password']),
            ...($mustChange ? ['must_change_password' => false] : []),
        ]);

        if ($mustChange) {
            return redirect()->route('tenant.dashboard')->with('status', 'password-updated');
        }

        $redirect = $isTenant ? redirect()->route('tenant.password.edit') : back();

        return $redirect->with('status', 'password-updated');
    }
}
