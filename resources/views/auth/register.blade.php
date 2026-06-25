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
      <label class="form-label" for="name">Full Name</label>
      <input type="text" name="name" class="form-control" id="name" value="{{ old('name') }}" required autofocus>
    </div>
    <div class="mb-3">
      <label class="form-label" for="email">Email Address</label>
      <input type="email" name="email" class="form-control" id="email" value="{{ old('email') }}" required>
    </div>
    <div class="mb-3">
      <label class="form-label" for="phone">Phone (optional)</label>
      <input type="text" name="phone" class="form-control" id="phone" value="{{ old('phone') }}">
    </div>
    <div class="mb-3">
      <label class="form-label" for="password">Password</label>
      <input type="password" name="password" class="form-control" id="password" required>
    </div>
    <div class="mb-4">
      <label class="form-label" for="password_confirmation">Confirm Password</label>
      <input type="password" name="password_confirmation" class="form-control" id="password_confirmation" required>
    </div>
    <div class="mb-3">
      <button type="submit" class="btn btn-primary w-100">Create Account</button>
    </div>
    <p class="mb-0 text-center">Already have an account? <a href="{{ route('login') }}">Sign In</a></p>
  </form>
@endsection
