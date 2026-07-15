@extends('layouts.app')

@section('title', $provider->name)

@section('page_actions')
  <a href="{{ route('availability.edit', $provider) }}" class="btn btn-light"><i class="fi fi-rr-clock me-1"></i> Availability</a>
  <a href="{{ route('providers.edit', $provider) }}" class="btn btn-light"><i class="fi fi-rr-pencil me-1"></i> Edit</a>
@endsection

@section('content')
  <div class="row g-3">
    <div class="col-xl-4">
      <x-card>
        <div class="text-center">
          <img src="{{ $provider->user->avatar_url }}" class="rounded-circle mb-3" width="84" height="84" alt="">
          <h5 class="mb-0">{{ $provider->name }}</h5>
          <p class="text-muted">{{ $provider->specialty }} · {{ $provider->credentials }}</p>
          <p class="small">{{ $provider->bio }}</p>
        </div>
      </x-card>
    </div>
    <div class="col-xl-8">
      <x-card title="Services" class="mb-3">
        @forelse ($provider->services as $s)
          <span class="badge badge-light-secondary me-1">{{ $s->name }} ({{ $s->duration }} min)</span>
        @empty
          <span class="text-muted">No services assigned.</span>
        @endforelse
      </x-card>
      <x-card title="Working hours" bodyClass="p-0">
        <div class="table-responsive">
          <table class="table mb-0">
            <thead class="table-light"><tr><th>Day</th><th>From</th><th>To</th></tr></thead>
            <tbody>
              @php $days = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday']; @endphp
              @forelse ($provider->availabilities->sortBy('day_of_week') as $av)
                <tr><td>{{ $days[$av->day_of_week] }}</td><td>{{ \Carbon\Carbon::parse($av->start_time)->format('g:i A') }}</td><td>{{ \Carbon\Carbon::parse($av->end_time)->format('g:i A') }}</td></tr>
              @empty
                <x-empty-state colspan="3" icon="fi-rr-clock" title="No availability set" />
              @endforelse
            </tbody>
          </table>
        </div>
      </x-card>
    </div>
  </div>
@endsection
