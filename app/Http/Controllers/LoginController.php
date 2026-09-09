<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;

class LoginController extends Controller
{
    public function show(Request $request)
    {
        if (Auth::check()) {
            return redirect()->intended('dashboard');
        }

        $response = response()->view('auth.login', [
            'databaseImportStatus' => $request->cookie('database_import_status'),
        ]);

        if ($request->hasCookie('database_import_status')) {
            $response->withCookie(Cookie::forget('database_import_status'));
        }

        return $response;
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (Auth::attempt($credentials)) {
            $request->session()->regenerate();

            return redirect()->intended('dashboard');
        }

        return back()->withErrors([
            'email' => 'The provided credentials do not match our records.',
        ])->onlyInput('email');
    }

    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
