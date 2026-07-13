@extends('layouts.auth')

@section('title', 'Enter your code')

@section('content')
  <div class="text-center mb-4">
    <h5 class="mb-1">Enter your code</h5>
    <p class="text-muted">We sent a 6-digit code over WhatsApp. It expires in 5 minutes.</p>
  </div>

  <form method="POST" action="{{ route('login.phone.verify.submit') }}">
    @csrf
    <div class="mb-4">
      <label class="form-label" for="code">Verification code</label>
      <input type="text" inputmode="numeric" pattern="[0-9]*" maxlength="6" name="code" class="form-control text-center" id="code"
             placeholder="000000" autocomplete="one-time-code" required autofocus>
    </div>
    <div class="mb-3">
      <button type="submit" class="btn btn-primary w-100">Verify &amp; log in</button>
    </div>
    <p class="mb-0 text-center"><a href="{{ route('login.phone') }}">Use a different phone number</a></p>
  </form>
@endsection
