@extends('layouts.auth')

@section('title', 'Sign In')

@section('content')
  <div class="mb-4">
    <h1 class="sas-auth-title mb-2">Welcome back</h1>
    <p class="text-muted mb-0">Sign in to access your dashboard.</p>
  </div>

  <form method="POST" action="{{ route('login') }}">
    @csrf
    <div class="mb-4">
      <x-outline-field name="email" type="email" label="Email Address" :value="old('email')" placeholder="you@example.com" required autofocus icon="fi-rr-envelope" />
    </div>
    <div class="mb-4">
      <x-outline-field name="password" type="password" label="Password" placeholder="********" required />
    </div>
    <div class="mb-4 d-flex justify-content-between">
      <div class="form-check mb-0">
        <input class="form-check-input" type="checkbox" name="remember" id="remember">
        <label class="form-check-label" for="remember">Remember Me</label>
      </div>
    </div>
    <div class="mb-3">
      <button type="submit" class="btn btn-primary btn-lg w-100 fw-semibold">Sign In</button>
    </div>
    <p class="mb-0 text-center">Don't have an account? <a href="{{ route('register') }}" class="fw-semibold">Sign Up</a></p>
  </form>

  <div class="sas-auth-demo mt-4">
    <div class="sas-auth-demo__head">
      <i class="fi fi-rr-key" aria-hidden="true"></i>
      <span>Demo logins <span class="text-muted fw-normal">(password: <code>password</code>)</span></span>
    </div>
    @foreach ([
      ['label' => 'Admin', 'email' => 'admin@scheduler.test', 'icon' => 'fi-rr-shield-check', 'bg' => 'bg-primary-subtle', 'fg' => 'text-primary'],
      ['label' => 'Front Desk', 'email' => 'frontdesk@scheduler.test', 'icon' => 'fi-rr-user-headset', 'bg' => 'bg-success-subtle', 'fg' => 'text-success'],
      ['label' => 'Provider', 'email' => 'sarah.chen@scheduler.test', 'icon' => 'fi-rr-user-md', 'bg' => 'bg-accent-subtle', 'fg' => 'text-accent'],
      ['label' => 'Patient', 'email' => 'patient1@scheduler.test', 'icon' => 'fi-rr-user', 'bg' => 'bg-warning-subtle', 'fg' => 'text-warning'],
    ] as $demo)
      <button type="button" class="sas-auth-demo__row" data-sas-fill-demo="{{ $demo['email'] }}">
        <span class="sas-icon-tile {{ $demo['bg'] }} {{ $demo['fg'] }}"><i class="fi {{ $demo['icon'] }}"></i></span>
        <span class="sas-auth-demo__label">{{ $demo['label'] }}</span>
        <code class="sas-auth-demo__email">{{ $demo['email'] }}</code>
      </button>
    @endforeach
  </div>
@endsection

@push('styles')
  <style>
    .sas-auth-title { font-size: 1.75rem; font-weight: 800; letter-spacing: -.02em; color: var(--sas-gray-900); }

    .sas-auth-divider { display: flex; align-items: center; gap: .75rem; margin: 1.1rem 0; color: var(--sas-gray-400); font-size: var(--sas-fs-xs); }
    .sas-auth-divider::before, .sas-auth-divider::after { content: ''; flex: 1; height: 1px; background: var(--sas-gray-100); }

    .sas-auth-demo { background: var(--sas-primary-25, #f5f9ff); border: 1px solid var(--sas-primary-100); border-radius: var(--sas-radius-lg); padding: 1rem 1.1rem; }
    .sas-auth-demo__head { display: flex; align-items: center; gap: .5rem; font-weight: 700; font-size: var(--sas-fs-sm); color: var(--sas-primary-800); margin-bottom: .75rem; }
    .sas-auth-demo__row {
      display: flex; align-items: center; gap: .65rem; width: 100%; border: 0; background: transparent;
      padding: .4rem .3rem; border-radius: var(--sas-radius-md); text-align: left; cursor: pointer;
      transition: background-color .15s var(--sas-ease);
    }
    .sas-auth-demo__row:hover { background: rgba(255,255,255,.7); }
    .sas-auth-demo__row .sas-icon-tile { width: 30px; height: 30px; font-size: .85rem; }
    .sas-auth-demo__label { font-size: var(--sas-fs-sm); font-weight: 600; color: var(--sas-gray-800); flex-shrink: 0; width: 82px; }
    .sas-auth-demo__email { font-size: .74rem; color: var(--sas-gray-500); background: transparent; margin-left: auto; }
  </style>
@endpush

@push('scripts')
  <script>
    // Convenience only — clicking a demo row fills the email field so you
    // don't have to type it, it never submits or auto-logs-in on its own.
    document.querySelectorAll('[data-sas-fill-demo]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        const email = document.getElementById('email');
        const password = document.getElementById('password');
        if (email) { email.value = btn.dataset.sasFillDemo; }
        if (password) { password.value = 'password'; password.focus(); }
      });
    });
  </script>
@endpush
