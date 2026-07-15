@extends('layouts.app')

@section('title', 'Walk-in Queue')

@section('content')
  <div class="row g-3 mb-3">
    <div class="col-6 col-md-3 col-xl">
      <x-stat-widget label="Waiting now" :value="$stats['waiting']" icon="fi-rr-hourglass-end" bg="bg-warning-subtle" fg="text-warning" />
    </div>
    <div class="col-6 col-md-3 col-xl">
      <x-stat-widget label="Being served" :value="$stats['serving']" icon="fi-rr-user-check" bg="bg-success-subtle" fg="text-success" />
    </div>
    <div class="col-6 col-md-3 col-xl">
      <x-stat-widget label="Done today" :value="$stats['done_today']" icon="fi-rr-check-circle" bg="bg-primary-subtle" fg="text-primary" />
    </div>
    <div class="col-6 col-md-3 col-xl">
      <div class="card sas-card sas-card-hover h-100"><div class="card-body">
        <div class="text-muted small">Left / no-show today</div>
        <div class="h3 fw-bold {{ $stats['left_today'] > 0 ? 'text-danger' : '' }} mb-0">{{ $stats['left_today'] }}</div>
        @if ($stats['left_rate'] !== null)
          <div class="text-muted small">{{ $stats['left_rate'] }}% of today's total</div>
        @endif
      </div></div>
    </div>
    <div class="col-6 col-md-3 col-xl">
      <div class="card sas-card sas-card-hover h-100"><div class="card-body">
        <div class="text-muted small">Avg. wait today</div>
        <div class="h3 fw-bold mb-0">{{ $stats['avg_wait_minutes'] !== null ? $stats['avg_wait_minutes'].' min' : '—' }}</div>
      </div></div>
    </div>
    <div class="col-6 col-md-3 col-xl">
      <x-stat-widget label="Done this week" :value="$stats['done_this_week']" icon="fi-rr-calendar-check" bg="bg-secondary-subtle" fg="text-secondary" />
    </div>
    <div class="col-6 col-md-3 col-xl">
      <x-stat-widget label="Done last 30 days" :value="$stats['done_this_month']" icon="fi-rr-calendar" bg="bg-secondary-subtle" fg="text-secondary" />
    </div>
  </div>

  <div class="row g-3">
    <div class="col-xl-9">
      <x-card bodyClass="p-0" class="mb-3">
        <x-slot:title>Waiting</x-slot:title>
        <x-slot:toolbar>
          <span class="badge bg-primary-subtle text-primary">{{ $stats['waiting'] }} waiting</span>
        </x-slot:toolbar>
        <div class="table-responsive">
          <table class="table align-middle mb-0 datatable">
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
                    <x-badge-status :color="$badge" :label="ucfirst($e->status)" />
                  </td>
                  <td class="text-end">
                    <div class="d-flex gap-1 justify-content-end align-items-center">
                      <button type="button" class="btn btn-sm btn-outline-secondary" title="Patient view" aria-label="Patient view" data-bs-toggle="modal" data-bs-target="#walkinPos{{ $e->id }}">
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
                      <div class="dropdown sas-dropdown-actions">
                        <button type="button" class="btn btn-sm btn-light" data-bs-toggle="dropdown" aria-expanded="false" aria-label="More actions">
                          <i class="fi fi-rr-menu-dots-vertical"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                          <li>
                            <form method="POST" action="{{ route('walkins.status', $e) }}" data-sas-confirm="Mark as left / no-show?" data-sas-confirm-label="Mark left">
                              @csrf @method('PATCH')
                              <input type="hidden" name="status" value="left">
                              <button type="submit" class="dropdown-item"><i class="fi fi-rr-exclamation"></i> Left / no-show</button>
                            </form>
                          </li>
                          <li>
                            <form method="POST" action="{{ route('walkins.destroy', $e) }}" data-sas-confirm="Remove from queue?" data-sas-confirm-label="Remove">
                              @csrf @method('DELETE')
                              <button type="submit" class="dropdown-item text-danger"><i class="fi fi-rr-trash"></i> Remove</button>
                            </form>
                          </li>
                        </ul>
                      </div>
                    </div>
                  </td>
                </tr>
              @empty
                <x-empty-state colspan="7" icon="fi-rr-users" title="No one in the queue" description="Walk-ins you add below will show up here." />
              @endforelse
            </tbody>
          </table>
        </div>
      </x-card>

      {{-- "Patient view" modals — rendered server-side per row so opening it
           needs no page navigation or extra request. --}}
      @foreach ($entries as $e)
        <x-modal id="walkinPos{{ $e->id }}" title="Patient view — {{ $e->name }}">
          <div class="text-center py-4">
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
        </x-modal>
      @endforeach

      <x-card bodyClass="p-0">
        <x-slot:title>Completed today</x-slot:title>
        <x-slot:toolbar>
          <span class="badge bg-secondary-subtle text-secondary">{{ $completedToday->count() }}</span>
        </x-slot:toolbar>
        <div class="table-responsive">
          <table class="table align-middle mb-0 datatable">
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
                    <x-badge-status :color="$badge" :label="$e->status === 'left' ? 'Left / no-show' : 'Done'" />
                  </td>
                </tr>
              @empty
                <x-empty-state colspan="7" icon="fi-rr-check" title="No completed walk-ins yet today" />
              @endforelse
            </tbody>
          </table>
        </div>
      </x-card>
    </div>

    <div class="col-xl-3">
      <x-card title="Add a walk-in">
        <form method="POST" action="{{ route('walkins.store') }}">
          @csrf
          <div class="mb-3">
            <x-form-field name="name" label="Name" :value="old('name')" :required="true" />
          </div>
          <div class="mb-3">
            <x-form-field name="phone" label="Phone" :value="old('phone')" />
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
      </x-card>
    </div>
  </div>
@endsection
