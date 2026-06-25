@extends('layouts.app')

@section('title', 'Appointment #'.$appointment->id)

@section('page_actions')
  @can('manage appointments')
    <a href="{{ route('appointments.edit', $appointment) }}" class="btn btn-light"><i class="fi fi-rr-pencil me-1"></i> Edit</a>
    <form method="POST" action="{{ route('appointments.destroy', $appointment) }}" class="d-inline" onsubmit="return confirm('Delete this appointment?')">
      @csrf @method('DELETE')
      <button class="btn btn-outline-danger">Delete</button>
    </form>
  @endcan
@endsection

@section('content')
  <div class="row g-3">
    <div class="col-xl-8">
      <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h6 class="mb-0">Appointment details</h6>
          <span class="badge bg-{{ $appointment->status_color }}-subtle text-{{ $appointment->status_color }} fs-6">{{ $appointment->status_label }}</span>
        </div>
        <div class="card-body">
          <dl class="row mb-0">
            <dt class="col-sm-4 text-muted">Patient</dt><dd class="col-sm-8">{{ $appointment->patient->name }} <small class="text-muted">({{ $appointment->patient->email }})</small></dd>
            <dt class="col-sm-4 text-muted">Provider</dt><dd class="col-sm-8">{{ $appointment->provider->name }} — {{ $appointment->provider->specialty }}</dd>
            <dt class="col-sm-4 text-muted">Service</dt><dd class="col-sm-8">{{ $appointment->service->name ?? '—' }}</dd>
            <dt class="col-sm-4 text-muted">When</dt><dd class="col-sm-8">{{ $appointment->start_at->format('l, F j, Y') }} · {{ $appointment->start_at->format('g:i A') }} – {{ $appointment->end_at->format('g:i A') }}</dd>
            <dt class="col-sm-4 text-muted">Clinic</dt><dd class="col-sm-8">{{ $appointment->clinic->name ?? '—' }}</dd>
            <dt class="col-sm-4 text-muted">Room/Resource</dt><dd class="col-sm-8">{{ $appointment->resource->name ?? '—' }}</dd>
            <dt class="col-sm-4 text-muted">Channel</dt><dd class="col-sm-8">{{ ucfirst($appointment->channel) }}</dd>
            <dt class="col-sm-4 text-muted">Telehealth</dt>
            <dd class="col-sm-8">
              @if ($appointment->is_telehealth && $appointment->telehealth_link)
                <a href="{{ $appointment->telehealth_link }}" target="_blank" class="btn btn-sm btn-success"><i class="fi fi-rr-video-camera me-1"></i> Join video visit</a>
              @else
                {{ $appointment->is_telehealth ? 'Yes' : 'No' }}
              @endif
            </dd>
            @if ($appointment->intakeForm?->ai_summary)
              <dt class="col-sm-4 text-muted">Intake summary</dt>
              <dd class="col-sm-8"><pre class="small bg-light p-2 rounded mb-0">{{ $appointment->intakeForm->ai_summary }}</pre></dd>
            @endif
            <dt class="col-sm-4 text-muted">Reason</dt><dd class="col-sm-8">{{ $appointment->reason ?? '—' }}</dd>
            <dt class="col-sm-4 text-muted">Notes</dt><dd class="col-sm-8">{{ $appointment->notes ?? '—' }}</dd>
          </dl>
        </div>
      </div>

      <div class="card">
        <div class="card-header"><h6 class="mb-0">Reminders</h6></div>
        <div class="card-body p-0">
          <table class="table mb-0">
            <thead class="table-light"><tr><th>Type</th><th>Channel</th><th>Scheduled</th><th>Status</th></tr></thead>
            <tbody>
              @forelse ($appointment->reminders as $rem)
                <tr><td>{{ ucfirst($rem->type) }}</td><td>{{ ucfirst($rem->channel) }}</td><td>{{ $rem->scheduled_at->format('M j, g:i A') }}</td><td>{{ ucfirst($rem->status) }}</td></tr>
              @empty
                <tr><td colspan="4" class="text-center text-muted py-3">No reminders scheduled.</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <div class="col-xl-4">
      <div class="card mb-3">
        <div class="card-header"><h6 class="mb-0">No-show risk</h6></div>
        <div class="card-body text-center">
          @php $rc = ['high' => 'danger', 'medium' => 'warning', 'low' => 'success'][$appointment->risk_level]; @endphp
          <div class="display-4 fw-bold text-{{ $rc }}">{{ $appointment->no_show_score }}%</div>
          <div class="text-muted">{{ ucfirst($appointment->risk_level) }} risk</div>
        </div>
      </div>

      @can('manage appointments')
        <div class="card mb-3">
          <div class="card-header"><h6 class="mb-0">Update status</h6></div>
          <div class="card-body">
            <form method="POST" action="{{ route('appointments.status', $appointment) }}">
              @csrf @method('PATCH')
              <select name="status" class="form-select mb-2">
                @foreach (\App\Models\Appointment::STATUSES as $key => $label)
                  <option value="{{ $key }}" @selected($appointment->status === $key)>{{ $label }}</option>
                @endforeach
              </select>
              <input type="text" name="cancellation_reason" class="form-control mb-2" placeholder="Reason (if cancelling)">
              <button class="btn btn-primary w-100">Update</button>
            </form>
          </div>
        </div>
      @endcan

      <div class="card">
        <div class="card-header"><h6 class="mb-0">Quick actions</h6></div>
        <div class="card-body d-grid gap-2">
          <a href="{{ route('intake.edit', $appointment) }}" class="btn btn-light">
            <i class="fi fi-rr-document me-1"></i> Intake form
            @if ($appointment->intakeForm?->status === 'completed')<span class="badge bg-success-subtle text-success ms-1">Done</span>@endif
          </a>
          @if ($appointment->status !== \App\Models\Appointment::STATUS_CHECKED_IN)
            <form method="POST" action="{{ route('intake.checkin', $appointment) }}">
              @csrf @method('PATCH')
              <button class="btn btn-light w-100"><i class="fi fi-rr-marker me-1"></i> Digital check-in</button>
            </form>
          @endif

          @can('manage appointments')
            <hr class="my-1">
            <form method="POST" action="{{ route('payments.charge', $appointment) }}" class="row g-1">
              @csrf
              <div class="col-5"><input type="number" step="0.01" name="amount" class="form-control form-control-sm" placeholder="Amt" value="{{ $appointment->service?->price }}"></div>
              <div class="col-4">
                <select name="type" class="form-select form-select-sm">
                  <option value="copay">Copay</option><option value="fee">Fee</option><option value="no_show_fee">No-show</option>
                </select>
              </div>
              <div class="col-3"><button class="btn btn-sm btn-primary w-100">Charge</button></div>
              <input type="hidden" name="method" value="cash">
            </form>
          @endcan
        </div>
      </div>
    </div>
  </div>
@endsection
