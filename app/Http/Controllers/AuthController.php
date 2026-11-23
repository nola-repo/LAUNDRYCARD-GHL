<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $creds = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string'],
        ]);

        // Only administrators may sign in to this application at all.
        if (Auth::attempt($creds + ['is_admin' => true], false)) {
            $request->session()->regenerate();

            return redirect()->intended(route('admin.overview'));
        }

        return back()->withErrors(['email' => 'These credentials do not match an administrator account.'])->onlyInput('email');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
