@extends('layouts.app')

@section('title', 'Appointments')

@section('page_actions')
  @can('manage appointments')
    <a href="{{ route('appointments.notifications.edit') }}" class="btn btn-outline-secondary"><i class="fi fi-rr-bell me-1"></i> Notifications</a>
    <a href="{{ route('appointments.create') }}" class="btn btn-primary"><i class="fi fi-rr-plus me-1"></i> New Appointment</a>
  @endcan
@endsection

@section('content')
  <div class="card mb-3">
    <div class="card-body">
      <form method="GET" class="row g-2 align-items-end">
        <div class="col-md-3">
          <label class="form-label small text-muted">Search patient</label>
          <input type="text" name="q" value="{{ request('q') }}" class="form-control" placeholder="Name or email">
        </div>
        <div class="col-md-3">
          <label class="form-label small text-muted">Status</label>
          <select name="status" class="form-select">
            <option value="">All</option>
            @foreach ($statuses as $key => $label)
              <option value="{{ $key }}" @selected(request('status') === $key)>{{ $label }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label small text-muted">Provider</label>
          <select name="provider_id" class="form-select">
            <option value="">All</option>
            @foreach ($providers as $p)
              <option value="{{ $p->id }}" @selected(request('provider_id') == $p->id)>{{ $p->name }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-2">
          <label class="form-label small text-muted">Date</label>
          <input type="date" name="date" value="{{ request('date') }}" class="form-control">
        </div>
        <div class="col-md-1 d-grid">
          <button class="btn btn-primary">Filter</button>
        </div>
      </form>
    </div>
  </div>

  <div class="card">
    <div class="card-body p-0">
      <div class="table-responsive p-3">
        <table class="table table-hover align-middle mb-0 datatable">
          <thead class="table-light">
            <tr><th>When</th><th>Patient</th><th>Provider</th><th>Service</th><th>Status</th><th>Risk</th><th></th></tr>
          </thead>
          <tbody>
            @forelse ($appointments as $a)
              <tr>
                <td class="fw-semibold">{{ $a->start_at->format('M j, Y') }}<br><small class="text-muted">{{ $a->start_at->format('g:i A') }}</small></td>
                <td>{{ $a->patient->name }}</td>
                <td>{{ $a->provider->name }}</td>
                <td>{{ $a->service->name ?? '—' }}</td>
                <td><span class="badge bg-{{ $a->status_color }}-subtle text-{{ $a->status_color }}">{{ $a->status_label }}</span></td>
                <td>
                  @php $rc = ['high' => 'danger', 'medium' => 'warning', 'low' => 'success'][$a->risk_level]; @endphp
                  <span class="badge bg-{{ $rc }}-subtle text-{{ $rc }}">{{ $a->no_show_score }}%</span>
                </td>
                <td class="text-end">
                  <a href="{{ route('appointments.show', $a) }}" class="btn btn-sm btn-light">View</a>
                </td>
              </tr>
            @empty
              <tr><td colspan="7" class="text-center text-muted py-4">No appointments found.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>
@endsection
