@extends('layouts.app')

@section('title', $provider->name)

@section('page_actions')
  <a href="{{ route('availability.edit', $provider) }}" class="btn btn-light"><i class="fi fi-rr-clock me-1"></i> Availability</a>
  <a href="{{ route('providers.edit', $provider) }}" class="btn btn-light"><i class="fi fi-rr-pencil me-1"></i> Edit</a>
@endsection

@section('content')
  <div class="row g-3">
    <div class="col-xl-4">
      <div class="card">
        <div class="card-body text-center">
          <img src="{{ $provider->user->avatar_url }}" class="rounded-circle mb-3" width="84" height="84" alt="">
          <h5 class="mb-0">{{ $provider->name }}</h5>
          <p class="text-muted">{{ $provider->specialty }} · {{ $provider->credentials }}</p>
          <p class="small">{{ $provider->bio }}</p>
        </div>
      </div>
    </div>
    <div class="col-xl-8">
      <div class="card mb-3">
        <div class="card-header"><h6 class="mb-0">Services</h6></div>
        <div class="card-body">
          @forelse ($provider->services as $s)
            <span class="badge bg-light text-dark me-1">{{ $s->name }} ({{ $s->duration }} min)</span>
          @empty
            <span class="text-muted">No services assigned.</span>
          @endforelse
        </div>
      </div>
      <div class="card">
        <div class="card-header"><h6 class="mb-0">Working hours</h6></div>
        <div class="card-body p-0">
          <table class="table mb-0">
            <thead class="table-light"><tr><th>Day</th><th>From</th><th>To</th></tr></thead>
            <tbody>
              @php $days = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday']; @endphp
              @forelse ($provider->availabilities->sortBy('day_of_week') as $av)
                <tr><td>{{ $days[$av->day_of_week] }}</td><td>{{ \Carbon\Carbon::parse($av->start_time)->format('g:i A') }}</td><td>{{ \Carbon\Carbon::parse($av->end_time)->format('g:i A') }}</td></tr>
              @empty
                <tr><td colspan="3" class="text-center text-muted py-3">No availability set.</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
@endsection
