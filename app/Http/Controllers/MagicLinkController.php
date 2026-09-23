<?php

namespace App\Http\Controllers;

use App\Mail\MagicLinkMail;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;

class MagicLinkController extends Controller
{
    public function show()
    {
        return Inertia::render('Auth/Login');
    }

    public function send(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        if (! User::where('email', $request->email)->exists()) {
            return back()->withErrors(['email' => 'No account found for that address.']);
        }

        $link = URL::temporarySignedRoute(
            'magic.verify',
            now()->addMinutes(15),
            ['email' => $request->email]
        );

        Mail::to($request->email)->send(new MagicLinkMail($link));

        return back()->with('status', 'Magic link sent — check your inbox. It expires in 15 minutes.');
    }

    public function verify(Request $request)
    {
        if (! $request->hasValidSignature()) {
            return redirect()->route('login')
                ->withErrors(['email' => 'This link has expired or is invalid. Request a new one.']);
        }

        $user = User::where('email', $request->email)->first();

        if (! $user) {
            return redirect()->route('login')
                ->withErrors(['email' => 'Invalid login attempt.']);
        }

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        return redirect()->route('dashboard');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
