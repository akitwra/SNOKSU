<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function login()
    {
        return view('auth.login');
    }

    public function register()
    {
        return view('auth.register');
    }

    public function authenticate(Request $r)
    {
        $data = $r->validate(['email' => 'required|email', 'password' => 'required|string']);
        if (! Auth::attempt($data, $r->boolean('remember'))) {
            return back()->withErrors(['email' => 'Проверьте почту и пароль.'])->onlyInput('email');
        }$r->session()->regenerate();

        return redirect()->intended('/dashboard');
    }

    public function store(Request $r)
    {
        $data = $r->validate(['name' => 'required|string|max:120', 'email' => 'required|email|max:190|unique:users', 'password' => ['required', 'confirmed', Password::min(10)->letters()->numbers()], 'consent' => 'accepted']);
        $u = User::create($data);
        Auth::login($u);
        $r->session()->regenerate();

        return redirect('/dashboard')->with('success', 'Добро пожаловать! Заполните профиль или выберите первую задачу.');
    }

    public function logout(Request $r)
    {
        Auth::logout();
        $r->session()->invalidate();
        $r->session()->regenerateToken();

        return redirect('/');
    }
}
