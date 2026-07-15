@extends('layouts.app')

@section('title', 'Leave Feedback')

@section('content')
  <div class="row justify-content-center"><div class="col-xl-6">
    <x-card>
      <p class="text-muted">How was your visit with {{ $appointment->provider->name }} on {{ $appointment->start_at->format('M j, Y') }}?</p>
      <form method="POST" action="{{ route('reviews.store', $appointment) }}">
        @csrf
        <div class="mb-3">
          <label class="form-label">Rating</label>
          <select name="rating" class="form-select" required>
            <option value="">Select…</option>
            @for ($i = 5; $i >= 1; $i--)<option value="{{ $i }}">{{ str_repeat('★', $i) }} ({{ $i }})</option>@endfor
          </select>
        </div>
        <div class="mb-3">
          <label class="form-label">Comments</label>
          <textarea name="comment" class="form-control" rows="3"></textarea>
        </div>
        <button class="btn btn-primary">Submit feedback</button>
      </form>
    </x-card>
  </div></div>
@endsection
