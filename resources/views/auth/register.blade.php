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
      <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" id="name" value="{{ old('name') }}" required autofocus>
      @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="mb-3">
      <label class="form-label" for="email">Email Address</label>
      <input type="email" name="email" class="form-control @error('email') is-invalid @enderror" id="email" value="{{ old('email') }}" required>
      @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="mb-3">
      <label class="form-label" for="phone">Phone (optional)</label>
      <input type="text" name="phone" class="form-control @error('phone') is-invalid @enderror" id="phone" value="{{ old('phone') }}">
      @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="mb-3">
      <label class="form-label" for="password">Password</label>
      <input type="password" name="password" class="form-control @error('password') is-invalid @enderror" id="password" required>
      @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
      <div class="form-text">Minimum 8 characters.</div>
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
