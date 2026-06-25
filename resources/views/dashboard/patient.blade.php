@extends('layouts.app')

@section('title', 'My Appointments')

@section('page_actions')
  <a href="{{ route('booking.create') }}" class="btn btn-primary"><i class="fi fi-rr-plus me-1"></i> Book Appointment</a>
@endsection

@section('content')
  @php $todays = $upcoming->filter(fn ($a) => $a->start_at->isToday()); @endphp
  @if ($todays->count())
    <div class="card border-0 mb-4" style="background:linear-gradient(135deg,#5955D1,#7b78e0);color:#fff">
      <div class="card-body d-flex flex-wrap align-items-center gap-3">
        <div class="sas-stat__icon bg-white text-primary"><i class="fi fi-rr-calendar-clock"></i></div>
        <div class="flex-grow-1">
          <h5 class="mb-1 text-white">You have an appointment today</h5>
          @foreach ($todays as $a)
            <div>{{ $a->start_at->format('g:i A') }} — {{ $a->service->name ?? 'Visit' }} with {{ $a->provider->name }}</div>
          @endforeach
        </div>
        @php $first = $todays->first(); @endphp
        @if ($first->is_telehealth && $first->telehealth_link)
          <a href="{{ $first->telehealth_link }}" target="_blank" class="btn btn-light"><i class="fi fi-rr-video-camera me-1"></i> Join video visit</a>
        @endif
        <a href="{{ route('intake.edit', $first) }}" class="btn btn-outline-light">Complete intake</a>
      </div>
    </div>
  @endif

  <div class="card mb-4">
    <div class="card-body d-flex align-items-center gap-3">
      <img src="{{ $user->avatar_url }}" class="rounded-circle" width="56" height="56" alt="">
      <div>
        <h5 class="mb-0">Welcome, {{ $user->name }}</h5>
        <small class="text-muted">{{ $user->email }}</small>
      </div>
    </div>
  </div>

  <div class="card mb-4">
    <div class="card-header"><h6 class="mb-0">Upcoming appointments</h6></div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table align-middle mb-0">
          <thead class="table-light"><tr><th>Date &amp; Time</th><th>Provider</th><th>Service</th><th>Status</th><th></th></tr></thead>
          <tbody>
            @forelse ($upcoming as $a)
              <tr>
                <td class="fw-semibold">{{ $a->start_at->format('M j, Y · g:i A') }}</td>
                <td>{{ $a->provider->name }}</td>
                <td>{{ $a->service->name ?? '—' }}</td>
                <td><span class="badge bg-{{ $a->status_color }}-subtle text-{{ $a->status_color }}">{{ $a->status_label }}</span></td>
                <td class="text-end">
                  <div class="d-flex gap-1 justify-content-end">
                    @if ($a->is_telehealth && $a->telehealth_link)
                      <a href="{{ $a->telehealth_link }}" target="_blank" class="btn btn-sm btn-success" title="Join video visit"><i class="fi fi-rr-video-camera"></i></a>
                    @endif
                    <a href="{{ route('intake.edit', $a) }}" class="btn btn-sm btn-light" title="Intake form"><i class="fi fi-rr-document"></i></a>
                    <form method="POST" action="{{ route('booking.cancel', $a) }}" onsubmit="return confirm('Cancel this appointment?')">
                      @csrf @method('PATCH')
                      <button class="btn btn-sm btn-outline-danger">Cancel</button>
                    </form>
                  </div>
                </td>
              </tr>
            @empty
              <tr><td colspan="5" class="text-center text-muted py-4">No upcoming appointments. <a href="{{ route('booking.create') }}">Book one now</a>.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-header"><h6 class="mb-0">Past visits</h6></div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table align-middle mb-0">
          <thead class="table-light"><tr><th>Date</th><th>Provider</th><th>Service</th><th>Status</th><th></th></tr></thead>
          <tbody>
            @forelse ($past as $a)
              <tr>
                <td>{{ $a->start_at->format('M j, Y') }}</td>
                <td>{{ $a->provider->name }}</td>
                <td>{{ $a->service->name ?? '—' }}</td>
                <td><span class="badge bg-{{ $a->status_color }}-subtle text-{{ $a->status_color }}">{{ $a->status_label }}</span></td>
                <td class="text-end">
                  @if ($a->status === \App\Models\Appointment::STATUS_COMPLETED)
                    <a href="{{ route('reviews.create', $a) }}" class="btn btn-sm btn-light"><i class="fi fi-rr-star me-1"></i> Review</a>
                  @endif
                </td>
              </tr>
            @empty
              <tr><td colspan="5" class="text-center text-muted py-4">No past visits.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>
@endsection
