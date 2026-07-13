@extends('layouts.app')

@section('title', 'Walk-in Queue')

@section('content')
  <div class="row g-3 mb-3">
    <div class="col-6 col-md-3 col-xl">
      <div class="card"><div class="card-body">
        <div class="text-muted small">Waiting now</div><div class="h3 fw-bold text-warning mb-0">{{ $stats['waiting'] }}</div>
      </div></div>
    </div>
    <div class="col-6 col-md-3 col-xl">
      <div class="card"><div class="card-body">
        <div class="text-muted small">Being served</div><div class="h3 fw-bold text-success mb-0">{{ $stats['serving'] }}</div>
      </div></div>
    </div>
    <div class="col-6 col-md-3 col-xl">
      <div class="card"><div class="card-body">
        <div class="text-muted small">Done today</div><div class="h3 fw-bold text-primary mb-0">{{ $stats['done_today'] }}</div>
      </div></div>
    </div>
    <div class="col-6 col-md-3 col-xl">
      <div class="card"><div class="card-body">
        <div class="text-muted small">Left / no-show today</div>
        <div class="h3 fw-bold {{ $stats['left_today'] > 0 ? 'text-danger' : '' }} mb-0">{{ $stats['left_today'] }}</div>
        @if ($stats['left_rate'] !== null)
          <div class="text-muted small">{{ $stats['left_rate'] }}% of today's total</div>
        @endif
      </div></div>
    </div>
    <div class="col-6 col-md-3 col-xl">
      <div class="card"><div class="card-body">
        <div class="text-muted small">Avg. wait today</div>
        <div class="h3 fw-bold mb-0">{{ $stats['avg_wait_minutes'] !== null ? $stats['avg_wait_minutes'].' min' : '—' }}</div>
      </div></div>
    </div>
    <div class="col-6 col-md-3 col-xl">
      <div class="card"><div class="card-body">
        <div class="text-muted small">Done this week</div><div class="h3 fw-bold mb-0">{{ $stats['done_this_week'] }}</div>
      </div></div>
    </div>
    <div class="col-6 col-md-3 col-xl">
      <div class="card"><div class="card-body">
        <div class="text-muted small">Done last 30 days</div><div class="h3 fw-bold mb-0">{{ $stats['done_this_month'] }}</div>
      </div></div>
    </div>
  </div>

  <div class="row g-3">
    <div class="col-xl-9">
      <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h6 class="mb-0">Waiting</h6>
          <span class="badge bg-primary-subtle text-primary">{{ $stats['waiting'] }} waiting</span>
        </div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table align-middle mb-0">
              <thead class="table-light"><tr><th>#</th><th>Name</th><th>Provider pref.</th><th>Service</th><th>Waiting since</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
              <tbody>
                @forelse ($entries as $e)
                  <tr>
                    <td>{{ $e->status === 'waiting' ? $e->position : '—' }}</td>
                    <td>
                      {{ $e->name }}
                      @if ($e->phone)<br><span class="text-muted small">{{ $e->phone }}</span>@endif
                    </td>
                    <td>{{ $e->provider->name ?? 'Any' }}</td>
                    <td>{{ $e->service->name ?? 'Any' }}</td>
                    <td>{{ $e->joined_at->diffForHumans() }}</td>
                    <td>
                      @php $badge = ['waiting' => 'warning', 'serving' => 'success'][$e->status] ?? 'secondary'; @endphp
                      <span class="badge bg-{{ $badge }}-subtle text-{{ $badge }}">{{ ucfirst($e->status) }}</span>
                    </td>
                    <td class="text-end">
                      <div class="d-flex gap-1 justify-content-end">
                        <button type="button" class="btn btn-sm btn-outline-secondary" title="Patient view" data-bs-toggle="modal" data-bs-target="#walkinPos{{ $e->id }}">
                          <i class="fi fi-rr-eye"></i>
                        </button>
                        @if ($e->status === 'waiting')
                          <form method="POST" action="{{ route('walkins.status', $e) }}">
                            @csrf @method('PATCH')
                            <input type="hidden" name="status" value="serving">
                            <button class="btn btn-sm btn-success">Call in</button>
                          </form>
                        @elseif ($e->status === 'serving')
                          <form method="POST" action="{{ route('walkins.status', $e) }}">
                            @csrf @method('PATCH')
                            <input type="hidden" name="status" value="done">
                            <button class="btn btn-sm btn-primary">Done</button>
                          </form>
                        @endif
                        <form method="POST" action="{{ route('walkins.status', $e) }}" onsubmit="return confirm('Mark as left / no-show?')">
                          @csrf @method('PATCH')
                          <input type="hidden" name="status" value="left">
                          <button class="btn btn-sm btn-outline-warning" title="Left / no-show">Left</button>
                        </form>
                        <form method="POST" action="{{ route('walkins.destroy', $e) }}" onsubmit="return confirm('Remove from queue?')">
                          @csrf @method('DELETE')
                          <button class="btn btn-sm btn-outline-danger">Remove</button>
                        </form>
                      </div>
                    </td>
                  </tr>
                @empty
                  <tr><td colspan="7" class="text-center text-muted py-4">No one in the queue right now.</td></tr>
                @endforelse
              </tbody>
            </table>
          </div>
        </div>
      </div>

      {{-- "Patient view" modals — rendered server-side per row so opening it
           needs no page navigation or extra request. --}}
      @foreach ($entries as $e)
        <div class="modal fade" id="walkinPos{{ $e->id }}" tabindex="-1" aria-hidden="true">
          <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
              <div class="modal-header">
                <h6 class="modal-title mb-0">Patient view — {{ $e->name }}</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
              </div>
              <div class="modal-body text-center py-4">
                <p class="text-muted small mb-1">Hi {{ $e->name }},</p>
                @if ($e->status === 'waiting')
                  <div class="display-4 fw-bold text-primary mb-2">{{ $e->position }}</div>
                  <h6 class="mb-3">{{ $e->position === 1 ? "You're next!" : 'people ahead of you' }}</h6>
                  <p class="text-muted small mb-0">Waiting since {{ $e->joined_at->format('g:i A') }}.</p>
                @elseif ($e->status === 'serving')
                  <div class="badge bg-success-subtle text-success mb-3 fs-6">You're up!</div>
                  <h5 class="mb-0">Please head to the front desk now.</h5>
                @endif
              </div>
            </div>
          </div>
        </div>
      @endforeach

      <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h6 class="mb-0">Completed today</h6>
          <span class="badge bg-secondary-subtle text-secondary">{{ $completedToday->count() }}</span>
        </div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table align-middle mb-0">
              <thead class="table-light"><tr><th>Name</th><th>Provider</th><th>Service</th><th>Joined</th><th>Wait time</th><th>Completed</th><th>Status</th></tr></thead>
              <tbody>
                @forelse ($completedToday as $e)
                  <tr>
                    <td>
                      {{ $e->name }}
                      @if ($e->phone)<br><span class="text-muted small">{{ $e->phone }}</span>@endif
                    </td>
                    <td>{{ $e->provider->name ?? '—' }}</td>
                    <td>{{ $e->service->name ?? '—' }}</td>
                    <td>{{ $e->joined_at->format('g:i A') }}</td>
                    <td>{{ $e->called_at ? round($e->joined_at->diffInMinutes($e->called_at, true)).' min' : '—' }}</td>
                    <td>{{ $e->completed_at->format('g:i A') }}</td>
                    <td>
                      @php $badge = ['done' => 'primary', 'left' => 'danger'][$e->status] ?? 'secondary'; @endphp
                      <span class="badge bg-{{ $badge }}-subtle text-{{ $badge }}">{{ $e->status === 'left' ? 'Left / no-show' : 'Done' }}</span>
                    </td>
                  </tr>
                @empty
                  <tr><td colspan="7" class="text-center text-muted py-4">No completed walk-ins yet today.</td></tr>
                @endforelse
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <div class="col-xl-3">
      <div class="card">
        <div class="card-header"><h6 class="mb-0">Add a walk-in</h6></div>
        <div class="card-body">
          <form method="POST" action="{{ route('walkins.store') }}">
            @csrf
            <div class="mb-3">
              <label class="form-label" for="wiName">Name <span class="text-danger">*</span></label>
              <input type="text" name="name" id="wiName" class="form-control" required>
            </div>
            <div class="mb-3">
              <label class="form-label" for="wiPhone">Phone</label>
              <input type="text" name="phone" id="wiPhone" class="form-control">
            </div>
            <div class="mb-3">
              <label class="form-label" for="wiProvider">Preferred provider</label>
              <select name="provider_id" id="wiProvider" class="form-select">
                <option value="">Any</option>
                @foreach ($providers as $p)<option value="{{ $p->id }}">{{ $p->name }}</option>@endforeach
              </select>
            </div>
            <div class="mb-3">
              <label class="form-label" for="wiService">Service</label>
              <select name="service_id" id="wiService" class="form-select">
                <option value="">Any</option>
                @foreach ($services as $s)<option value="{{ $s->id }}">{{ $s->name }}</option>@endforeach
              </select>
            </div>
            <button class="btn btn-primary w-100">Add to queue</button>
          </form>
        </div>
      </div>
    </div>
  </div>
@endsection
