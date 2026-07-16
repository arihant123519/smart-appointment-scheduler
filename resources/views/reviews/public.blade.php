@extends('layouts.auth')

@section('title', 'Leave a review')

@section('content')
  <h4 class="fw-bold mb-1">How was your visit?</h4>
  <p class="text-muted mb-4">{{ $qr->clinic->name ?? 'Tell us about your experience' }}</p>

  <form method="POST" action="{{ route('reviews.public.store', $qr->token) }}">
    @csrf

    <div class="mb-3">
      <label class="form-label">Your rating <span class="text-danger">*</span></label>
      <div id="starRating" class="fs-2 text-warning" style="cursor:pointer">
        @for ($i = 1; $i <= 5; $i++)
          <i class="fi fi-rr-star star-input" data-value="{{ $i }}" style="opacity:.35"></i>
        @endfor
      </div>
      <input type="hidden" name="rating" id="ratingInput" value="{{ old('rating') }}" required>
      @error('rating')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
    </div>

    <x-form-field name="name" label="Your name" :required="true" :value="old('name')" />
    <x-form-field name="phone" label="Phone number" :required="true" :value="old('phone')" />

    <div class="mb-3">
      <label class="form-label">Comments <span class="text-muted">(optional)</span></label>
      <textarea name="comment" class="form-control @error('comment') is-invalid @enderror" rows="4">{{ old('comment') }}</textarea>
      @error('comment')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <button class="btn btn-primary w-100">Submit review</button>
  </form>

  <script>
    (function () {
      const stars = document.querySelectorAll('#starRating .star-input');
      const input = document.getElementById('ratingInput');
      function paint(value) {
        stars.forEach(function (s) {
          s.style.opacity = Number(s.dataset.value) <= value ? '1' : '.35';
        });
      }
      stars.forEach(function (s) {
        s.addEventListener('click', function () {
          input.value = s.dataset.value;
          paint(Number(s.dataset.value));
        });
      });
      paint(Number(input.value || 0));
    })();
  </script>
@endsection
