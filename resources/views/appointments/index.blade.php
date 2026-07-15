@extends('layouts.app')

@section('title', 'Appointments')

@section('page_actions')
  @can('manage appointments')
    <a href="{{ route('appointments.notifications.edit') }}" class="btn btn-outline-secondary"><i class="fi fi-rr-bell me-1"></i> Notifications</a>
    <a href="{{ route('appointments.create') }}" class="btn btn-primary"><i class="fi fi-rr-plus me-1"></i> New Appointment</a>
  @endcan
@endsection

@section('content')
  <x-card bodyClass="pb-2" class="mb-3">
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
  </x-card>

  <x-card bodyClass="p-0">
    <div class="table-responsive">
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
              <td><x-badge-status :color="$a->status_color" :label="$a->status_label" /></td>
              <td>
                @php $rc = ['high' => 'danger', 'medium' => 'warning', 'low' => 'success'][$a->risk_level]; @endphp
                <x-badge-status :color="$rc" :label="$a->no_show_score.'%'" />
              </td>
              <td class="text-end">
                <a href="{{ route('appointments.show', $a) }}" class="btn btn-sm btn-light">View</a>
              </td>
            </tr>
          @empty
            <x-empty-state colspan="7" icon="fi-rr-calendar" title="No appointments found" description="Try adjusting your filters." />
          @endforelse
        </tbody>
      </table>
    </div>
  </x-card>
@endsection
