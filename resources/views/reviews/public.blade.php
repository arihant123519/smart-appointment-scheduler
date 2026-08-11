@extends('layouts.auth')

@section('title', 'Leave a review')

@section('content')
  @if (session('success'))
    <div class="text-center py-2">
      <div class="sas-avatar mx-auto mb-3" style="width:64px;height:64px;font-size:1.9rem;background:var(--sas-success-subtle);color:var(--sas-success-emphasis)">
        <i class="fi fi-rr-check-circle"></i>
      </div>
      <h4 class="fw-bold mb-1">Thank you!</h4>
      <p class="text-muted mb-0">{{ session('success') }}</p>
    </div>
  @else
    <div class="text-center mb-4">
      <div class="sas-avatar mx-auto mb-3" style="width:56px;height:56px;font-size:1.5rem">
        <i class="fi fi-rr-star"></i>
      </div>
      <h4 class="fw-bold mb-1">How was your visit?</h4>
      <p class="text-muted mb-0">{{ $qr->clinic->name ?? 'Tell us about your experience' }}</p>
    </div>

    <form method="POST" action="{{ route('reviews.public.store', $qr->token) }}">
      @csrf

      <div class="mb-4 text-center">
        <label class="form-label d-block">Your rating <span class="text-danger">*</span></label>
        <x-star-rating name="rating" :value="old('rating')" :required="true" />
        @error('rating')<div class="text-danger small mt-2">{{ $message }}</div>@enderror
      </div>

      <x-form-field name="name" label="Your name" :required="true" :value="old('name')" placeholder="Jane Doe" />
      <x-form-field name="phone" label="Phone number" type="tel" :required="true" :value="old('phone')" placeholder="e.g. 9876543210" />

      <div class="mb-3">
        <x-outline-field name="comment" label="Comments (optional)" textarea rows="4" placeholder="What stood out about your visit?" :value="old('comment')" />
      </div>

      <button class="btn btn-primary w-100 btn-lg">Submit review</button>
      <p class="text-center text-muted small mt-3 mb-0">Your feedback helps {{ $qr->clinic->name ?? 'this clinic' }} improve care for every patient.</p>
    </form>
  @endif
@endsection
