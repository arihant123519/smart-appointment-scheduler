@extends('layouts.auth')

@section('title', 'Create Account')

@section('content')
  <div class="text-center mb-4">
    <h5 class="mb-1">Create your account</h5>
    <p class="text-muted">Register as a patient to book appointments.</p>
  </div>

  <form method="POST" action="{{ route('register') }}">
    @csrf
    <div class="mb-3">
      <x-outline-field name="name" label="Full Name" :value="old('name')" required autofocus />
    </div>
    <div class="mb-3">
      <x-outline-field name="email" type="email" label="Email Address" :value="old('email')" required />
    </div>
    <div class="mb-3">
      <x-outline-field name="phone" label="Phone (optional)" :value="old('phone')" />
    </div>
    <div class="mb-3">
      <x-outline-field name="password" type="password" label="Password" required help="Minimum 8 characters." />
    </div>
    <div class="mb-4">
      <x-outline-field name="password_confirmation" type="password" label="Confirm Password" required />
    </div>
    <div class="mb-3">
      <button type="submit" class="btn btn-primary w-100">Create Account</button>
    </div>
    <p class="mb-0 text-center">Already have an account? <a href="{{ route('login') }}">Sign In</a></p>
  </form>
@endsection
