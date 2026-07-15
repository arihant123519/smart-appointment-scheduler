@extends('layouts.auth')

@section('title', 'Log in with phone')

@section('content')
  <div class="text-center mb-4">
    <h5 class="mb-1">Log in with your phone</h5>
    <p class="text-muted">We'll send a one-time code to your phone over WhatsApp — no password needed.</p>
  </div>

  <form method="POST" action="{{ route('login.phone.send') }}">
    @csrf
    <div class="mb-4">
      <label class="form-label" for="phone">Phone number</label>
      <input type="tel" name="phone" class="form-control @error('phone') is-invalid @enderror" id="phone" value="{{ old('phone') }}" placeholder="+1 555 123 4567" required autofocus>
      @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
      <div class="form-text">Include your country code, e.g. +1 555 123 4567.</div>
    </div>
    <div class="mb-3">
      <button type="submit" class="btn btn-primary w-100">Send code</button>
    </div>
    <p class="mb-0 text-center"><a href="{{ route('login') }}">Log in with email and password instead</a></p>
  </form>
@endsection
