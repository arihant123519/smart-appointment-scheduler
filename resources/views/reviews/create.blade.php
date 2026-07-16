@extends('layouts.app')

@section('title', 'Leave Feedback')

@section('content')
  <div class="row justify-content-center">
    <div class="col-xl-6">
      <x-card>
        <div class="d-flex align-items-center gap-3 mb-4">
          <div class="sas-avatar sas-avatar-lg">{{ strtoupper(substr($appointment->provider->name ?? '?', 0, 1)) }}</div>
          <div>
            <div class="fw-bold">How was your visit with {{ $appointment->provider->name }}?</div>
            <div class="text-muted small">{{ $appointment->start_at->format('M j, Y \a\t g:i A') }}</div>
          </div>
        </div>

        <form method="POST" action="{{ route('reviews.store', $appointment) }}">
          @csrf

          <div class="mb-4 text-center">
            <label class="form-label d-block">Your rating <span class="text-danger">*</span></label>
            <x-star-rating name="rating" :required="true" />
          </div>

          <div class="mb-3">
            <label class="form-label">Comments <span class="text-muted fw-normal">(optional)</span></label>
            <textarea name="comment" class="form-control" rows="3" placeholder="Tell us more about your experience..."></textarea>
          </div>

          <button class="btn btn-primary w-100">Submit feedback</button>
        </form>
      </x-card>
    </div>
  </div>
@endsection
