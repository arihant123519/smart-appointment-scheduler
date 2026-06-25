@extends('layouts.auth')

@section('title', 'Sign In')

@section('content')
  <div class="text-center mb-4">
    <h5 class="mb-1">Welcome back</h5>
    <p class="text-muted">Sign in to access your dashboard.</p>
  </div>

  <form method="POST" action="{{ route('login') }}">
    @csrf
    <div class="mb-4">
      <label class="form-label" for="email">Email Address</label>
      <input type="email" name="email" class="form-control" id="email" value="{{ old('email') }}" placeholder="you@example.com" required autofocus>
    </div>
    <div class="mb-4">
      <label class="form-label" for="password">Password</label>
      <input type="password" name="password" class="form-control" id="password" placeholder="********" required>
    </div>
    <div class="mb-4 d-flex justify-content-between">
      <div class="form-check mb-0">
        <input class="form-check-input" type="checkbox" name="remember" id="remember">
        <label class="form-check-label" for="remember">Remember Me</label>
      </div>
    </div>
    <div class="mb-3">
      <button type="submit" class="btn btn-primary w-100">Login</button>
    </div>
    <p class="mb-0 text-center">Don't have an account? <a href="{{ route('register') }}">Sign Up</a></p>
  </form>

  <div class="alert alert-light border mt-4 mb-0 small">
    <strong>Demo logins</strong> (password: <code>password</code>)<br>
    Admin: <code>admin@scheduler.test</code><br>
    Front Desk: <code>frontdesk@scheduler.test</code><br>
    Provider: <code>sarah.chen@scheduler.test</code><br>
    Patient: <code>patient1@scheduler.test</code>
  </div>
@endsection
