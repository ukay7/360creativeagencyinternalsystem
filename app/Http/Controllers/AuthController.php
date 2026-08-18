<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Auth;
use Illuminate\Http\Request;

final class AuthController extends Controller
{
    public function __construct(private readonly Auth $auth) {}

    public function create(): mixed
    {
        if ($this->auth->check()) { return redirect(agency_url('dashboard')); }
        $flashes = [];
        foreach (['success', 'danger'] as $type) {
            if (session()->has($type)) { $flashes[] = ['type'=>$type, 'message'=>(string) session($type)]; }
        }
        return view('auth.login', compact('flashes'));
    }

    public function store(Request $request): mixed
    {
        $credentials = $request->validate(['email'=>['required','email'],'password'=>['required','string']]);
        if (! $this->auth->attempt($credentials['email'], $credentials['password'])) {
            return back()->with('danger', 'The email or password is incorrect.')->onlyInput('email');
        }
        return redirect()->intended(agency_url('dashboard'))->with('success', 'Welcome back. Your agency command center is ready.');
    }
}
